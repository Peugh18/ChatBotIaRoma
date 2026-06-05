<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Ventas\Constructores\ConstructorMensaje;
use App\Ventas\MaquinaEstados\EtapaVentas;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use Illuminate\Support\Facades\Log;

/**
 * Flujo tarjeta: asesor envía link por CRM; el bot sigue activo hasta el comprobante.
 */
class ServicioLinkPagoTarjeta
{
    public const CTX_PENDIENTE = 'pendiente_link_tarjeta';

    public const CTX_SOLICITADO_AT = 'link_tarjeta_solicitado_at';

    public function __construct(
        protected MaquinaEstadosVentas $maquina,
        protected ConstructorMensaje $mensajes,
    ) {}

    public function marcarPendienteEnvio(ConversationState $estado): void
    {
        $ctx = $estado->context ?? [];
        $ctx[self::CTX_PENDIENTE] = true;
        $ctx[self::CTX_SOLICITADO_AT] = now()->toIso8601String();
        unset($ctx['handoff']);
        $estado->update([
            'context' => $ctx,
            'requires_human' => false,
            'is_auto_escalated' => false,
        ]);
        $this->maquina->establecerCheckoutPaso($estado, null);
        $this->maquina->establecer($estado, EtapaVentas::ESPERANDO_LINK_TARJETA);
    }

    public function estaPendiente(?ConversationState $estado): bool
    {
        if (! $estado) {
            return false;
        }

        $ctx = $estado->context ?? [];
        if (! ($ctx[self::CTX_PENDIENTE] ?? false)) {
            return false;
        }

        return $this->maquina->obtener($estado) === EtapaVentas::ESPERANDO_LINK_TARJETA;
    }

    /**
     * @return array{order_id: int, bot_messages: list<string>}
     */
    public function enviarLink(ConversationState $estado, string $paymentLink): array
    {
        if (! $this->estaPendiente($estado)) {
            throw new \InvalidArgumentException('Esta conversación no está esperando un link de tarjeta.');
        }

        $url = trim($paymentLink);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('El link de pago no es una URL válida.');
        }

        $ctx = $estado->context ?? [];
        $orderId = (int) ($ctx['ultimo_pedido_id'] ?? $ctx['last_order_id'] ?? 0);
        $order = $orderId > 0 ? Order::find($orderId) : null;

        if (! $order || $order->status !== 'pending' || $order->payment_method !== 'card') {
            throw new \InvalidArgumentException('No hay un pedido con tarjeta pendiente de link en esta conversación.');
        }

        $total = number_format((float) $order->amount_total, 2);

        $mensajeLink = $this->mensajes->plantilla('tarjeta_mensaje_link', [
            'link' => $url,
            'total' => $total,
            'pedido' => (string) $orderId,
        ]);
        $mensajeCaptura = $this->mensajes->plantilla('tarjeta_pide_captura_post_link');

        $this->enviarMensajeBot($estado, $mensajeLink, 'card_payment_link');
        $this->enviarMensajeBot($estado, $mensajeCaptura, 'card_payment_link_followup');

        unset($ctx[self::CTX_PENDIENTE], $ctx[self::CTX_SOLICITADO_AT], $ctx['handoff']);
        $ctx['payment_link_sent_at'] = now()->toIso8601String();
        $ctx['payment_link_url'] = $url;
        $estado->update(['context' => $ctx]);

        $order->update([
            'notes' => trim(($order->notes ?? '').' | Link enviado: '.$url),
        ]);

        $this->maquina->establecer($estado, EtapaVentas::COMPROBANTE);
        $this->maquina->establecerCheckoutPaso($estado, null);

        Log::info('ServicioLinkPagoTarjeta: link enviado', [
            'phone' => $estado->phone_number,
            'order_id' => $orderId,
        ]);

        return [
            'order_id' => $orderId,
            'bot_messages' => [$mensajeLink, $mensajeCaptura],
        ];
    }

    protected function enviarMensajeBot(ConversationState $estado, string $texto, string $source): void
    {
        if (trim($texto) === '') {
            return;
        }

        $customer = Customer::find($estado->customer_id);
        $message = Message::create([
            'message_id' => 'temp_card_'.uniqid(),
            'phone_number' => $estado->phone_number,
            'customer_id' => $estado->customer_id,
            'conversation_state_id' => $estado->id,
            'customer_name' => $customer?->name,
            'content' => $texto,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['source' => $source],
        ]);

        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new MessageReceived($message))->toOthers();
            } catch (\Exception $e) {
                Log::error('ServicioLinkPagoTarjeta: broadcast falló', ['error' => $e->getMessage()]);
            }
        }

        SendWhatsappMessageJob::dispatch($message);
    }
}
