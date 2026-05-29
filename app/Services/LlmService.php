<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\ConversationState;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    protected BotSetting $settings;
    protected ToolExecutorService $toolExecutor;
    protected PromptBuilder $promptBuilder;

    public function __construct(ToolExecutorService $toolExecutor, PromptBuilder $promptBuilder)
    {
        $this->settings = BotSetting::first() ?? BotSetting::create([
            'system_prompt' => 'Eres un asistente de ventas de Vestidos Roma.',
            'auto_reply_enabled' => true,
        ]);
        $this->toolExecutor = $toolExecutor;
        $this->promptBuilder = $promptBuilder;
    }

    public function getSettings(): BotSetting
    {
        return $this->settings;
    }

    /**
     * Bucle recursivo para resolver llamadas de herramientas con Groq
     */
    public function callGroq(array &$context, ?string $imageUrl = null, array $messages = [], int $iteration = 0, string $toolChoice = 'auto'): string
    {
        if ($iteration >= 3) {
            Log::warning('LlmService: Max iterations reached. Escalating.');
            if (isset($context['state'])) {
                $context['state']->update([
                    'requires_human' => true,
                    'is_auto_escalated' => true,
                    'last_human_activity_at' => now(),
                ]);
            }
            return $this->settings->escalation_message;
        }

        $model = $imageUrl ? $this->settings->model_vision : $this->settings->model_chat;

        if (empty($messages)) {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->promptBuilder->buildSystemPrompt($context['state'], $context['customer_context']),
                ],
            ];

            // Cargar historial de conversación
            if (!empty($context['conversation_context']['history'])) {
                foreach ($context['conversation_context']['history'] as $msg) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }

            // Agregar mensaje del usuario
            if ($imageUrl) {
                $messages[] = [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $context['user_message']],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                    ],
                ];
            } else {
                $messages[] = [
                    'role' => 'user',
                    'content' => $context['user_message'],
                ];
            }
        }

        try {
            $postData = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 450,
            ];

            if (!$imageUrl) {
                $dynamicTools = $this->getToolsDefinition();
                if (!empty($dynamicTools)) {
                    $postData['tools'] = $dynamicTools;
                    $postData['tool_choice'] = $toolChoice;
                    $postData['parallel_tool_calls'] = false;
                }
            }

            Log::info('LlmService: Requesting Groq completions', [
                'model' => $model,
                'iteration' => $iteration,
                'messages_count' => count($messages),
                'tool_choice' => is_array($toolChoice) ? json_encode($toolChoice) : $toolChoice,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->settings->groq_api_key,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', $postData);

            if (!$response->successful()) {
                Log::error('LlmService: Groq API Error', ['status' => $response->status(), 'body' => $response->body()]);

                if ($model !== 'llama-3.1-8b-instant') {
                    Log::warning('LlmService: Retrying with fallback llama-3.1-8b-instant');
                    $postData['model'] = 'llama-3.1-8b-instant';
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->settings->groq_api_key,
                        'Content-Type' => 'application/json',
                    ])->post('https://api.groq.com/openai/v1/chat/completions', $postData);

                    if (!$response->successful()) {
                        Log::error('LlmService: Fallback failed', ['status' => $response->status()]);
                        return $this->settings->escalation_message;
                    }
                } else {
                    return $this->settings->escalation_message;
                }
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;
            if (!$choice) {
                return $this->settings->escalation_message;
            }

            $assistantMessage = $choice['message'] ?? null;
            if (!$assistantMessage) {
                return $this->settings->escalation_message;
            }

            // Procesar llamadas a herramientas
            if (!empty($assistantMessage['tool_calls'])) {
                Log::info('LlmService: Executing tools requested by Groq', ['calls' => $assistantMessage['tool_calls']]);

                // Registrar en la lista de mensajes pasados al bot que hizo tool calls
                $context['has_called_tools_this_turn'] = true;
                $messages[] = $assistantMessage;

                $interactiveBody = null;
                $shouldEscalate = false;

                foreach ($assistantMessage['tool_calls'] as $toolCall) {
                    $toolId = $toolCall['id'];
                    $funcName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'], true) ?? [];

                    $toolResult = null;

                    try {
                        switch ($funcName) {
                            case 'get_products':
                                $toolResult = $this->toolExecutor->executeGetProducts($context['state'], $arguments['query'], $arguments['color'] ?? null);
                                break;
                            case 'check_stock':
                                $toolResult = $this->toolExecutor->executeCheckStock($context['state'], (int)$arguments['product_id'], $arguments['color']);
                                break;
                            case 'get_delivery_cost':
                                $toolResult = $this->toolExecutor->executeGetDeliveryCost($arguments['district']);
                                break;
                            case 'send_product_image':
                                $toolResult = $this->toolExecutor->executeSendProductImage($context['state'], (int)$arguments['product_id'], $arguments['color'] ?? null);
                                break;
                            case 'create_order':
                                $toolResult = $this->toolExecutor->executeCreateOrder($context['state'], $arguments['items'], $arguments['shipping_method'], $arguments['payment_method'], $arguments['district'] ?? null, $arguments['address'] ?? null);
                                break;
                            case 'escalate_to_human':
                                $toolResult = $this->toolExecutor->executeEscalateToHuman($context['state'], $arguments['reason']);
                                $shouldEscalate = true;
                                break;
                            case 'send_interactive_buttons':
                                $toolResult = $this->toolExecutor->executeSendInteractiveButtons($context['state'], $arguments['body'], $arguments['buttons'], $arguments['footer'] ?? null);
                                $interactiveBody = $arguments['body'] ?? '';
                                break;
                            case 'send_interactive_list':
                                $toolResult = $this->toolExecutor->executeSendInteractiveList($context['state'], $arguments['body'], $arguments['button_text'], $arguments['sections'], $arguments['footer'] ?? null);
                                $interactiveBody = $arguments['body'] ?? '';
                                break;
                            case 'get_customer_profile':
                                $toolResult = $this->toolExecutor->executeGetCustomerProfile($context['state']);
                                break;
                            case 'get_order_status':
                                $toolResult = $this->toolExecutor->executeGetOrderStatus($context['state'], $arguments['order_id'] ?? null);
                                break;
                            default:
                                $toolResult = ['error' => 'Herramienta no implementada'];
                                break;
                        }
                    } catch (\Exception $ex) {
                        Log::error("LlmService: Exception in {$funcName}", ['error' => $ex->getMessage()]);
                        $toolResult = ['error' => 'Error al ejecutar herramienta: ' . $ex->getMessage()];
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolId,
                        'name' => $funcName,
                        'content' => json_encode($toolResult),
                    ];
                }

                if ($shouldEscalate) {
                    return $this->settings->escalation_message;
                }

                if ($interactiveBody !== null) {
                    return $interactiveBody;
                }

                // Llamada recursiva pasando la referencia de si usó herramientas
                return $this->callGroq($context, $imageUrl, $messages, $iteration + 1);
            }

            return $assistantMessage['content'] ?? '';

        } catch (\Exception $e) {
            Log::error('LlmService: Exception in callGroq', ['error' => $e->getMessage()]);
            return $this->settings->escalation_message;
        }
    }

    /**
     * Definición completa de herramientas.
     */
    protected function getToolsDefinition(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_products',
                    'description' => 'Busca vestidos en el catálogo por nombre, descripción o palabras clave (ej: "rojo", "seda"). Devuelve variante, precios e imagen.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Término de búsqueda del vestido o categoría.',
                            ],
                            'color' => [
                                'type' => 'string',
                                'description' => 'Color del vestido (opcional).',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_stock',
                    'description' => 'Verifica el stock por talla y color disponible para un ID de producto.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID numérico del producto.',
                            ],
                            'color' => [
                                'type' => 'string',
                                'description' => 'Color a verificar.',
                            ],
                        ],
                        'required' => ['product_id', 'color'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_delivery_cost',
                    'description' => 'Obtiene tarifas y tiempos estimados de envío para Shalom o Motorizado en un distrito de Lima.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'district' => [
                                'type' => 'string',
                                'description' => 'Distrito de entrega en Lima (ej. "Miraflores", "Surco").',
                            ],
                        ],
                        'required' => ['district'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_product_image',
                    'description' => 'Ubica la imagen del producto y la encola para ser enviada adjunta al cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID numérico del producto.',
                            ],
                            'color' => [
                                'type' => 'string',
                                'description' => 'Color del vestido.',
                            ],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_order',
                    'description' => 'Crea el pedido del cliente en la BD tras su confirmación explícita de compra.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_id' => ['type' => 'integer'],
                                        'color' => ['type' => 'string'],
                                        'size' => ['type' => 'string'],
                                        'qty' => ['type' => 'integer', 'default' => 1],
                                    ],
                                    'required' => ['product_id', 'color', 'size'],
                                ],
                            ],
                            'shipping_method' => [
                                'type' => 'string',
                                'enum' => ['shalom', 'motorizado', 'none'],
                            ],
                            'payment_method' => [
                                'type' => 'string',
                                'enum' => ['yape', 'card', 'link', 'cash'],
                            ],
                            'district' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                        ],
                        'required' => ['items', 'shipping_method', 'payment_method'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'escalate_to_human',
                    'description' => 'Deriva el chat a un agente humano ante quejas o si es solicitado por el usuario.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Causa de la derivación.',
                            ],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_customer_profile',
                    'description' => 'Obtiene datos CRM del cliente actual (segmento, notas, historial de compras y último pedido).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_order_status',
                    'description' => 'Consulta estado de pedido del cliente actual por ID (opcional) o el más reciente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => [
                                'type' => 'integer',
                                'description' => 'ID del pedido a consultar (opcional).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_interactive_buttons',
                    'description' => 'Envía hasta 3 botones interactivos de WhatsApp al usuario junto con un mensaje.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'body' => [
                                'type' => 'string',
                                'description' => 'Contenido principal del mensaje.',
                            ],
                            'buttons' => [
                                'type' => 'array',
                                'description' => 'Arreglo de botones (máx 3).',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => [
                                            'type' => 'string',
                                            'description' => 'ID de retorno cuando hagan clic (ej: "opt_fiesta").',
                                        ],
                                        'title' => [
                                            'type' => 'string',
                                            'description' => 'Texto visible en el botón (máx 20 caracteres).',
                                        ],
                                    ],
                                    'required' => ['id', 'title'],
                                ],
                            ],
                            'footer' => [
                                'type' => 'string',
                                'description' => 'Texto de pie de página (opcional).',
                            ],
                        ],
                        'required' => ['body', 'buttons'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'send_interactive_list',
                    'description' => 'Envía un menú de lista desplegable de WhatsApp (de hasta 10 opciones) al cliente.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'body' => [
                                'type' => 'string',
                                'description' => 'Cuerpo de texto explicativo.',
                            ],
                            'button_text' => [
                                'type' => 'string',
                                'description' => 'Texto visible en el botón para abrir el menú (máx 20 caracteres).',
                            ],
                            'sections' => [
                                'type' => 'array',
                                'description' => 'Secciones de la lista.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => [
                                            'type' => 'string',
                                            'description' => 'Título de la sección.',
                                        ],
                                        'rows' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => [
                                                        'type' => 'string',
                                                        'description' => 'ID de la opción seleccionada.',
                                                    ],
                                                    'title' => [
                                                        'type' => 'string',
                                                        'description' => 'Título principal de la fila (máx 24 caracteres).',
                                                    ],
                                                    'description' => [
                                                        'type' => 'string',
                                                        'description' => 'Descripción corta opcional (máx 72 caracteres).',
                                                    ],
                                                ],
                                                'required' => ['id', 'title'],
                                            ],
                                        ],
                                    ],
                                    'required' => ['title', 'rows'],
                                ],
                            ],
                            'footer' => [
                                'type' => 'string',
                                'description' => 'Texto de pie de página opcional.',
                            ],
                        ],
                        'required' => ['body', 'button_text', 'sections'],
                    ],
                ],
            ],
        ];
    }
}
