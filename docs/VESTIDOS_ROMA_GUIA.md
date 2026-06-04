# Vestidos Roma — Guía rápida

> **Documento maestro del proyecto:** [`CONTEXTO_PROYECTO.md`](./CONTEXTO_PROYECTO.md)  
> Ahí está **todo**: visión, requisitos, prohibiciones, flujos completos (cliente, bot, asesor), arquitectura, estados y operación.

Esta guía es un **resumen operativo** para el día a día.

---

## Empresa

- **Vestidos Roma** · bot **Leidi** · soles · Yape + motorizado/Shalom
- Copy: `config/copy_ventas.php` · Panel: `/company-settings`

## Dónde trabaja el asesor

| Lugar | Para qué |
|-------|----------|
| `/chat` → **Conversaciones** | Ventas activas, handoff, pago por validar |
| `/chat` → **Por enviar** | Pedido ya pagado; coordinar entrega |
| `/pipeline` | Pagado → Enviado → **Entregado** (reactiva bot) |
| `/dashboard` | Cola pagos por validar |

## Flujo Yape en 6 pasos

1. Cliente confirma carrito y paga  
2. Envía captura → bot confirma → modo humano  
3. Asesor: **✓ Pago validado** en chat  
4. Cliente recibe «Pedido #N registrado» · carrito limpio  
5. Chat en **Por enviar**  
6. **Entregado** en pipeline → bot otra vez  

## Comandos

```bash
composer run dev
php artisan queue:restart
npm run build    # si no ves pestaña Por enviar
php artisan catalog:index-embeddings
```

## Reglas que no se rompen

- No inventar precios/stock  
- No mezclar pedidos viejos en carrito tras pagar  
- No usar Meta directo desde CRM (solo roma-api)  
- Match por foto **con nombre** del vestido  

---

Detalle completo → **[CONTEXTO_PROYECTO.md](./CONTEXTO_PROYECTO.md)**
