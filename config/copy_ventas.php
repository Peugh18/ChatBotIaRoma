<?php

/**
 * Textos del bot — tono cercano, no robótico. Placeholders: {nombre}, {producto}, {precio}, etc.
 */
return [
    'sin_datos_bd' => 'Por ahora no tengo eso registrado, hermosa 💖 Te muestro lo que sí tenemos disponible.',

    'saludo_intro' => 'Hola linda, soy Leidi, tu asistente de Roma 💖',
    'saludo_cta_categorias' => 'Tenemos estos productos para ti. Toca una opción 👇',
    'saludo_nueva' => "Hola linda, soy Leidi, tu asistente de Roma 💖\n\nTenemos estos productos para ti. Toca una opción 👇",
    'saludo_regresa' => "¡Hola {nombre}! 💖 Qué gusto verte de nuevo.\n\nSoy Leidi. Tenemos estos productos para ti. Toca una opción 👇",
    'pie_categorias' => 'Toca una opción para ver modelos 👇',

    'lista_pagina_siguiente' => 'Siguiente página ({pagina}) 👇',
    'lista_productos_intro' => 'Estos son los modelos disponibles en {categoria} 😍',
    'lista_productos_pie' => 'Elige el que te guste 👇',

    'producto_intro' => 'Mira, este está precioso 😍',
    'producto_linea_precio' => '{nombre} — S/{precio}',
    'producto_stock_por_color' => 'Disponibilidad:',
    'producto_color_linea' => '{color}: {tallas}',
    'producto_color_agotado' => '{color}: agotado',
    'producto_elige_color' => 'Elige tu color 👇',
    'producto_sin_colores' => 'Este modelo no tiene colores con stock ahora 😔 ¿Quieres ver otros modelos?',

    'color_confirmado' => 'Listo, color {color} 💖',
    'foto_color_caption' => '{producto} en {color}',
    'pregunta_talla' => '¿Qué talla necesitas?',
    'reprompt_etapa' => 'Toca una opción de la lista o escríbeme el color/talla tal como aparece arriba 💖',
    'talla_confirmada' => 'Perfecto, talla {talla} ✅',
    'talla_sin_stock' => 'Esa talla no está en {color} 😔 Te muestro opciones parecidas con tu talla.',
    'linea_carrito' => 'Llevas: {producto} · {color} · {talla} · S/{precio}',
    'pregunta_mas_o_confirmar' => '¿Agregas otro producto o confirmamos tu pedido?',
    'agregar_otro_intro' => 'Dale, elige otro modelo 👇',
    'agregar_otro_categorias_intro' => 'Sigues con lo que ya llevas en el carrito 💖 Elige otra categoría para agregar:',
    'otras_categorias_intro' => 'Sin problema 💖 Mira estas categorías y elige otra:',
    'lista_otros_modelos_intro' => 'Otros modelos en {categoria} 👇',

    'descuento_suerte' => '¡Qué suerte! *{producto}* tiene descuento — te queda en S/{precio} 😍',

    'rescate_intro' => '{nombre}te ayudo 💖 ¿Qué necesitas?',
    'rescate_sin_nombre' => 'Te ayudo 💖 ¿Qué necesitas?',

    'carrito_vacio' => 'Aún no tienes nada en el carrito. Elige un modelo para empezar 👇',
    'carrito_resumen' => "Tu carrito:\n{lineas}\n\nTotal productos: S/{subtotal}",

    'confirmar_reinicio' => '¿Borramos el carrito y empezamos de cero? Toca Sí o No 👇',
    'reinicio_cancelado' => 'Dale, seguimos con tu pedido 💖',
    'reinicio_hecho' => 'Listo, empezamos de nuevo 💖',

    'reanudar_pedido' => "Tenías un pedido a medias:\n{resumen}\n\n¿Seguimos o quieres cambiar algo?",
    'stock_carrito_cambio' => 'Ojo: algunas tallas ya no están. Te actualizo el carrito 👇',

    'match_intro' => "Creo que puede ser uno de estos 😍\n{productos}",
    'match_uno' => 'Creo que es el {producto} (S/{precio}). Mira 👇',
    'match_sin_resultado' => 'No encontré ese modelo exacto, pero tengo opciones parecidas 👇',
    'match_sin_wa_token' => 'Recibí tu foto 📸 Para reconocerla automáticamente el equipo debe configurar WA_TOKEN en el servidor. Mientras tanto, dime el nombre del vestido o elige categoría 👇',
    'match_sin_voyage' => 'Recibí tu foto 📸 El reconocimiento visual no está activo (falta VOYAGE_API_KEY). Cuéntame el nombre del modelo o elige categoría 👇',

    'comprobante_pide_captura' => 'Envíame la captura de tu pago por aquí 📸',
    'comprobante_recibido' => 'Gracias hermosa 💖 Ya recibimos tu comprobante. Una asesora lo valida y te confirmamos.',
    'pedido_confirmado' => 'Listo 💖 Tu pedido #{pedido} quedó registrado. Pronto te contactamos.',
    'tarjeta_espera' => 'Perfecto 💖 Pedido #{pedido} por S/{total}. En un momentito te enviamos tu link de pago por aquí 💳',
    'tarjeta_espera_link' => 'Tu link de pago está en camino hermosa 💳 En un ratito te lo enviamos por aquí.',
    'tarjeta_mensaje_link' => "Listo hermosa 💳 Aquí está tu link de pago:\n{link}\n\n*Pedido #{pedido} · Total S/{total}*",
    'tarjeta_pide_captura_post_link' => 'Cuando hayas pagado, envíame la captura por aquí 📸 Quedo atenta para validarlo.',
    'tarjeta_recordatorio_link_cliente' => 'Tu link de pago llega en un momentito hermosa 💳✨ Gracias por tu paciencia.',

    'datos_guardados_intro' => "Para tu envío tengo estos datos:\n\n{envio_linea}{nombre_linea}{celular_linea}{direccion_linea}{dni_linea}\n\n¿Usamos los mismos o los actualizamos?",

    'similares_intro' => 'Este modelo está agotado en ese color, pero tengo opciones parecidas 😍',

    'envio_elige_metodo' => "¿Cómo te enviamos tu pedido? 👇\n\n🚚 *Shalom:* S/10 en Lima y S/12 en provincia (promedio).\n🏍️ *Motorizado Lima:* según distrito; se suma al total y pagas *antes* de la entrega.\n\nToca una opción:",
    'envio_intro_motorizado' => "🏍️ *Envío motorizado (Lima)*\n\n{horario_entregas}\nEl costo de envío según tu distrito se suma al total del pedido y lo pagas antes de coordinar la entrega 💖\n\nTe voy pidiendo los datos uno por uno. La ubicación en tiempo actual (📍) es solo de referencia para el reparto.",
    'envio_intro_shalom' => "📦 *Envío Shalom*\n\n🚚 Costo referencial: S/10 Lima · S/12 provincia (promedio). Se suma al total del pedido 💖",
    'envio_lineas_pedido' => "🛍️ *Tu pedido:*\n{lineas}",
    'checkout_pide_sede_shalom' => "¿En qué sede Shalom recoges tu pedido?\n\nEscríbela con ciudad y agencia, por ejemplo:\n*Cusco — Av. El Sol* o *Arequipa — Cerro Colorado*",
    'checkout_pide_distrito' => '¿En qué distrito de Lima estás? (ej. Surco, Miraflores)',
    'checkout_pide_nombre' => '¿A nombre de quién enviamos? 💖',
    'checkout_pide_dni' => '¿Tu número de DNI? (8 dígitos)',
    'checkout_pide_dni_invalido' => 'El DNI debe tener *exactamente 8 dígitos*. Escríbelo de nuevo por favor 🪪',
    'checkout_pide_celular' => '¿Un celular de contacto?',
    'checkout_pide_direccion' => 'Dime la dirección exacta (calle, número, urbanización)',
    'checkout_pide_ubicacion' => 'Comparte tu *ubicación en tiempo actual* con el clip 📍 de WhatsApp (solo como referencia para el reparto), o pega el enlace de Google Maps.',
    'ubicacion_referencia_recibida' => '📍 Listo, guardé tu ubicación como referencia para el reparto.',
    'ubicacion_fuera_envio' => '📍 Recibí tu ubicación. Cuando elijas envío motorizado en tu pedido la usamos como referencia 💖',

    'resumen_pedido' => "Así queda tu pedido:\n{lineas}\n\nSubtotal: S/{subtotal}\nEnvío: S/{envio}\n*Total: S/{total}*\n\n{envio_detalle}",
    'resumen_envio_motorizado' => "🏍️ Motorizado · {distrito}\n📍 {direccion}\n{ubicacion_linea}",
    'resumen_envio_shalom' => "📦 Shalom · {sede}\n🪪 DNI: {dni}",

    'recordatorio_datos_envio' => 'Hermosa por favor sus datos para poder programar el envío 💖',

    'pago_elige_metodo' => '¿Cómo prefieres pagar? 👇',

    'tarjeta_pide_nombre' => 'Para el link de tarjeta necesito tu nombre completo 💳',
    'tarjeta_pide_email' => 'Tu correo electrónico, por favor',
    'tarjeta_pide_celular' => 'Y tu número de celular',
];
