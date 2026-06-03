<?php

$flujo = require __DIR__.'/flujo_ventas.php';

return array_merge($flujo, [
    'shalom_lima_cost' => $flujo['costo_shalom_lima'],
    'shalom_provincia_cost' => $flujo['costo_shalom_provincia'],
    'style_filters' => $flujo['filtros_estilo'],
    'district_aliases' => $flujo['alias_distritos'],
    'delivery_hours' => $flujo['horario_entregas'],
    'payment_validation_client_message' => $flujo['mensaje_comprobante_recibido'],
    'payment_validation_pending_message' => $flujo['mensaje_pago_pendiente'],
    'payment_validation_approved_message' => $flujo['mensaje_pago_aprobado'],
    'payment_validation_escalation_reason' => $flujo['motivo_escalamiento_pago'],
]);
