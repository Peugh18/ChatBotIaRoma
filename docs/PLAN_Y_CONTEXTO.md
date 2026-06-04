# Plan + contexto — RomaCrm / Vestidos Roma

**Documento vivo para el equipo y para agentes (Cursor).** Resume *qué es el proyecto*, *qué ya está hecho*, *qué falta* y *en qué orden seguir*.  
Detalle largo (requisitos, prohibiciones, flujos): [`CONTEXTO_PROYECTO.md`](./CONTEXTO_PROYECTO.md).

**Última actualización:** 2026-06-02 · Rama referencia: `main` · Repo: `ChatBotIaRoma` (RomaCrm)

---

## 1. Contexto en 30 segundos

| Qué | Dónde |
|-----|--------|
| Negocio | **Vestidos Roma** — vestidos por WhatsApp, soles, **Leidi** |
| CRM | Laravel + Vue — catálogo, chat, pipeline, bot |
| WhatsApp | **roma-api** (otra app Node) → webhook → RomaCrm |
| Bot ventas | `app/Ventas/` determinístico; copy en `config/copy_ventas.php` |
| Fotos vestido | **Voyage** embeddings (`VOYAGE_API_KEY`) + index catálogo |
| Humano | Validar Yape, handoff, **Por enviar** hasta **Entregado** |

**Regla de oro:** mejorar `app/Ventas/`, no duplicar flujos. No Meta directo desde CRM.

---

## 2. Arquitectura (no olvidar)

```
Cliente WA → Meta → roma-api (:3000, ngrok) → POST RomaCrm /api/roma/messages
                                              → queue ProcessIncomingMessageJob
                                              → OrquestadorVentas
                                              → queue SendWhatsappMessageJob → roma-api → Meta
```

| Máquina / URL | Rol |
|---------------|-----|
| PC CRM | Laravel `:8000`, `PUBLIC_APP_URL` (ngrok CRM) |
| PC webhook (o misma) | roma-api, `ROMA_API_URL`, media `/api/media/file/...` |
| Cola | `php artisan queue:listen` — **obligatoria** |
| Token Meta | Supabase `app_settings.meta_access_token` + roma-api |

---

## 3. Plan por fases (histórico)

| Fase | Entregable | Estado |
|------|------------|--------|
| 0–1 | Módulo Ventas, catálogo, carrito | ✅ |
| 2 | Similares, sin stock, descuento | ✅ |
| 3 | Match foto **Voyage** | ✅ |
| 4–5 | Checkout, Yape, validación CRM | ✅ |
| 6 | FAQ, recordatorios, reanudar | ✅ |
| 7 | Panel CRM, sedes Shalom, similares | ✅ |
| 8 | Paginación WA, dashboard pagos, `/bot-debug` | ✅ |
| **Post-V1** | Bandeja **Por enviar**, carrito limpio post-pago, nombre en match foto, banner post-pedido | ✅ en código |
| **Docs** | `docs/CONTEXTO_PROYECTO.md`, esta guía | ✅ pushed `main` |
| **ResNet** | Clasificador Python local | ❌ descartado (rama `PRobandoResnet` borrada) |

---

## 4. Plan activo — qué hacer ahora (orden sugerido)

### A. Operación / infra (antes de vender en serio)

- [ ] **`.env` CRM:** `VOYAGE_API_KEY`, `PUBLIC_APP_URL`, `ROMA_API_URL`, `ROMA_SYNC_TOKEN`, `QUEUE_CONNECTION=database`
- [ ] **`composer run dev`** o serve + **queue** + vite
- [ ] **roma-api otra PC:** `git pull`, `npm run dev`, ngrok :3000, `/api/health` → `media_pipeline_version: 3`
- [ ] **`php artisan catalog:index-embeddings`** tras fotos en productos
- [ ] **`php artisan queue:restart`** tras deploy
- [ ] Probar webhook: mensaje entrante + saliente con `wamid.`

### B. QA flujo negocio (checklist)

- [ ] Cliente: categoría → producto → color → talla → carrito → envío → resumen → Yape → comprobante
- [ ] Asesor: **✓ Pago validado** → mensaje pedido #N → chat en pestaña **Por enviar** (no Handoff)
- [ ] Carrito **vacío** tras validar; nuevo pedido / «Mismos datos» **sin** vestidos viejos
- [ ] Foto vestido: mensaje con **nombre** (ej. Aurora)
- [ ] Pipeline: **Entregado** → modo Bot otra vez
- [ ] Front: `npm run build` + F5 si no ves pestañas **Conversaciones | Por enviar**

### C. Producto / mejoras (backlog, no bloquean V1)

