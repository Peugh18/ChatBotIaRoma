<?php

return [
    /*
    | Tarifas Shalom cuando no hay distrito (Etapa 6 del flujo comercial).
    */
    'shalom_lima_cost' => (float) env('SALES_SHALOM_LIMA_COST', 10),
    'shalom_provincia_cost' => (float) env('SALES_SHALOM_PROVINCIA_COST', 12),

    /*
    | Filtros de estilo (tags_ia en productos). Se muestran solo si hay stock en la categoría.
    */
    'style_filters' => [
        'elegante' => 'Elegante',
        'casual' => 'Casual',
        'fiesta' => 'Fiesta',
        'sexy' => 'Sexy',
        'ofertas' => 'Ofertas',
    ],

    /*
    | Alias de distritos para cotización motorizado (DeliveryZone en BD).
    */
    'district_aliases' => [
        'surco' => 'Santiago de Surco',
        'magdalena' => 'Magdalena del Mar',
        'smp' => 'San Martín de Porres',
        'sjl' => 'San Juan de Lurigancho',
        'jesus maria' => 'Jesús María',
        'jesús maría' => 'Jesús María',
        'cercado' => 'Cercado de Lima',
        'villa el salvador' => 'Villa El Salvador',
        'villa maria' => 'Villa María',
        'villa maría' => 'Villa María',
        'los olivos' => 'Los Olivos',
        'san borja' => 'San Borja',
        'san isidro' => 'San Isidro',
        'san miguel' => 'San Miguel',
        'pueblo libre' => 'Pueblo Libre',
        'la victoria' => 'La Victoria',
    ],

    'delivery_hours' => 'Las entregas se realizan de lunes a sábado entre 5 pm y 9 pm.',

    /*
    | Comprobante de pago: el bot escala a humano y no confirma solo.
    */
    'payment_validation_client_message' => 'Perfecto hermosa 💕 Recibimos tu comprobante de pago. Un momento, estamos validándolo con el equipo ✨ En breve te confirmamos.',
    'payment_validation_pending_message' => 'Tu pago sigue en validación hermosa 💕 En unos minutos te confirmamos. Gracias por tu paciencia ✨',
    'payment_validation_approved_message' => 'Listo hermosa 💕 Tu pago fue validado correctamente ✨',
    'payment_validation_escalation_reason' => 'Comprobante de pago Yape pendiente de validación manual',
];
