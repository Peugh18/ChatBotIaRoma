<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageReceived;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessageJob;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\ConversationState;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Services\ServicioMediaProducto;
use App\Support\EtapaVenta;
use App\Support\AutenticacionWebhookRoma;
use App\Support\RegistroSeguro;
use App\Support\ContratoMensajeWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RomaSyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        if (! AutenticacionWebhookRoma::verify($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'type' => 'required|string|in:products,categories,delivery,company,all',
        ]);

        switch ($data['type']) {
            case 'products':
                return response()->json(Product::with(['category', 'variants'])->get());
            case 'categories':
                return response()->json(Category::all());
            case 'delivery':
                return response()->json(DeliveryZone::all());
            case 'company':
                return response()->json(CompanySetting::first());
            case 'all':
                return response()->json([
                    'products' => Product::with(['category', 'variants'])->get(),
                    'categories' => Category::all(),
                    'delivery_zones' => DeliveryZone::all(),
                    'company_settings' => CompanySetting::first(),
                ]);
            default:
                return response()->json(['error' => 'Invalid type'], 400);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        // Endpoint legacy: delega a flujo canónico para evitar divergencia.
        return $this->receiveMessage($request);
    }

    public function receiveMessage(Request $request): JsonResponse
    {
        if (! AutenticacionWebhookRoma::verify($request)) {
            \Log::warning('Roma messages: auth failed', [
                'ip' => $request->ip(),
                'has_signature' => $request->hasHeader('X-Roma-Signature'),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        \Log::info('Roma messages payload recibido', RegistroSeguro::redactPayload($payload));

        $messageType = $payload['message_type'] ?? 'text';
        $contentPreview = ContratoMensajeWhatsapp::inboundContent($payload);

        // Contenido real del cliente (texto, imagen, botón) — no confundir con receipts de estado.
        $directionRaw = $payload['direction'] ?? 'incoming';
        if ($directionRaw === 'inbound') {
            $directionRaw = 'incoming';
        }
        $hasInboundPayload = $directionRaw === 'incoming' && (
            $contentPreview !== ''
            || in_array($messageType, ['image', 'interactive_button_reply', 'interactive_list_reply'], true)
            || ! empty($payload['interactive'])
            || $messageType === 'image'
        );

        // 1. Manejar Web Events de Estado (solo receipts, sin cuerpo de mensaje nuevo)
        $isStatusUpdate = ! $hasInboundPayload && (
            (isset($payload['event']) && $payload['event'] === 'status')
            || (isset($payload['status']) && in_array($payload['status'], ['sent', 'delivered', 'read'], true))
        );

        if ($isStatusUpdate) {
            $waId = $payload['wa_id'] ?? $payload['message_id'] ?? $payload['id'] ?? null;
            $status = $payload['status'] ?? null;

            if ($waId && $status) {
                $message = Message::where('message_id', $waId)->first();
                if ($message) {
                    $metadata = $message->metadata ?? [];
                    $statusHistory = $metadata['status_history'] ?? [];
                    $statusHistory[] = [
                        'status' => $status,
                        'timestamp' => now()->toDateTimeString(),
                    ];

                    $mergedMetadata = array_merge($metadata, [
                        'webhook_status_update' => now()->toDateTimeString(),
                        'status_history' => $statusHistory,
                    ]);

                    $message->update([
                        'status' => $status,
                        'metadata' => $mergedMetadata,
                    ]);

                    // Si el mensaje entrante quedó en "sent" y el bot no corrió, reintentar al pasar a delivered
                    if (
                        $message->direction === 'incoming'
                        && in_array($status, ['delivered', 'read'], true)
                        && empty($mergedMetadata['bot_processed_at'])
                    ) {
                        $conversationState = ConversationState::where('phone_number', $message->phone_number)->first();
                        if ($conversationState && ! ($conversationState->requires_human ?? false)) {
                            \Log::info('RomaSyncController: Re-queueing bot on delivered status', [
                                'message_id' => $message->id,
                                'phone' => $message->phone_number,
                            ]);
                            $imageUrl = $metadata['image_url'] ?? null;
                            ProcessIncomingMessageJob::dispatch($message, $imageUrl);
                        }
                    }

                    // Disparar evento Pusher para tiempo real en el frontend del CRM
                    if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                        try {
                            broadcast(new MessageReceived($message))->toOthers();
                        } catch (\Exception $e) {
                            \Log::error('Error broadcasting status update: '.$e->getMessage());
                        }
                    }

                    return response()->json(['message' => 'Status updated successfully', 'wa_id' => $waId, 'status' => $status]);
                }
            }

            return response()->json(['message' => 'Status event processed, no matching message found'], 200);
        }

        // 2. Manejar Webhook de Mensajes Entrantes (Inbound Messages)
        $messageId = $payload['wa_id'] ?? $payload['message_id'] ?? $payload['id'] ?? uniqid('msg_');
        $phoneNumber = $payload['from'] ?? $payload['phone_number'] ?? $payload['sender_phone'] ?? null;
        $content = $contentPreview;

        $metadata = ContratoMensajeWhatsapp::inboundMetadata($payload);

        // Buscar URL de imagen en el payload normalizado o en el bloque crudo
        $imageUrl = $payload['image_url'] ?? $payload['media_url'] ?? null;
        if ($messageType === 'image') {
            $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
            $rawImage = is_array($raw['image'] ?? null) ? $raw['image'] : [];
            $imageUrl = $imageUrl
                ?? $rawImage['link']
                ?? $rawImage['url']
                ?? $raw['image_url']
                ?? null;
        }
        if ($imageUrl) {
            if (str_contains((string) $imageUrl, 'lookaside.fbsbx.com')) {
                $resolved = app(\App\Services\ServicioDescargaImagenWhatsapp::class)
                    ->resolverDesdePayloadInbound(array_merge($payload, ['image_url' => $imageUrl]));
                if ($resolved) {
                    $imageUrl = $resolved;
                    \Log::info('RomaSyncController: imagen lookaside resuelta en CRM', [
                        'phone' => $phoneNumber,
                    ]);
                } else {
                    \Log::warning('RomaSyncController: imagen entrante sigue en lookaside', [
                        'phone' => $phoneNumber,
                        'hint' => 'WA_TOKEN en CRM (.env, mismo token Meta) o en otra PC: código roma-api + ROMA_API_PUBLIC_URL + reiniciar dev',
                    ]);
                }
            }
            $metadata['image_url'] = $imageUrl;
        }

        $direction = $payload['direction'] ?? 'incoming';
        if ($direction === 'inbound') {
            $direction = 'incoming';
        }
        if ($direction === 'outbound') {
            $direction = 'outgoing';
        }

        if (! $phoneNumber) {
            return response()->json(['error' => 'Phone number missing'], 400);
        }

        // Obtener o crear Cliente
        $customer = Customer::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'name' => $payload['customer_name'] ?? $payload['name'] ?? null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'segment' => 'lead',
            ]
        );

        if (empty($customer->name) && (! empty($payload['customer_name']) || ! empty($payload['name']))) {
            $customer->update(['name' => $payload['customer_name'] ?? $payload['name']]);
        }

        // Obtener o crear ConversationState
        $conversationState = ConversationState::firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'customer_id' => $customer->id,
                'current_state' => 'greeting',
                'context' => [],
                'last_activity_at' => now(),
            ]
        );

        if (empty($conversationState->customer_id)) {
            $conversationState->update(['customer_id' => $customer->id]);
        }

        // Guardar o actualizar Mensaje
        $message = Message::updateOrCreate(
            ['message_id' => $messageId],
            [
                'phone_number' => $phoneNumber,
                'customer_id' => $customer->id,
                'conversation_state_id' => $conversationState->id,
                'customer_name' => $customer->name,
                'content' => is_string($content) ? $content : json_encode($content),
                'direction' => $direction,
                'status' => $payload['status'] ?? 'delivered',
                'whatsapp_timestamp' => $payload['timestamp'] ?? now(),
                'metadata' => array_merge($metadata, is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []),
            ]
        );

        // Disparar Pusher para tiempo real en el CRM
        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new MessageReceived($message))->toOthers();
            } catch (\Exception $e) {
                \Log::error('Error broadcasting message: '.$e->getMessage());
            }
        }

        // Despachar procesamiento asíncrono para mensajes entrantes (sent o delivered)
        $requiresHuman = $conversationState->requires_human ?? false;
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $alreadyProcessed = ! empty($metadata['bot_processed_at']);
        $canRunBot = in_array($message->status, ['sent', 'delivered', 'read', 'received'], true);

        if ($direction === 'incoming' && $canRunBot && ! $requiresHuman && ! $alreadyProcessed && $hasInboundPayload) {
            \Log::info('RomaSyncController: Queueing message processing job', [
                'phone' => $phoneNumber,
                'message_id' => $message->id,
                'status' => $message->status,
            ]);

            try {
                ProcessIncomingMessageJob::dispatch($message, $imageUrl);
            } catch (\Exception $e) {
                \Log::error('RomaSyncController: Failed to dispatch ProcessIncomingMessageJob', [
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($direction === 'incoming' && $hasInboundPayload) {
            \Log::info('RomaSyncController: Bot processing skipped for inbound message', [
                'phone' => $phoneNumber,
                'message_id' => $message->id,
                'requires_human' => $requiresHuman,
                'already_processed' => $alreadyProcessed,
                'can_run_bot' => $canRunBot,
                'status' => $message->status,
            ]);
        }

        return response()->json([
            'message' => 'Messages received',
            'count' => 1,
            'data' => [$message],
        ], 200);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string',
            'content' => 'nullable|string',
            'image_url' => 'nullable|string',
        ]);

        if (empty($validated['content']) && empty($validated['image_url'])) {
            return response()->json(['message' => 'Se requiere texto o imagen'], 422);
        }

        $media = app(ServicioMediaProducto::class);
        if (! empty($validated['image_url'])) {
            $validated['image_url'] = $media->resolveWhatsappSendUrl($validated['image_url']);
            if (! $media->isUrlReachableByMeta($validated['image_url'])) {
                return response()->json([
                    'message' => 'La foto no es accesible desde internet (WhatsApp no puede descargar localhost).',
                    'hint' => 'Configura PUBLIC_APP_URL en .env con tu URL HTTPS pública del CRM (ngrok a php artisan serve :8000).',
                    'image_url' => $validated['image_url'],
                ], 422);
            }
        }

        try {
            $phoneNumber = $validated['phone_number'];

            // 1. Obtener o crear Cliente
            $customer = Customer::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'segment' => 'lead',
                ]
            );

            // 2. Obtener o crear ConversationState
            $conversationState = ConversationState::firstOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'customer_id' => $customer->id,
                    'current_state' => 'greeting',
                    'context' => [],
                    'last_activity_at' => now(),
                ]
            );

            if (empty($conversationState->customer_id)) {
                $conversationState->update(['customer_id' => $customer->id]);
            }

            // Cuando el humano envía un mensaje, se considera actividad humana activa:
            // Apagamos el bot (requires_human = true), reseteamos is_auto_escalated a false, y actualizamos el timestamp
            $conversationState->update([
                'requires_human' => true,
                'is_auto_escalated' => false,
                'last_human_activity_at' => now(),
            ]);

            $metadata = [];
            $content = (string) ($validated['content'] ?? '');
            if (! empty($validated['image_url'])) {
                $metadata['image_url'] = $validated['image_url'];
                $metadata['type'] = 'image';
                if ($content === '') {
                    $content = '📸';
                }
            }

            // 3. Crear Mensaje en estado "pending"
            $message = Message::create([
                'message_id' => 'temp_'.uniqid(),
                'phone_number' => $phoneNumber,
                'customer_id' => $customer->id,
                'conversation_state_id' => $conversationState->id,
                'customer_name' => $customer->name,
                'content' => $content,
                'direction' => 'outgoing',
                'status' => 'pending',
                'whatsapp_timestamp' => now(),
                'metadata' => $metadata,
            ]);

            // Disparar evento Pusher para tiempo real (para mostrarlo de inmediato en el chat del remitente)
            if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                try {
                    broadcast(new MessageReceived($message))->toOthers();
                } catch (\Exception $e) {
                    \Log::error('Error broadcasting manual pending message: '.$e->getMessage());
                }
            }

            // Despachar el Job para enviar a la API externa de Roma en segundo plano
            SendWhatsappMessageJob::dispatch($message);

            return response()->json([
                'message' => 'Message queued for sending',
                'data' => $message,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('RomaSyncController: Error queueing manual message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to queue message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function retryMessage(int $messageId): JsonResponse
    {
        $message = Message::with('conversationState')->find($messageId);

        if (! $message) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        if ($message->direction !== 'outgoing') {
            return response()->json(['message' => 'Only outgoing messages can be retried'], 422);
        }

        if (! in_array($message->status, ['failed', 'pending'], true)) {
            return response()->json(['message' => 'Message cannot be retried in current status'], 422);
        }

        try {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            $retryCount = (int) ($metadata['retry_count'] ?? 0) + 1;
            unset($metadata['send_error']);
            unset($metadata['provider_error_type']);

            $message->update([
                'status' => 'pending',
                'message_id' => 'temp_'.uniqid(),
                'metadata' => array_merge($metadata, [
                    'retry_count' => $retryCount,
                    'last_retry_at' => now()->toDateTimeString(),
                ]),
            ]);

            if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                try {
                    broadcast(new MessageReceived($message))->toOthers();
                } catch (\Exception $e) {
                    \Log::error('Error broadcasting retried pending message: '.$e->getMessage());
                }
            }

            SendWhatsappMessageJob::dispatch($message);

            return response()->json([
                'message' => 'Message re-queued for sending',
                'data' => $message->fresh(),
            ], 200);
        } catch (\Exception $e) {
            \Log::error('RomaSyncController: Error retrying message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retry message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMessages(Request $request): JsonResponse
    {
        // Temporalmente sin autenticación para pruebas
        $phone_number = $request->query('phone_number');
        $limit = $request->query('limit', 200);

        $query = Message::with(['conversationState', 'customer'])->orderBy('created_at', 'desc');

        if ($phone_number) {
            $query->where('phone_number', $phone_number);
        }

        $messages = $query->limit($limit)->get()->reverse()->values();

        return response()->json($messages);
    }

    public function getMode(string $phone): JsonResponse
    {
        $state = ConversationState::where('phone_number', $phone)->first();

        $modoPedido = $state ? app(\App\Services\ServicioModoConversacionPedido::class) : null;

        return response()->json([
            'mode' => ($state && $state->requires_human) ? 'human' : 'bot',
            'is_auto_escalated' => $state ? (bool) $state->is_auto_escalated : false,
            'asesor_post_pedido' => $state && $modoPedido ? $modoPedido->tieneAsesorPostPedido($state) : false,
        ]);
    }

    public function validatePayment(string $phone): JsonResponse
    {
        $state = ConversationState::where('phone_number', $phone)->first();
        if (! $state) {
            return response()->json(['message' => 'Conversación no encontrada'], 404);
        }

        if (! EtapaVenta::esValidacionPago($state)) {
            return response()->json(['message' => 'No hay un pago pendiente de validación en esta conversación'], 422);
        }

        $ctx = $state->context ?? [];
        $orderId = (int) ($ctx['ultimo_pedido_id'] ?? $ctx['last_order_id'] ?? 0);
        if ($orderId > 0) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        app(\App\Ventas\MaquinaEstados\MaquinaEstadosVentas::class)->finalizarValidacionPago($state->fresh());
        $state->refresh();

        $resumeText = (string) config(
            'flujo_ventas.mensaje_pago_aprobado',
            'Tu pedido ha sido confirmado exitosamente 💖 Muy pronto estaremos coordinando la entrega de tu compra.'
        );
        if ($orderId > 0) {
            $pedidoMsg = str_replace(
                '{pedido}',
                (string) $orderId,
                (string) config('copy_ventas.pedido_confirmado', $resumeText)
            );
            if ($pedidoMsg !== '') {
                $resumeText = $pedidoMsg;
            }
        }

        $this->sendAutomatedBotReply($state, $resumeText);

        app(\App\Services\ServicioModoConversacionPedido::class)
            ->activarHumanoTrasConfirmacion($state->fresh(), $orderId > 0 ? $orderId : null);

        return response()->json([
            'message' => 'Pago validado. Confirma al cliente y continúa en modo humano.',
            'mode' => 'human',
            'requires_human' => true,
            'is_auto_escalated' => false,
            'asesor_post_pedido' => true,
            'order_id' => $orderId > 0 ? $orderId : null,
            'bot_reply_preview' => mb_substr($resumeText, 0, 120),
        ]);
    }

    public function setMode(Request $request, string $phone): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:bot,human',
        ]);

        $mode = $validated['mode'];
        $requiresHuman = ($mode === 'human');

        $state = ConversationState::where('phone_number', $phone)->first();
        $paymentValidationResumed = false;
        if ($state) {
            $ctx = $state->context ?? [];
            if (! $requiresHuman) {
                unset(
                    $ctx[\App\Services\ServicioModoConversacionPedido::CTX_ASESOR_POST_PEDIDO],
                    $ctx['asesor_post_pedido_order_id'],
                );
            }

            $state->update([
                'context' => $ctx,
                'requires_human' => $requiresHuman,
                'is_auto_escalated' => false,
                'last_human_activity_at' => $requiresHuman ? now() : null,
            ]);

            if (! $requiresHuman) {
                $this->requeueUnprocessedIncomingMessages($phone);
            }

            if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                try {
                    broadcast(new \App\Events\ConversationModeChanged(
                        $phone,
                        $mode,
                        false,
                        ! $requiresHuman ? false : app(\App\Services\ServicioModoConversacionPedido::class)->tieneAsesorPostPedido($state->fresh()),
                    ))->toOthers();
                } catch (\Exception $e) {
                    \Log::error('setMode broadcast: '.$e->getMessage());
                }
            }
        }

        return response()->json([
            'message' => 'Conversation mode updated',
            'mode' => $mode,
            'requires_human' => $requiresHuman,
            'is_auto_escalated' => false,
            'payment_validation_resumed' => $paymentValidationResumed,
        ]);
    }

    protected function sendAutomatedBotReply(ConversationState $state, string $text): void
    {
        if (trim($text) === '') {
            return;
        }

        $customer = Customer::find($state->customer_id);
        $message = Message::create([
            'message_id' => 'temp_'.uniqid(),
            'phone_number' => $state->phone_number,
            'customer_id' => $state->customer_id,
            'conversation_state_id' => $state->id,
            'customer_name' => $customer?->name,
            'content' => $text,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => ['source' => 'payment_validation_resume'],
        ]);

        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new MessageReceived($message))->toOthers();
            } catch (\Exception $e) {
                \Log::error('Error broadcasting automated bot reply: '.$e->getMessage());
            }
        }

        SendWhatsappMessageJob::dispatch($message);
    }

    /**
     * Reprocesa mensajes entrantes que no recibieron respuesta del bot (p. ej. tras volver a modo bot).
     */
    protected function requeueUnprocessedIncomingMessages(string $phone): void
    {
        $messages = Message::where('phone_number', $phone)
            ->where('direction', 'incoming')
            ->whereIn('status', ['sent', 'delivered', 'read', 'received'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($messages as $message) {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            if (! empty($metadata['bot_processed_at'])) {
                continue;
            }

            $imageUrl = $metadata['image_url'] ?? null;
            \Log::info('RomaSyncController: Re-queueing unprocessed inbound after bot mode', [
                'phone' => $phone,
                'message_id' => $message->id,
            ]);
            ProcessIncomingMessageJob::dispatch($message, $imageUrl);
        }
    }
}
