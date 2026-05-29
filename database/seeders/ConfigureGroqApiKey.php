<?php

namespace Database\Seeders;

use App\Models\BotSetting;
use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class ConfigureGroqApiKey extends Seeder
{
    public function run(): void
    {
        $apiKey = env('GROQ_API_KEY');

        $settings = BotSetting::first();

        if ($settings) {
            $settings->update([
                'groq_api_key' => $apiKey,
                'auto_reply_enabled' => true,
                'model_chat' => env('GROQ_MODEL_CHAT', 'llama-3.3-70b-versatile'),
                'model_vision' => env('GROQ_MODEL_VISION', 'meta-llama/llama-4-scout-17b-16e-instruct'),
            ]);
            echo "✅ BotSetting actualizado desde variables de entorno\n";
        } else {
            BotSetting::create([
                'system_prompt' => 'Eres una asesora de ventas enfocada en cierre. Detecta intención de compra y guía al cliente con datos reales del catálogo.',
                'welcome_message' => '¡Hola! ¿Qué vestido buscas hoy? Puedes enviarme foto o nombre del modelo.',
                'reminder_3min_message' => 'Hermosa nos confirmas si vas a realizar el pedido por favor',
                'reminder_15min_message' => 'Muchas gracias hermosa, cualquier cosita si te animas más tarde nos escribes. Que tengas un gran día 🤗🤗',
                'escalation_message' => 'Voy a realizar la consulta a un agente especializado y en breve le brindamos una respuesta.',
                'groq_api_key' => $apiKey,
                'auto_reply_enabled' => true,
                'model_chat' => env('GROQ_MODEL_CHAT', 'llama-3.3-70b-versatile'),
                'model_vision' => env('GROQ_MODEL_VISION', 'meta-llama/llama-4-scout-17b-16e-instruct'),
                'reminder_3min_seconds' => 180,
                'reminder_15min_seconds' => 900,
            ]);
            echo "✅ BotSetting creado desde variables de entorno\n";
        }

        if (CompanySetting::count() === 0) {
            CompanySetting::create([
                'company_name' => 'Vestidos Roma',
                'yape_number' => '912874650',
                'yape_name' => 'Solange Llantoy',
                'address' => 'Gamarra, La Victoria, Lima, Perú',
                'business_hours' => ['open' => '10:00', 'close' => '20:00'],
                'social_networks' => [
                    'instagram' => '@vestidos_roma',
                    'tiktok' => '@vestidosroma',
                ],
                'sales_tone' => 'cálido, cercano y vendedor',
                'sales_closing_cta' => '¿Te lo separo ahora?',
            ]);
            echo "✅ CompanySetting por defecto sembrado\n";
        }
    }
}
