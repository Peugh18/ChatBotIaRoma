<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ServicioNotificacionEnvioPedido
{
    public function __construct(
        protected ServicioMediaProducto $media,
    ) {}

    /**
     * @return array{message: Message, content: string}
     */
    public function enviar(
        Order $order,
        string $mensajeBase,
        ?string $codigoRecojo = null,
        ?UploadedFile $imagen = null,
    ): array {
        $order->loadMissing('customer');
        $phone = $order->customer?->phone_number;
        if (! $phone) {
            throw new \InvalidArgumentException('El pedido no tiene teléfono de cliente.');
        }

        $contenido = trim($mensajeBase);
        if ($codigoRecojo !== null && trim($codigoRecojo) !== '') {
            $codigo = trim($codigoRecojo);
            $contenido .= "\n\n*Código de recojo:* *{$codigo}*";
        }

        $imageUrl = null;
        if ($imagen !== null) {
            $path = $imagen->store('shipping-proofs', 'public');
            $imageUrl = Storage::disk('public')->url($path);
            $imageUrl = $this->media->resolveWhatsappSendUrl($imageUrl);
            if (! $this->media->isUrlReachableByMeta($imageUrl)) {
                throw new \InvalidArgumentException(
                    'La imagen no es accesible desde internet. Revisa PUBLIC_APP_URL (ngrok).'
                );
            }
        }

        $customer = Customer::firstOrCreate(
            ['phone_number' => $phone],
            ['first_seen_at' => now(), 'last_seen_at' => now(), 'segment' => 'lead']
        );

        $estado = ConversationState::firstOrCreate(
            ['phone_number' => $phone],
            [
                'customer_id' => $customer->id,
                'current_state' => 'greeting',
                'context' => [],
                'last_activity_at' => now(),
            ]
        );

        if (empty($estado->customer_id)) {
            $estado->update(['customer_id' => $customer->id]);
        }

        $estado->update([
            'requires_human' => true,
            'is_auto_escalated' => false,
            'last_human_activity_at' => now(),
        ]);

        $metadata = [];
        if ($imageUrl) {
            $metadata['image_url'] = $imageUrl;
            $metadata['type'] = 'image';
        }

        $content = $contenido !== '' ? $contenido : ($imageUrl ? '📸' : '');

        $message = Message::create([
            'message_id' => 'temp_'.uniqid(),
            'phone_number' => $phone,
            'customer_id' => $customer->id,
            'conversation_state_id' => $estado->id,
            'customer_name' => $customer->name,
            'content' => $content,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);

        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new MessageReceived($message))->toOthers();
            } catch (\Exception $e) {
                Log::error('ServicioNotificacionEnvioPedido: broadcast falló', ['error' => $e->getMessage()]);
            }
        }

        SendWhatsappMessageJob::dispatch($message);

        $order->update([
            'status' => 'shipped',
            'shipped_at' => now(),
        ]);

        return ['message' => $message, 'content' => $content];
    }
}
