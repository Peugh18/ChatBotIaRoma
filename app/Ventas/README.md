# Módulo Ventas (bot WhatsApp V1)

## Arquitectura

```
OrquestadorVentas          ← entrada (ServicioBotEntrada)
    ├── TraductorAccionesWhatsapp
    ├── ServicioAntiBucle
    ├── ManejadorMatchImagen (fotos / comprobante)
    └── EnrutadorVentas
            ├── ManejadorRespuestasTransversales (rescate, carrito, FAQ, reinicio)
            ├── ManejadorCheckout (envío, resumen, pago)
            ├── ManejadorInicio
            ├── ManejadorNavegacion
            └── ManejadorPresentacion (producto, color, talla, similares)

MaquinaEstadosVentas + EtapaVentas
RepositorioCatalogo | RepositorioEnvios | RepositorioPedidos
ServicioCarrito | ServicioMatchImagen
ConstructorMensaje | ConstructorInteractivos
Contrato: RespuestaBot (texto + metadata interactivo/imagen)
```

Copy: `config/copy_ventas.php` · reglas: `config/flujo_ventas.php`

## Fases

| Fase | Contenido |
|------|-----------|
| 0–1 | BD, módulo, catálogo, carrito |
| 2 | Similares, descuento, sin stock |
| 3 | Match foto Voyage |
| 4–5 | Checkout, Yape, comprobante, validación CRM |
| 6 | FAQ, recordatorios, reanudar |
| **7** | **Panel CRM** | Hecho |
| **8** | **v1.1** paginación WA, cola pagos dashboard, debug bot | Hecho |

### Fase 7 — Panel

- `mensaje_presentacion` en Personalidad del bot
- Producto: `status` + similares manuales (`/api/products/{id}/similares`)
- Sedes Shalom (`/sedes-shalom`)
- Chat: contexto ampliado (carrito, stock por color, etapa legible, validación pago)
- Foto variante → cola `IndexVariantEmbeddingJob` automática

### Fase 8 — v1.1

- `PaginadorListasWhatsapp`: categorías, productos y sedes Shalom (>9 ítems + «Ver más»)
- Dashboard: cola **Pagos por validar** → enlace `/chat?phone=...`
- `/bot-debug`: simular mensaje + reiniciar conversación de prueba (`POST /api/bot-debug/simulate`)
