# CONTEXTO DEL PROYECTO — RomaCrm / Vestidos Roma

**Documento único de referencia.** Incluye visión, requisitos, prohibiciones, flujos completos (cliente, bot, asesor, técnico), reglas de negocio y operación.

> Versión viva del producto: bot de ventas WhatsApp **V1** en `app/Ventas/`, integración **roma-api**, panel CRM Laravel + Vue.

---

## Tabla de contenidos

1. [Visión y objetivo](#1-visión-y-objetivo)
2. [Empresa y usuarios](#2-empresa-y-usuarios)
3. [Requisitos funcionales (obligatorios)](#3-requisitos-funcionales-obligatorios)
4. [Requisitos no funcionales](#4-requisitos-no-funcionales)
5. [Prohibiciones](#5-prohibiciones)
6. [Flujo del cliente (end-to-end)](#6-flujo-del-cliente-end-to-end)
7. [Flujo del bot (lógica y etapas)](#7-flujo-del-bot-lógica-y-etapas)
8. [Flujo del asesor (CRM)](#8-flujo-del-asesor-crm)
9. [Estados: pedido, conversación, etapa venta](#9-estados-pedido-conversación-etapa-venta)
10. [Arquitectura y repos](#10-arquitectura-y-repos)
11. [WhatsApp: límites y contratos](#11-whatsapp-límites-y-contratos)
12. [Configuración: qué se edita y qué no](#12-configuración-qué-se-edita-y-qué-no)
13. [Plan de fases (roadmap implementado)](#13-plan-de-fases-roadmap-implementado)
14. [Definición de hecho / criterios de éxito](#14-definición-de-hecho--criterios-de-éxito)
15. [Operación diaria](#15-operación-diaria)
16. [Glosario](#16-glosario)
17. [Referencias en el repo](#17-referencias-en-el-repo)

---

## 1. Visión y objetivo

### Qué es RomaCrm

CRM operativo para **Vestidos Roma**: vender por WhatsApp con un bot determinístico (**Leidi**), catálogo en base de datos, validación humana de pagos Yape, coordinación de envíos y panel para asesores.

### Objetivo de negocio

- Automatizar el embudo **descubrir → elegir modelo/color/talla → carrito → envío → pago → confirmación**.
- Dejar al humano solo lo que aporta valor: **validar Yape**, **excepciones**, **tarjeta/link**, **post-venta entrega**.
- Vender **solo lo que hay en stock** en el CRM (sin inventar precios ni tallas).

### Principio de desarrollo acordado

- **Mejorar** el módulo existente `app/Ventas/`, no crear flujos paralelos duplicados.
- El embudo principal vive en **código** (`config/copy_ventas.php`, `config/flujo_ventas.php`, manejadores).
- El panel configura **voz, Yape, escalamiento, LLM opcional**, no el árbol completo del menú.

---

## 2. Empresa y usuarios

### Marca

| Dato | Valor |
|------|--------|
| Marca | **Vestidos Roma** (Roma) |
| Moneda | Soles (`S/`) |
| Asistente | **Leidi** — cercana, femenina, “hermosa/linda”, 💖 |
| Productos | Vestidos por **categoría**; variantes **color + talla** |

### Usuarios del sistema

| Rol | Uso |
|-----|-----|
| **Cliente WhatsApp** | Compra, envía fotos, comprobante Yape |
| **Asesora / admin CRM** | Chat, valida pago, pipeline, catálogo |
| **Desarrollo** | roma-api + RomaCrm + ngrok + colas |

### Voz (copy)

- Central: `config/copy_ventas.php`
- Complemento comercial: `/company-settings` (CTA cierre, Yape, horarios)
- LLM (opcional): `/bot-settings` — no sustituye el flujo determinístico

### Logística y pagos

| Canal | Regla |
|-------|--------|
| **Yape** | Método principal; número y titular en empresa |
| **Tarjeta / link** | Bot pide datos → escala a asesora |
| **Motorizado** | Lima por distrito (`delivery-zones`) |
| **Shalom** | Provincia; sedes en `/sedes-shalom` |
| **Entregas** | Lun–sáb 5 pm–9 pm (mensaje bot) |

Costos referencia env (`flujo_ventas`): Shalom Lima ~S/10, provincia ~S/12 (ajustables).

---

## 3. Requisitos funcionales (obligatorios)

### RF-01 — Catálogo y navegación

- [ ] Menú por **categorías** (lista interactiva WA, paginación si >9 ítems).
- [ ] Listado de **productos** por categoría (paginación «Ver más»).
- [ ] Ficha de producto: precio, **stock por color y talla** desde BD.
- [ ] Opcional: hasta **4 fotos** al abrir categoría (`max_fotos_lista_productos`).

### RF-02 — Selección color / talla

- [ ] Solo colores con stock.
- [ ] Tallas validadas contra `sizes_stock` de la variante.
- [ ] Si no hay talla: mensaje + **similares** o alternativas.
- [ ] Foto de variante al elegir color (cuando existe imagen).

### RF-03 — Carrito

- [ ] Añadir líneas (producto · color · talla · precio).
- [ ] Ver carrito, agregar otro producto o **confirmar pedido**.
- [ ] Máximo **10 líneas** (`VENTAS_MAX_LINEAS_CARRITO`).
- [ ] Reinicio de carrito con confirmación Sí/No.
- [ ] Reanudar pedido a medias si aplica.

### RF-04 — Match por foto (live / consulta visual)

- [ ] Cliente envía imagen → búsqueda por **embeddings Voyage** en variantes indexadas.
- [ ] Respuesta debe incluir **nombre del vestido** (intro + mensaje interactivo).
- [ ] Multi-match: lista de candidatos con nombres.
- [ ] Sin API / sin media: mensaje claro + pedir nombre o categoría.

### RF-05 — Checkout

- [ ] Método de envío: motorizado vs Shalom.
- [ ] Captura distrito, nombre, celular, dirección, referencia.
- [ ] **Reutilizar datos** (“Mismos datos”) solo con datos guardados del cliente.
- [ ] Resumen: líneas + subtotal + envío + **total** + dirección.
- [ ] Método de pago (Yape prioritario).

### RF-06 — Pago Yape y validación humana

- [ ] Bot pide captura de comprobante.
- [ ] Al recibir imagen en etapa comprobante: confirma recepción, **modo humano**, pedido `pending`, etapa `esperando_validacion_pago`.
- [ ] Asesor en CRM: botón **Pago validado**.
- [ ] Tras validar: pedido `paid`, mensaje `pedido_confirmado`, **carrito vacío**, bandeja **Por enviar**, modo humano post-pedido.

### RF-07 — Post-pedido y entrega

- [ ] Chat en bandeja **Por enviar** (no mezclado con handoff urgente).
- [ ] Panel derecho: **Pedido confirmado** (sin galería/carrito de venta activa).
- [ ] Pipeline: `paid` → `shipped` → `delivered`.
- [ ] Al **Entregado**: limpiar flag post-pedido, **reactivar bot**.

### RF-08 — Escalamiento a humano

- [ ] Cliente puede pedir asesor (texto o botón `escalate_human`).
- [ ] Comprobante Yape pendiente escala automáticamente.
- [ ] Tarjeta: flujo pide datos y escala.
- [ ] Con `requires_human`: el bot **no responde** hasta volver a modo Bot (salvo mensajes automáticos controlados desde CRM).

### RF-09 — Panel CRM

- [ ] Chat tiempo real (Pusher opcional).
- [ ] Modo Bot / Humano manual.
- [ ] Contexto de venta: etapa, carrito, stock, validación pago.
- [ ] Pipeline kanban de pedidos.
- [ ] Dashboard: pagos por validar.
- [ ] Productos, categorías, zonas, sedes Shalom, debug bot.

### RF-10 — Anti-bucle y rescate

- [ ] Tras **3** mensajes sin avance útil en la misma etapa → menú rescate.
- [ ] Rescate: categorías, carrito, continuar, asesor humano.

### RF-11 — Recordatorios (scheduler)

- [ ] Recordatorios en etapas de carrito (config en bot-settings).
- [ ] Auto-retorno a bot tras inactividad en modo humano **excepto** post-pedido (`asesor_post_pedido`).

---

## 4. Requisitos no funcionales

| ID | Requisito |
|----|-----------|
| RNF-01 | Respuestas del bot vía **cola** (`ProcessIncomingMessageJob`, `SendWhatsappMessageJob`); sin cola no hay ventas. |
| RNF-02 | Integración WhatsApp **solo** vía **roma-api** + `ROMA_SYNC_TOKEN`. |
| RNF-03 | URLs públicas HTTPS para media (ngrok en dev). |
| RNF-04 | Flujo determinístico por defecto; LLM fallback **desactivado** (`BOT_ENABLE_LLM_FALLBACK=false`). |
| RNF-05 | Copy en español Perú; mensajes cortos (LLM máx. 3 líneas si se usa). |
| RNF-06 | Idempotencia: mensajes duplicados ignorados (~60s). |
| RNF-07 | Imágenes proxy CRM: caché, límite 10MB. |
| RNF-08 | Embeddings: reindexar tras subir fotos de variantes. |

---

## 5. Prohibiciones

### 5.1 Negocio y bot (NUNCA)

| # | Prohibición |
|---|-------------|
| P-01 | **Inventar precios** — solo los de `products` / variante en BD. |
| P-02 | **Prometer stock** sin leer `sizes_stock` / repositorio catálogo. |
| P-03 | **Generar códigos de descuento** no configurados en sistema. |
| P-04 | **Confirmar pago** sin validación humana (Yape). |
| P-05 | **Vender variantes sin stock** en la talla/color elegidos. |
| P-06 | **Mezclar en un mismo resumen** líneas de pedidos ya pagados con un pedido nuevo (carrito debe limpiarse al validar pago). |
| P-07 | **Mostrar pedido pagado** como “Handoff automático” — usar bandeja/estado **Por enviar**. |
| P-08 | **Enviar match por foto sin nombre** del modelo cuando hay match claro. |
| P-09 | **Reactivar bot** post-pedido antes de marcar **Entregado** en pipeline (salvo toggle manual consciente del asesor). |

### 5.2 Técnicas (NO hacer en este proyecto)

| # | Prohibición |
|---|-------------|
| T-01 | Conectar CRM **directo a Meta** o **Kapso** (arquitectura actual es roma-api). |
| T-02 | Duplicar flujo de ventas fuera de `app/Ventas/` (legacy `DeterministicBotService` solo como puente si aún existe). |
| T-03 | Editar el árbol de etapas desde panel sin desplegar código. |
| T-04 | Depender del LLM para el embudo principal de ventas. |
| T-05 | Más de **3 botones** o más de **10 filas** en una lista WA. |
| T-06 | Commitear `.env`, tokens Meta, keys en repo. |
| T-07 | `queue:restart` olvidado tras cambiar jobs en producción. |

### 5.3 UX asesor

| # | Prohibición |
|---|-------------|
| A-01 | Validar pago sin comprobante en etapa correcta (endpoint valida etapa). |
| A-02 | Enviar fotos de color al cliente con modo **Bot** activo (UI lo bloquea). |
| A-03 | Confundir **Por enviar** con **Handoff** — son bandejas y badges distintos. |

### 5.4 LLM (si `BOT_ENABLE_LLM_FALLBACK=true`)

Reglas heredadas del prompt (AgentService / anti-alucinación):

1. Nunca inventar precios (solo tools `get_products`).
2. Nunca prometer stock sin `check_stock`.
3. Nunca descuentos inventados.
4. Precio 0/null → “no disponible”.
5. Precio sospechoso → escalar a humano.

---

## 6. Flujo del cliente (end-to-end)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  ENTRADA                                                                     │
│  Cliente escribe o envía foto / usa botones de listas anteriores             │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  SALUDO → CATEGORÍAS → PRODUCTOS → PRODUCTO (stock por color)               │
│  → COLOR → TALLA → ¿MÁS O CONFIRMAR?                                       │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                    ┌─────────────────┴─────────────────┐
                    ▼                                   ▼
           [Agregar otro producto]              [Confirmar pedido]
                    │                                   │
                    └──────────────┬────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  ENVÍO: motorizado / Shalom → datos (o «Mismos datos») → RESUMEN            │
└─────────────────────────────────────────────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PAGO: Yape (captura) │ Tarjeta (datos → asesora)                           │
└─────────────────────────────────────────────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  YAPE: envía foto comprobante → bot confirma → ESPERA VALIDACIÓN HUMANA     │
└─────────────────────────────────────────────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  ASESOR: Pago validado → mensaje «Pedido #N registrado» → POR ENVIAR        │
│  Coordina entrega por WhatsApp (modo humano)                                 │
└─────────────────────────────────────────────────────────────────────────────┘
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  PIPELINE: Pagado → Enviado → ENTREGADO → bot vuelve, nueva compra limpia  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Atajos globales (cualquier etapa)

| Acción cliente | Efecto |
|----------------|--------|
| Reiniciar / empezar de nuevo | Confirmar borrado de carrito |
| Ver carrito | Resumen líneas |
| Ver categorías | Vuelve al menú |
| Continuar pedido | Reanuda contexto |
| Asesor humano | Handoff (`is_auto_escalated`) |
| Foto en live | Match catálogo (no comprobante) |
| Foto en comprobante | Validación pago |

---

## 7. Flujo del bot (lógica y etapas)

### Orden de procesamiento (`OrquestadorVentas`)

1. ¿`auto_reply_enabled`? Si no → silencio.
2. ¿`requires_human`? Si sí → silencio (asesor atiende).
3. Traducir IDs de botones WA → texto interno.
4. ¿Imagen?
   - Etapa `comprobante` → `recibirComprobante`
   - Otra → `ManejadorMatchImagen`
5. Comandos globales (`ManejadorRespuestasTransversales`).
6. Etapa `esperando_validacion_pago` → solo mensajes de pago o reinicio.
7. Checkout (`ManejadorCheckout`).
8. Por etapa: inicio, navegación, presentación (`EnrutadorVentas`).
9. Anti-bucle → menú rescate si aplica.

### Mapa de etapas (`etapa_venta`)

| Etapa | ID constante | Descripción |
|-------|--------------|-------------|
| Inicio | `inicio` | Post-pago o vacío |
| Categoría | `categoria` | Menú categorías |
| Productos | `productos` | Lista modelos |
| Producto | `producto` | Detalle + colores |
| Color | `color` | Elección color |
| Talla | `talla` | Elección talla |
| Más / confirmar | `mas_o_confirmar` | Carrito parcial |
| Envío método | `envio_metodo` | Motorizado / Shalom |
| Envío datos | `envio_datos` | Formulario envío |
| Reutilizar datos | `datos_reutilizar` | Mismos datos sí/no |
| Resumen | `resumen` | Total y confirmar |
| Pago | `pago` | Yape / tarjeta |
| Comprobante | `comprobante` | Espera captura |
| Validación | `esperando_validacion_pago` | Humano en CRM |
| Tarjeta | `tarjeta_datos` | Datos link pago |
| Confirmar reinicio | `confirmar_reinicio` | Sí/No borrar carrito |

### Contexto importante (`conversation_states.context`)

| Clave | Uso |
|-------|-----|
| `etapa_venta` | Etapa actual |
| `carrito` | Líneas `{producto, color, talla, precio, ...}` |
| `producto_actual_id` | Producto en curso |
| `color_actual` / `talla_actual` | Selección parcial |
| `checkout_paso` | Subpaso envío/datos |
| `datos_envio` | Últimos datos guardados del cliente |
| `ultimo_pedido_id` | Pedido recién creado/confirmado |
| `payment_proof_url` | Comprobante pendiente |
| `asesor_post_pedido` | Coordinación entrega |
| `asesor_post_pedido_order_id` | ID pedido post-pago |

### Limpieza al validar pago (`finalizarValidacionPago`)

Borra: carrito, producto/color/talla, checkout, comprobante pendiente, handoff temporal.  
Conserva: `ultimo_pedido_id`, `datos_envio`, flags post-pedido.

---

## 8. Flujo del asesor (CRM)

### Chat `/chat`

| Paso | Acción |
|------|--------|
| 1 | Elegir bandeja **Conversaciones** o **Por enviar** |
| 2 | Abrir cliente → leer historial WA |
| 3 | Si pago pendiente → **✓ Pago validado** |
| 4 | Si post-pedido → coordinar envío; banner verde |
| 5 | Actualizar **Pipeline** (enviado / entregado) |
| 6 | Entregado → cliente vuelve a bot en **Conversaciones** |

### Pipeline `/pipeline`

| Estado | Significado operativo |
|--------|----------------------|
| `pending` | Creado; aún no pagado confirmado |
| `paid` | Yape validado por asesor |
| `shipped` | En camino |
| `delivered` | Cerrado; bot reactivado |

### Dashboard

- Cola **Pagos por validar** → enlace directo al chat del teléfono.

---

## 9. Estados: pedido, conversación, etapa venta

### Modos de conversación

```
                    ┌──────────────┐
         ┌─────────│  BOT ACTIVO  │─────────┐
         │         └──────────────┘         │
         │ requires_human=false             │
         ▼                                    │
┌─────────────────┐              ┌──────────────────────┐
│ Handoff auto    │              │ Humano manual        │
│ is_auto_escalated│              │ (toggle CRM)        │
└────────┬────────┘              └──────────┬───────────┘
         │                                    │
         │         Pago validado              │
         └──────────────┬─────────────────────┘
                        ▼
              ┌─────────────────────┐
              │ Por enviar          │
              │ asesor_post_pedido  │
              │ (NO es handoff)     │
              └──────────┬──────────┘
                         │ Entregado
                         ▼
              ┌─────────────────────┐
              │ BOT ACTIVO otra vez │
              └─────────────────────┘
```

---

## 10. Arquitectura y repos

```
Meta WhatsApp
    ↓
roma-api (Node :3000) — webhook, media, send API
    ↓ POST /api/roma/messages  (header X-Roma-Sync-Token)
RomaCrm (Laravel :8000)
    ├─ ProcessIncomingMessageJob
    ├─ app/Ventas/OrquestadorVentas
    └─ SendWhatsappMessageJob → roma-api → Meta
```

| Repo / carpeta | Rol |
|----------------|-----|
| **RomaCrm** (este) | CRM, bot, pedidos, embeddings, UI |
| **apiRoma / ChatBotIaRoma** | roma-api, token Meta (también Supabase `app_settings`) |

### Servicios CRM destacados

| Servicio | Rol |
|----------|-----|
| `OrquestadorVentas` | Entrada bot |
| `EnrutadorVentas` | Etapas |
| `ServicioMatchImagen` | Voyage |
| `ServicioModoConversacionPedido` | Post-pago humano |
| `ServicioContextoVentaChat` | API panel chat |
| `RomaSyncController` | Mensajes, modo, validate-payment |

### Variables `.env` críticas

`PUBLIC_APP_URL`, `ROMA_API_URL`, `ROMA_SYNC_TOKEN`, `VOYAGE_API_KEY`, `WA_TOKEN` (opcional), `BOT_ENABLE_LLM_FALLBACK`, `PUSHER_*`, `QUEUE_CONNECTION=database`

---

## 11. WhatsApp: límites y contratos

| Límite | Valor |
|--------|--------|
| Botones por mensaje | **3** |
| Filas lista | **10** (9 ítems + «Ver más» típico) |
| IDs producto | `pick_product_{id}` |
| IDs categoría | `pick_category_{id}` |
| Página categorías | `page_categories_{n}` |
| Escalar humano | `escalate_human` |
| Carrito | `confirm_cart`, `add_more_product` |

**Envío:** solo mensajes `outgoing` con cola; éxito cuando respuesta trae `wamid.`.

---

## 12. Configuración: qué se edita y qué no

| Editable en panel | Solo en código |
|-------------------|----------------|
| Yape, tono, CTA, horarios | Etapas del embudo |
| Personalidad LLM, recordatorios | Orden de manejadores |
| Auto-reply on/off | Copy principal (`copy_ventas.php`) |
| Umbrales visión (parcial env) | Reglas anti-bucle |
| Productos, stock, fotos | IDs botones WA |
| Zonas delivery, sedes | Paginación listas |

---

## 13. Plan de fases (roadmap implementado)

| Fase | Contenido | Estado |
|------|-----------|--------|
| 0–1 | BD, módulo Ventas, catálogo, carrito | Hecho |
| 2 | Similares, descuento, sin stock | Hecho |
| 3 | Match foto Voyage | Hecho |
| 4–5 | Checkout, Yape, validación CRM | Hecho |
| 6 | FAQ, recordatorios, reanudar | Hecho |
| 7 | Panel CRM ampliado, sedes, similares manuales | Hecho |
| 8 | Paginación WA, cola pagos dashboard, bot-debug | Hecho |
| Post | Bandeja Por enviar, limpieza carrito, nombre en match | Hecho |

---

## 14. Definición de hecho / criterios de éxito

Una venta Yape está **bien cerrada** cuando:

1. Cliente recibió resumen con total correcto.
2. Comprobante quedó en pedido `pending` → asesor validó → `paid`.
3. Cliente recibió mensaje con **número de pedido**.
4. Carrito vacío; nuevo pedido no arrastra líneas viejas.
5. Chat en **Por enviar**; pipeline en `paid` o posterior.
6. Tras entrega: `delivered`, bot activo, cliente puede comprar de nuevo.

Un **match por foto** está bien cuando:

1. Nombre del modelo visible en el mensaje.
2. Solo productos del catálogo con embedding/stock coherente.
3. Fallback claro si no hay match o falla media.

---

## 15. Operación diaria

```bash
composer run dev          # serve + queue + vite
php artisan schedule:work
php artisan queue:restart # tras deploy
npm run build             # si el front no actualiza
php artisan catalog:index-embeddings
```

### Checklist incidentes

| Síntoma | Revisar |
|---------|---------|
| Bot no responde | Cola, `requires_human`, `auto_reply_enabled` |
| No sale al teléfono | `SendWhatsappMessageJob`, token Meta en roma-api |
| Foto no reconoce | VOYAGE_API_KEY, index embeddings, ngrok media v3 |
| UI sin «Por enviar» | `npm run build`, Ctrl+Shift+R |
| Handoff en vez de Por enviar | Validar pago de nuevo o pedido `paid` en BD |

### Dónde ver «Por enviar»

`/chat` → columna izquierda → pestaña **Por enviar** (no está en el menú lateral).

---

## 16. Glosario

| Término | Significado |
|---------|-------------|
| **Leidi** | Persona del bot |
| **Handoff** | Cliente pidió asesor; urgencia atención |
| **Por enviar** | Pago ya validado; coordinar entrega |
| **roma-api** | Microservicio WhatsApp |
| **Etapa** | Paso del embudo en `context.etapa_venta` |
| **Embedding** | Vector Voyage para match visual |
| **Live** | Cliente envía foto de vestido para identificar modelo |

---

## 17. Referencias en el repo

| Documento | Uso |
|-----------|-----|
| `docs/CONTEXTO_PROYECTO.md` | **Este archivo** — contexto completo |
| `docs/VESTIDOS_ROMA_GUIA.md` | Guía operativa resumida |
| `app/Ventas/README.md` | Arquitectura módulo Ventas |
| `.agents/skills/roma-sales-bot/SKILL.md` | Skill agente Cursor |
| `config/copy_ventas.php` | Textos bot |
| `config/flujo_ventas.php` | Reglas numéricas, distritos |
| `README.md` | Setup desarrollo |

---

*Mantén este documento alineado con cada cambio de negocio. Si cambias copy o reglas, actualiza también `config/copy_ventas.php` y la sección correspondiente aquí.*