- [ ] Rotar secretos si se expusieron en chats/logs
- [ ] FAQ cliente en docs (opcional)
- [ ] Métricas bot en dashboard (ya hay intenciones/rutas en chat)
- [ ] Pruebas E2E automatizadas del embudo (Pest)

---

## 5. Decisiones fijas (no revertir sin acuerdo)

1. **Yape** → validación humana obligatoria antes de `paid`.
2. **Post-pago** → humano + bandeja **Por enviar**; bot vuelve con **Entregado**.
3. **Carrito** se limpia al validar pago (`finalizarValidacionPago`).
4. **Match foto** debe mostrar nombre del producto.
5. **LLM** off por defecto (`BOT_ENABLE_LLM_FALLBACK=false`).
6. **No ResNet** en producción; solo Voyage + catálogo indexado.
7. Copy y etapas en **código**, no constructor visual en panel.

---

## 6. Prohibiciones rápidas

| No |
|----|
| Inventar precios/stock |
| Confirmar pago sin asesor |
| Mezclar pedidos pagados en carrito nuevo |
| CRM → Meta sin roma-api |
| Duplicar flujo fuera de `app/Ventas/` |
| >3 botones o >10 filas en lista WA |

Lista completa: [`CONTEXTO_PROYECTO.md` §5](./CONTEXTO_PROYECTO.md#5-prohibiciones).

---

## 7. Flujo cliente (recordatorio)

```
Saludo → categorías → producto → color → talla → ¿más o confirmar?
→ envío (motorizado/Shalom) → datos → resumen → pago Yape
→ comprobante → [HUMANO valida] → pedido #N → POR ENVIAR → entregado → BOT
```

Atajos: reiniciar carrito, ver carrito, asesor, foto live, «Mismos datos».

---

## 8. Panel CRM — mapa rápido

| Ruta | Uso |
|------|-----|
| `/chat` | **Conversaciones** (activo) · **Por enviar** (pagado) |
| `/pipeline` | Estados pedido |
| `/dashboard` | Pagos por validar |
| `/products` | Catálogo + fotos → embeddings |
| `/company-settings` | Yape, tono |
| `/bot-settings` | Voyage key, LLM, recordatorios |
| `/bot-debug` | Simular sin WA |

---

## 9. Variables y archivos clave

```env
VOYAGE_API_KEY=pa-...
PUBLIC_APP_URL=https://....ngrok-free.dev
ROMA_API_URL=https://....ngrok-free.dev
ROMA_SYNC_TOKEN=...
```

| Código | Rol |
|--------|-----|
| `app/Ventas/OrquestadorVentas.php` | Entrada bot |
| `app/Ventas/EnrutadorVentas.php` | Etapas |
| `app/Services/ServicioModoConversacionPedido.php` | Post-pago humano |
| `app/Http/Controllers/Api/RomaSyncController.php` | Mensajes, validate-payment |
| `config/copy_ventas.php` | Textos Leidi |
| `config/flujo_ventas.php` | Límites, envíos, distritos |

---

## 10. Para agentes Cursor

Al tocar el bot:

1. Leer **este archivo** + [`CONTEXTO_PROYECTO.md`](./CONTEXTO_PROYECTO.md).
2. Skill: `.agents/skills/roma-sales-bot/SKILL.md`.
3. Cambios mínimos en `app/Ventas/`; tests en `tests/Unit/`.
4. Tras jobs: `php artisan queue:restart`.
5. No reintroducir ResNet sin decisión explícita del usuario.

---

## 11. Estado Git (referencia)

- **Rama principal:** `main` (remoto `origin/main`).
- **Docs en repo:** `docs/CONTEXTO_PROYECTO.md`, `docs/VESTIDOS_ROMA_GUIA.md`, **este archivo**.
- **Rama eliminada:** `PRobandoResnet` (experimento ResNet, no usar).
- **Archivos que a veces salen “modified” sin cambio real:** `BotFlowDebug*`, `copy_ventas.php` → solo CRLF; `git restore` si molesta.

---

## 12. Historial de la conversación (memoria del plan)

Temas trabajados en sesión (ya implementados salvo infra):

| Tema | Resultado |
|------|-----------|
| Fotos WA / ngrok / roma-api media v3 | Pipeline documentado; requiere roma-api actualizado |
| Post-pago → modo humano | `ServicioModoConversacionPedido` |
| Entregado → modo bot | `OrderController` + reactivar bot |
| Carrito no arrastra tras pagar | `finalizarValidacionPago` limpia carrito |
| Nombre vestido en match foto | `ManejadorMatchImagen` + copy |
| Bandeja **Por enviar** en chat | `inboxTab` en `useChatMessages` + UI |
| Documentación | `docs/*` commit en `main` |
| ResNet local | Descartado y limpiado de working tree |

---

*Actualiza las secciones 4 y 12 cuando cierres tareas o cambie el plan.*
