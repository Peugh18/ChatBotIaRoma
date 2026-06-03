<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessageJob;
use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Models\Message;
use App\Ventas\MaquinaEstados\MaquinaEstadosVentas;
use App\Ventas\Servicios\ServicioCarrito;

/**
 * Recordatorios 3 min / 15 min en etapas de compra activas.
 */
class ServicioRecordatorios
{
    public function __construct(
        protected MaquinaEstadosVentas $maquina,
        protected ServicioCarrito $carrito,
    ) {}

    public function getSettings(): BotSetting
    {
        return BotSetting::firstOrFail();
    }

    /**
     * @return list<array{phone: string, type: string}>
     */
    public function checkReminders(): array
    {
        $settings = $this->getSettings();
        if (! $settings->auto_reply_enabled) {
            return [];
        }

        $segundos3 = (int) ($settings->reminder_3min_seconds ?? 180);
        $segundos15 = (int) ($settings->reminder_15min_seconds ?? 900);
        $etapas = $this->maquina->etapasConRecordatorio();
        $enviados = [];

        $estados = ConversationState::query()
            ->where('requires_human', false)
            ->where('last_activity_at', '<', now()->subSeconds($segundos3))
            ->get();

        foreach ($estados as $estado) {
            $etapa = $this->maquina->obtener($estado);
            if ($etapa === null || ! in_array($etapa, $etapas, true)) {
                continue;
            }

            $ultimo = $estado->last_reminder_sent ?? 'none';

            if ($ultimo === 'none' && $estado->last_activity_at->lt(now()->subSeconds($segundos3))) {
                $texto = trim((string) $settings->reminder_3min_message);
                if ($texto !== '' && $this->enviar($estado, $texto)) {
                    $estado->update(['last_reminder_sent' => '3min']);
                    $enviados[] = ['phone' => $estado->phone_number, 'type' => '3min'];
                }

                continue;
            }

            if ($ultimo === '3min' && $estado->last_activity_at->lt(now()->subSeconds($segundos15))) {
                if ($etapa === \App\Ventas\MaquinaEstados\EtapaVentas::MAS_O_CONFIRMAR) {
                    $revalidado = $this->carrito->revalidar($this->maquina->carrito($estado));
                    if ($revalidado['cambio']) {
                        $this->maquina->guardarCarrito($estado, $revalidado['lineas']);
                    }
                }

                $texto = trim((string) $settings->reminder_15min_message);
                if ($texto !== '' && $this->enviar($estado, $texto)) {
                    $estado->update(['last_reminder_sent' => '15min']);
                    $enviados[] = ['phone' => $estado->phone_number, 'type' => '15min'];
                }
            }
        }

        return $enviados;
    }

    protected function enviar(ConversationState $estado, string $texto): bool
    {
        $message = Message::create([
            'message_id' => 'temp_reminder_'.uniqid(),
            'phone_number' => $estado->phone_number,
            'customer_id' => $estado->customer_id,
            'conversation_state_id' => $estado->id,
            'content' => $texto,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['source' => 'reminder'],
        ]);

        SendWhatsappMessageJob::dispatch($message);

        return true;
    }
}
