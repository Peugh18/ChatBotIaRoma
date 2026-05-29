<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\IntentTranslatorService;
use App\Services\ResponseSanitizer;
use App\Services\ToolExecutorService;
use App\Services\PromptBuilder;
use App\Services\LlmService;
use App\Models\ConversationState;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BotTest extends TestCase
{
    use RefreshDatabase;

    public function test_intent_translator_translates_legacy_and_new_whatsapp_button_replies_correctly()
    {
        $translator = new IntentTranslatorService();

        // Texto plano sin ID interactivo se conserva tal cual
        $this->assertEquals('1', $translator->translate('1'));
        $this->assertEquals('Cualquier mensaje', $translator->translate('Cualquier mensaje'));

        // Botones fijos del flujo comercial
        $this->assertEquals('Quiero hablar con un asesor humano', $translator->translate('Asesor', ['interactive' => ['id' => 'escalate_human']]));
        $this->assertEquals('sí continuar con el pago', $translator->translate('Pagar', ['interactive' => ['id' => 'proceed_payment']]));
        $this->assertEquals('shalom lima', $translator->translate('Lima', ['interactive' => ['id' => 'shalom_lima']]));

        // 3. Probar botones dinámicos con regex
        $this->assertEquals('Quiero ver la foto del vestido ID 42', $translator->translate('Ver foto', ['interactive' => ['id' => 'view_img_42']]));
        $this->assertEquals('Quiero consultar stock del vestido ID 15 en color rojo', $translator->translate('Ver Stock', ['interactive' => ['id' => 'check_stock_15_rojo']]));
        $this->assertEquals('pick_product_99', $translator->translate('Comprar', ['interactive' => ['id' => 'buy_product_99']]));
    }

    public function test_response_sanitizer_removes_technical_garbage_and_llm_function_blocks_correctly()
    {
        // 1. Probar detección de basura
        $garbageJson = '{"name": "get_products", "arguments": {"query": "vestidos"}}';
        $garbageCode = 'get_products("noche")';
        $normalText = 'Hola hermosa, ¿cómo estás? 😊';

        $this->assertTrue(ResponseSanitizer::hasTechnicalGarbage($garbageJson));
        $this->assertTrue(ResponseSanitizer::hasTechnicalGarbage($garbageCode));
        $this->assertFalse(ResponseSanitizer::hasTechnicalGarbage($normalText));

        // 2. Probar sanitización de texto con llamadas incrustadas
        $dirtyResponse = "Te recomiendo este vestido. send_product_image(12) ¡Te va a encantar!";
        $this->assertEquals("Te recomiendo este vestido. ¡Te va a encantar!", ResponseSanitizer::sanitize($dirtyResponse));

        $dirtyButtons = "Aquí tienes. send_interactive_buttons('Cuerpo', [])";
        $this->assertEquals("Aquí tienes.", ResponseSanitizer::sanitize($dirtyButtons));
    }

    public function test_prompt_builder_contains_profile_and_cart_reference()
    {
        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [
                'current_product_name' => 'Vestido Gala Especial',
                'current_color' => 'Rojo',
                'current_size' => 'M',
                'last_shown_products' => [
                    ['id' => 12, 'name' => 'Vestido Gala Especial', 'final_price' => 150.00, 'has_stock' => true]
                ]
            ],
        ]);

        $prompt = app(PromptBuilder::class)->buildSystemPrompt($state, [
            'name' => 'María',
            'total_spent' => 300.00,
            'notes' => 'Le encantan los vestidos rojos.'
        ]);

        $this->assertStringContainsString('María', $prompt);
        $this->assertStringContainsString('51999999999', $prompt);
        $this->assertStringContainsString('Vestido Gala Especial', $prompt);
        $this->assertStringContainsString('Rojo', $prompt);
        $this->assertStringContainsString('M', $prompt);
        $this->assertStringContainsString('Le encantan los vestidos rojos.', $prompt);
        $this->assertStringContainsString('<contrato>', $prompt);
        $this->assertStringContainsString('TOOL-FIRST', $prompt);
        $this->assertStringContainsString('get_products', $prompt);
    }

    public function test_tool_executor_service_get_products_tokenizes_terms_properly()
    {
        $category = Category::create(['name' => 'Vestido', 'slug' => 'vestido']);
        
        $product = Product::create([
            'name' => 'Mariela Fiesta',
            'description' => 'Hermoso vestido de gala',
            'price' => 120.00,
            'discount' => 20.00,
            'category_id' => $category->id,
            'tags_ia' => ['graduación'],
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Rojo',
            'sizes_stock' => ['S' => 2, 'M' => 1],
        ]);

        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $executor = app(ToolExecutorService::class);
        $result = $executor->executeGetProducts($state, 'vestidos de gala Mariela');

        $this->assertEquals(1, $result['count']);
        $this->assertEquals('Mariela Fiesta', $result['products'][0]['name']);
        $this->assertEquals(100.00, $result['products'][0]['final_price']);
    }

    public function test_webhook_status_update_does_not_convert_outgoing_message_to_incoming()
    {
        \Illuminate\Support\Facades\Queue::fake();

        // 1. Configurar token de sincronización
        config(['services.roma.token' => 'roma_sync_secret_2026']);

        // 2. Crear un mensaje saliente (outgoing) en la base de datos
        $customer = \App\Models\Customer::create([
            'phone_number' => '51959166911',
            'name' => 'Cliente Test',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $state = ConversationState::create([
            'phone_number' => '51959166911',
            'customer_id' => $customer->id,
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $message = \App\Models\Message::create([
            'message_id' => 'wamid.test12345',
            'phone_number' => '51959166911',
            'customer_id' => $customer->id,
            'conversation_state_id' => $state->id,
            'content' => 'Hola cliente, ¿cómo estás?',
            'direction' => 'outgoing',
            'status' => 'pending',
            'whatsapp_timestamp' => now(),
        ]);

        // 3. Simular webhook de status update del roma-api (con direction: inbound y status: delivered)
        $payload = [
            'wa_id' => 'wamid.test12345',
            'sender_phone' => '51959166911',
            'message_body' => '[non-text]',
            'direction' => 'inbound',
            'status' => 'delivered',
            'timestamp' => now()->toIso8601String(),
        ];

        $response = $this->withHeaders([
            'X-Roma-Sync-Token' => 'roma_sync_secret_2026'
        ])->postJson('/api/roma/messages', $payload);

        $response->assertStatus(200);

        // 4. Verificar que el mensaje NO cambió su dirección a incoming y mantiene su contenido original
        $messageFresh = $message->fresh();
        $this->assertEquals('outgoing', $messageFresh->direction);
        $this->assertEquals('Hola cliente, ¿cómo estás?', $messageFresh->content);
        $this->assertEquals('delivered', $messageFresh->status);

        // 5. Verificar que NO se encoló ningún trabajo de procesamiento para este mensaje
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\ProcessIncomingMessageJob::class);
    }

    public function test_interactive_buttons_return_early_and_prevent_recursion()
    {
        $toolExecutor = $this->createMock(\App\Services\ToolExecutorService::class);
        $promptBuilder = $this->createMock(\App\Services\PromptBuilder::class);
        
        $state = ConversationState::create([
            'phone_number' => '51999999999',
            'current_state' => 'greeting',
            'context' => [],
        ]);

        $toolExecutor->expects($this->once())
            ->method('executeSendInteractiveButtons')
            ->willReturn(['success' => true]);

        $llmService = new LlmService($toolExecutor, $promptBuilder);
        
        $context = [
            'state' => $state,
            'user_message' => 'hola',
            'has_called_tools_this_turn' => false,
            'conversation_context' => [],
            'customer_context' => [],
        ];
        
        \Illuminate\Support\Facades\Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [
                                [
                                    'id' => 'call_xyz',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'send_interactive_buttons',
                                        'arguments' => json_encode([
                                            'body' => 'Hola hermosa, elige una opción:',
                                            'buttons' => [
                                                ['id' => 'btn_1', 'title' => 'Opción 1']
                                            ]
                                        ])
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $llmService->callGroq($context, null, [], 0);

        $this->assertEquals('Hola hermosa, elige una opción:', $result);
        $this->assertTrue($context['has_called_tools_this_turn']);
    }
}
