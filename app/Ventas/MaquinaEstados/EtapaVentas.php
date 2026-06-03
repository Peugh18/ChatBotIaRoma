<?php

namespace App\Ventas\MaquinaEstados;

final class EtapaVentas
{
    public const INICIO = 'inicio';

    public const CATEGORIA = 'categoria';

    public const PRODUCTOS = 'productos';

    public const PRODUCTO = 'producto';

    public const COLOR = 'color';

    public const TALLA = 'talla';

    public const MAS_O_CONFIRMAR = 'mas_o_confirmar';

    public const ENVIO_METODO = 'envio_metodo';

    public const ENVIO_DATOS = 'envio_datos';

    public const DATOS_REUTILIZAR = 'datos_reutilizar';

    public const RESUMEN = 'resumen';

    public const PAGO = 'pago';

    public const COMPROBANTE = 'comprobante';

    public const VALIDACION_PAGO = 'esperando_validacion_pago';

    public const TARJETA_DATOS = 'tarjeta_datos';

    public const CONFIRMAR_REINICIO = 'confirmar_reinicio';

    /** Legacy */
    public const LEGACY_VALIDACION = 'awaiting_payment_validation';

    public const LEGACY_MAP = [
        'awaiting_category_selection' => self::CATEGORIA,
        'awaiting_product_selection' => self::PRODUCTOS,
        'awaiting_color_selection' => self::COLOR,
        'awaiting_size_selection' => self::TALLA,
        'awaiting_order_confirmation' => self::MAS_O_CONFIRMAR,
        'awaiting_shipping_method' => self::ENVIO_METODO,
        'awaiting_shipping_data' => self::ENVIO_DATOS,
        'awaiting_shipping_details' => self::ENVIO_DATOS,
        'awaiting_order_summary' => self::RESUMEN,
        'awaiting_payment_method' => self::PAGO,
        'awaiting_payment_proof' => self::COMPROBANTE,
        'awaiting_payment_validation' => self::VALIDACION_PAGO,
        'awaiting_card_details' => self::TARJETA_DATOS,
        'envio_metodo' => self::ENVIO_METODO,
        'envio_datos' => self::ENVIO_DATOS,
        'resumen' => self::RESUMEN,
        'pago' => self::PAGO,
        'comprobante' => self::COMPROBANTE,
        'esperando_validacion_pago' => self::VALIDACION_PAGO,
    ];
}
