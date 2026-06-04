# RomaCrm

CRM de ventas por WhatsApp para Roma: catálogo, chat en vivo, bot de ventas, pedidos y pipeline operativo.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL
- [roma-api](https://github.com/) (servicio WhatsApp) en ejecución
- Pusher (opcional, para chat en tiempo real)

## Desarrollo local

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
composer run dev
```

`composer run dev` levanta:

- Servidor Laravel (`php artisan serve`)
- Cola de jobs (`php artisan queue:listen --tries=5`) — **obligatorio** para respuestas del bot
- Vite (`npm run dev`)

### Scheduler (recordatorios y vuelta a modo bot)

En otra terminal:

```bash
php artisan schedule:work
```

O configura cron: `* * * * * php /ruta/artisan schedule:run`

## Variables de entorno clave

| Variable | Uso |
|----------|-----|
| `ROMA_API_URL` | URL de roma-api |
| `ROMA_SYNC_TOKEN` | Token `X-Roma-Sync-Token` (webhooks) |
| `ROMA_WEBHOOK_SECRET` | HMAC opcional |
| `BOT_ENABLE_LLM_FALLBACK` | `true` para fallback LLM (Groq) |
| `GROQ_API_KEY` | API Groq (también editable en `/bot-settings`) |
| `VOYAGE_API_KEY` | API Voyage AI para reconocimiento visual del catálogo (también editable en `/bot-settings`) |
| `CATALOG_VISION_ENABLED` | `true` para activar matching visual por embeddings Voyage |
| `CATALOG_VISION_MIN_SIMILARITY` | Umbral de similitud coseno (default: `0.72`) |
| `QUEUE_CONNECTION` | Usar `database` en desarrollo |
| `PUSHER_*` / `VITE_PUSHER_*` | Chat en tiempo real |

## Arquitectura del bot

```
WhatsApp → roma-api → POST /api/roma/messages
       → ProcessIncomingMessageJob
       → DeterministicBotService (+ SalesFlowService)
       → SendWhatsappMessageJob → roma-api → Meta
```

Documentación detallada: `.agents/skills/roma-sales-bot/SKILL.md`

**Contexto del proyecto (flujo, requisitos, prohibiciones):** [`docs/CONTEXTO_PROYECTO.md`](docs/CONTEXTO_PROYECTO.md) · [Guía rápida](docs/VESTIDOS_ROMA_GUIA.md)

## Reconocimiento visual (Voyage AI)

Cuando un cliente envía una foto en un live, el bot puede buscar por **similitud visual** usando embeddings Voyage multimodal:

1. `php artisan catalog:index-embeddings` — indexa fotos del catálogo
2. `php artisan catalog:index-embeddings --force` — re-indexa todo (necesario al migrar de otro proveedor)
3. `POST /api/test-embedding` — prueba que Voyage esté respondiendo

El matching usa **cosine similarity** contra un índice en MySQL (`product_variants.embedding`).
Si no hay API key Voyage o el score es menor al umbral (0.72), cae al fallback de Groq vision + búsqueda textual.

Requiere `VOYAGE_API_KEY` en `.env` o en `/bot-settings`. Obtén la key en [voyageai.com](https://www.voyageai.com/) (200M tokens + 150B píxeles gratis al registrarte).

## Pantallas principales

| Ruta | Descripción |
|------|-------------|
| `/dashboard` | Métricas de ventas |
| `/chat` | Conversaciones WhatsApp |
| `/pipeline` | Kanban de pedidos |
| `/customers` | Lista de clientes y enlace al chat |
| `/products` | Catálogo |
| `/bot-settings` | Personalidad y LLM |
| `/company-settings` | Yape, tono, horarios |

## API interna (sesión autenticada)

- `GET /api/orders` — pedidos
- `PUT /api/orders/{id}` — cambiar estado
- `GET /api/health` — salud del sistema (precios, pedidos)
- `GET /api/dashboard-stats` — dashboard

## Tests

```bash
php artisan test
```

## Troubleshooting

- **Bot no responde:** verificar que la cola esté corriendo.
- **Mensajes atascados en "enviando":** revisar `storage/logs/laravel.log` y token WhatsApp en roma-api.
- **Pipeline no mueve tarjetas:** recargar sesión; mutaciones requieren CSRF (incluido en `apiJson`).
