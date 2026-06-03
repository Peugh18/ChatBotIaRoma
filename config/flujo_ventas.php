<?php

return [
    'mensaje_bot_reconstruccion' => 'Hola hermosa 💖 Dame un segundito y te muestro el catálogo.',

    'vision_match_alto' => (float) env('VENTAS_VISION_MATCH_ALTO', 0.85),
    'vision_match_medio' => (float) env('VENTAS_VISION_MATCH_MEDIO', 0.72),

    'max_lineas_carrito' => (int) env('VENTAS_MAX_LINEAS_CARRITO', 10),
    'max_intentos_sin_avance' => (int) env('VENTAS_MAX_INTENTOS_SIN_AVANCE', 3),
    /** Filas de producto/categoría por página en listas WA (máx 10 con fila «Ver más»). */
    'lista_filas_por_pagina' => (int) env('VENTAS_LISTA_FILAS_POR_PAGINA', 9),
    /** Fotos enviadas antes de la lista al elegir categoría (0 = solo lista; máx recomendado 4). */
    'max_fotos_lista_productos' => (int) env('VENTAS_MAX_FOTOS_LISTA_PRODUCTOS', 4),

    'costo_shalom_lima' => (float) env('SALES_SHALOM_LIMA_COST', 10),
    'costo_shalom_provincia' => (float) env('SALES_SHALOM_PROVINCIA_COST', 12),

    'filtros_estilo' => [
        'elegante' => 'Elegante',
        'casual' => 'Casual',
        'fiesta' => 'Fiesta',
        'sexy' => 'Sexy',
        'ofertas' => 'Ofertas',
    ],

    'alias_distritos' => [
        'surco' => 'Santiago de Surco',
        'magdalena' => 'Magdalena del Mar',
        'smp' => 'San Martín de Porres',
        'sjl' => 'San Juan de Lurigancho',
        'jesus maria' => 'Jesús María',
        'jesús maría' => 'Jesús María',
        'cercado' => 'Cercado de Lima',
        'los olivos' => 'Los Olivos',
        'san borja' => 'San Borja',
        'san isidro' => 'San Isidro',
    ],

    'horario_entregas' => 'Las entregas son de lunes a sábado entre 5 pm y 9 pm.',

    'mensaje_comprobante_recibido' => 'Gracias hermosa 💖 Recibimos tu comprobante. Una asesora validará el pago y enseguida te confirmamos.',
    'mensaje_pago_pendiente' => 'Tu pago sigue en validación hermosa 💕 En unos minutos te confirmamos.',
    'mensaje_pago_aprobado' => 'Tu pedido ha sido confirmado exitosamente 💖 Muy pronto coordinamos la entrega.',
    'motivo_escalamiento_pago' => 'Comprobante de pago pendiente de validación',
];
