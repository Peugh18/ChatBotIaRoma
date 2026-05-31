<?php

namespace App\Jobs;

use App\Events\HumanEscalation;
use App\Events\MessageReceived;
use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Models\Message;
use App\Services\DeterministicBotService;
use App\Services\ProductMediaService;
use App\Services\ToolExecutorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    protected Message $message;

    protected ?string $imageUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message, ?string $imageUrl = null)
    {
        $this->message = $message;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Execute the job.
     */
    /**
     * Palabras clave que indican que el cliente quiere hablar con un humano.
     * Se usan con word boundaries (\b) para evitar falsos positivos.
     * Ej: "agentemente" NO debe matchear "agente".
     */
    protected array $humanEscalationKeywords = [
        'asesor', 'humano', 'vendedor', 'agente',
        'persona real', 'hablar con alguien', 'quiero hablar con',
        'no quiero bot', 'pasame con', 'pásame con', 'derivame', 'derívame',
        'atencion personalizada', 'atención personalizada',
        'atender por una persona', 'que me atienda alguien',
    ];

    /**
     * Verifica si el mensaje indica que el cliente quiere hablar con un humano.
     * Usa word boundaries para evitar falsos positivos.
     */
    protected function clientWantsHuman(string $message): bool
    {
        $message = mb_strtolower($message);
        foreach ($this->humanEscalationKeywords as $keyword) {
            $kw = mb_strtolower($keyword);
            // Si el keyword tiene espacios (frase), usar str_contains; si es palabra única, usar word boundary
            if (str_contains($kw, ' ')) {
                if (str_contains($message, $kw)) {
                    return true;
                }
            } else {
                $pattern = '/\b'.preg_quote($kw, '/').'\b/u';
                if (preg_match($pattern, $message)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * M3: Detecta si el mensaje es un duplicado reciente del cliente.
     * Si recibimos el mismo texto en los últimos 60 segundos, lo ignoramos.
     */
    protected function isDuplicateMessage(?ConversationState $state): bool
    {
        if (! $state) {
            return false;
        }

        $lastMsg = Message::where('phone_number', $this->message->phone_number)
            ->where('direction', 'incoming')
            ->where('id', '!=', $this->message->id)
            ->orderByDesc('id')
            ->first();

        if (! $lastMsg) {
            return false;
        }

        $sameContent = trim(mb_strtolower($lastMsg->content)) === trim(mb_strtolower($this->message->content));
        $within60s = $lastMsg->created_at && $lastMsg->created_at->gt(now()->subSeconds(60));

        if (! $sameContent || ! $within60s) {
            return false;
        }

        // Solo ignorar si el bot YA respondió al mensaje anterior idéntico (evita doble respuesta por webhook duplicado)
        return Message::where('phone_number', $this->message->phone_number)
            ->where('direction', 'outgoing')
            ->where('created_at', '>=', $lastMsg->created_at)
            ->where('created_at', '<=', $this->message->created_at)
            ->exists();
    }

    protected function markIncomingAsBotProcessed(): void
    {
        $metadata = $this->message->metadata ?? [];
        $metadata['bot_processed_at'] = now()->toDateTimeString();
        $this->message->update(['metadata' => $metadata]);
    }

    /**
     * Envía la foto de producto en un mensaje aparte (más fiable que imagen + botones en uno).
     */
    protected function dispatchPendingProductImageIfNeeded(
        ?ConversationState $conversationState,
        ProductMediaService $media
    ): void {
        if (! $conversationState) {
            return;
        }

        $ctx = $conversationState->context ?? [];
        $queue = $ctx['pending_image_queue'] ?? [];

        if (is_array($queue) && $queue !== []) {
            foreach ($queue as $item) {
                $this->dispatchOneProductImage(
                    $conversationState,
                    $media,
                    (string) ($item['url'] ?? ''),
                    (string) ($item['caption'] ?? '📸')
                );
            }
            unset($ctx['pending_image_queue']);
            $conversationState->context = $ctx;
            $conversationState->save();
            $conversationState->refresh();
            $ctx = $conversationState->context ?? [];
        }

        if (empty($ctx['pending_image_url'])) {
            return;
        }

        $pendingImage = $media->resolveWhatsappSendUrl((string) $ctx['pending_image_url']);
        $caption = (string) ($ctx['pending_image_caption'] ?? '📸');
        $this->dispatchOneProductImage($conversationState, $media, $pendingImage, $caption);

        unset($ctx['pending_image_url'], $ctx['pending_image_caption']);
        $conversationState->context = $ctx;
        $conversationState->save();
    }

    protected function dispatchOneProductImage(
        ConversationState $conversationState,
        ProductMediaService $media,
        string $imageUrl,
        string $caption
    ): void {
        if ($imageUrl === '') {
            return;
        }

        $pendingImage = $media->resolveWhatsappSendUrl($imageUrl);

        if (! $media->isUrlReachableByMeta($pendingImage)) {
            Log::warning('ProcessIncomingMessageJob: product image skipped (no public HTTPS URL)', [
                'phone' => $this->message->phone_number,
                'url' => $pendingImage,
                'hint' => 'Configura PUBLIC_APP_URL con tu ngrok HTTPS (puerto 8000)',
            ]);

            return;
        }

        $imageMessage = Message::create([
            'message_id' => 'temp_'.uniqid(),
            'phone_number' => $this->message->phone_number,
            'customer_id' => $this->message->customer_id,
            'conversation_state_id' => $this->message->conversation_state_id,
            'customer_name' => $this->message->customer_name,
            'content' => $caption,
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
            'metadata' => [
                'type' => 'image',
                'image_url' => $pendingImage,
            ],
        ]);

        if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
            try {
                broadcast(new MessageReceived($imageMessage))->toOthers();
            } catch (\Exception $e) {
                Log::error('ProcessIncomingMessageJob: Error broadcasting image reply: '.$e->getMessage());
            }
        }

        SendWhatsappMessageJob::dispatch($imageMessage);
    }

    /**
     * M2: Detecta si el mensaje es un saludo simple para responder con plantilla
     * sin llamar al LLM (ahorra ~80% tokens en saludos).
     */
    protected function isSimpleGreeting(string $message): bool
    {
        $msg = trim(mb_strtolower($message));
        $greetings = [
            'hola', 'holi', 'holaaa', 'buenas', 'buenos dias', 'buenos días',
            'buenas tardes', 'buenas noches', 'hi', 'hello', 'hey',
            'que tal', 'qué tal', 'inicio', 'empezar', 'info', 'información', 'informacion',
        ];

        // Solo si el mensaje es CORTO y matchea un saludo
        if (mb_strlen($msg) > 25) {
            return false;
        }

        foreach ($greetings as $g) {
            if ($msg === $g || $msg === $g.'!' || $msg === $g.'.') {
                return true;
            }
        }

        return false;
    }

    /**
     * M2: Genera saludo hardcoded sin invocar LLM.
     */
    protected function buildHardcodedGreeting(): string
    {
        return "¡Hola hermosa! 💕 Soy Roma de Vestidos Roma ✨\n\n".
               "Te cuento qué podemos hacer hoy:\n".
               "1️⃣ Ver vestidos de noche / fiesta 🌟\n".
               "2️⃣ Ver vestidos casuales / oficina 👍\n".
               "3️⃣ Solo estoy curioseando 👀\n\n".
               'Respóndeme con el número o cuéntame qué buscas 📝';
    }

    public function handle(DeterministicBotService $botService, ProductMediaService $media): void
    {
        Log::info('ProcessIncomingMessageJob: Started processing message', [
            'message_id' => $this->message->id,
            'phone' => $this->message->phone_number,
        ]);

        // Obtener estado de conversación
        $conversationState = ConversationState::where('phone_number', $this->message->phone_number)->first();
        if ($conversationState) {
            $conversationState->update(['last_reminder_sent' => 'none']);
        }

        // M3: Deduplicación - ignorar mensajes idénticos en últimos 60s
        if ($this->isDuplicateMessage($conversationState)) {
            Log::info('ProcessIncomingMessageJob: Duplicate message detected, skipping', [
                'phone' => $this->message->phone_number,
                'content' => substr($this->message->content, 0, 50),
            ]);
            $this->markIncomingAsBotProcessed();

            return;
        }

        // 1. Modo humano: si el asesor no escribe hace 15+ min, el bot vuelve a atender
        if ($conversationState && $conversationState->requires_human) {
            $humanInactive = $conversationState->last_human_activity_at === null
                || $conversationState->last_human_activity_at->lt(now()->subMinutes(15));

            if ($humanInactive) {
                Log::info('ProcessIncomingMessageJob: Human mode inactive 15+ min, reactivating bot', [
                    'phone' => $this->message->phone_number,
                ]);

                $conversationState->update([
                    'requires_human' => false,
                    'is_auto_escalated' => false,
                ]);
            } else {
                Log::info('ProcessIncomingMessageJob: Conversation in human mode, skipping bot', [
                    'phone' => $this->message->phone_number,
                ]);

                return;
            }
        }

        // 2. Detectar si el cliente pide humano explícitamente
        if ($this->clientWantsHuman($this->message->content)) {
            Log::info('ProcessIncomingMessageJob: Client requested human, escalating', [
                'phone' => $this->message->phone_number,
            ]);

            if ($conversationState) {
                app(ToolExecutorService::class)->executeEscalateToHuman(
                    $conversationState,
                    'Cliente solicitó asesor humano'
                );
            }

            broadcast(new HumanEscalation($this->message))->toOthers();

            return;
        }

        try {
            $metadata = [];
            $incomingMeta = is_array($this->message->metadata) ? $this->message->metadata : null;
            $imageUrl = $this->imageUrl;
            if (($imageUrl === null || $imageUrl === '') && is_array($incomingMeta)) {
                $imageUrl = $incomingMeta['image_url'] ?? null;
            }

            $result = $botService->process(
                $this->message->phone_number,
                $this->message->content,
                $imageUrl,
                $incomingMeta
            );
            $botResponse = $result['text'] ?? '';
            if (is_array($result['metadata'] ?? null)) {
                $metadata = $result['metadata'];
            }

            Log::info('ProcessIncomingMessageJob: Bot process result', [
                'has_response' => ! empty($botResponse),
                'response_snippet' => ! empty($botResponse) ? substr($botResponse, 0, 50) : null,
            ]);

            if (! empty($metadata['trigger_human_escalation'])) {
                broadcast(new HumanEscalation($this->message))->toOthers();
            }

            if ($botResponse) {
                // Detectar si el bot devolvió el mensaje de escalación (no pudo responder)
                $settings = BotSetting::first();
                if ($settings && $botResponse === $settings->escalation_message) {
                    Log::info('ProcessIncomingMessageJob: Bot returned escalation message, marking human mode', [
                        'phone' => $this->message->phone_number,
                    ]);

                    if ($conversationState) {
                        $conversationState->update([
                            'requires_human' => true,
                            'is_auto_escalated' => true,
                            'last_human_activity_at' => now(),
                        ]);
                    }

                    broadcast(new HumanEscalation($this->message))->toOthers();
                }

                // B3: Imagen de producto — enviar en mensaje separado (WhatsApp no muestra bien imagen + botones juntos)
                $conversationState = ConversationState::where('phone_number', $this->message->phone_number)->first();
                $this->dispatchPendingProductImageIfNeeded($conversationState, $media);

                // Extraer interactivos pendientes del contexto si existen
                $conversationState = ConversationState::where('phone_number', $this->message->phone_number)->first();
                if ($conversationState && isset($conversationState->context['pending_interactive'])) {
                    $pendingInteractive = $conversationState->context['pending_interactive'];
                    $metadata['type'] = 'interactive';
                    $metadata['interactive'] = $pendingInteractive['interactive'];

                    $stateContext = $conversationState->context;
                    unset($stateContext['pending_interactive']);
                    $conversationState->context = $stateContext;
                    $conversationState->save();
                }

                // 1. Guardar el mensaje del bot en estado "pending" con relaciones
                $sentMessage = Message::create([
                    'message_id' => 'temp_'.uniqid(),
                    'phone_number' => $this->message->phone_number,
                    'customer_id' => $this->message->customer_id,
                    'conversation_state_id' => $this->message->conversation_state_id,
                    'customer_name' => $this->message->customer_name,
                    'content' => $botResponse,
                    'direction' => 'outgoing',
                    'status' => 'pending',
                    'whatsapp_timestamp' => now(),
                    'metadata' => $metadata,
                ]);

                // 2. Disparar evento Pusher para actualizar el chat del CRM en tiempo real
                if (env('BROADCAST_CONNECTION') === 'pusher' && env('PUSHER_APP_ID')) {
                    try {
                        broadcast(new MessageReceived($sentMessage))->toOthers();
                        Log::info('ProcessIncomingMessageJob: Pending reply broadcasted');
                    } catch (\Exception $e) {
                        Log::error('ProcessIncomingMessageJob: Error broadcasting pending reply: '.$e->getMessage());
                    }
                }

                // 3. Despachar el Job dedicado a realizar el envío físico
                SendWhatsappMessageJob::dispatch($sentMessage);
            }

            $this->markIncomingAsBotProcessed();
        } catch (\Throwable $e) {
            Log::error('ProcessIncomingMessageJob: Exception occurred', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
