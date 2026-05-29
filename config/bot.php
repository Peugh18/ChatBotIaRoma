<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot Runtime Flags
    |--------------------------------------------------------------------------
    |
    | enable_llm_fallback: Si está en false, el bot usa únicamente flujo
    | determinístico (reglas + BD) y nunca llama al LLM para fallback.
    |
    */
    'enable_llm_fallback' => env('BOT_ENABLE_LLM_FALLBACK', false),

    /*
    |--------------------------------------------------------------------------
    | Prompt contract (system prompt del LLM)
    |--------------------------------------------------------------------------
    */
    'prompt' => [
        'default_persona' => 'Eres Roma, asesora de ventas de Vestidos Roma.',
        'max_response_lines' => (int) env('BOT_MAX_RESPONSE_LINES', 3),
        'max_interactive_buttons' => 3,
        'max_interactive_list_rows' => 10,
    ],

    'tools' => [
        'get_products',
        'check_stock',
        'get_delivery_cost',
        'get_customer_profile',
        'get_order_status',
        'send_product_image',
        'send_interactive_buttons',
        'send_interactive_list',
        'create_order',
        'escalate_to_human',
    ],
];

