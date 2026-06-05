<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = $argv[1] ?? '51959166911';
$s = \App\Models\ConversationState::where('phone_number', $phone)->first();
if (! $s) {
    echo "no state for {$phone}\n";
    exit(0);
}
$c = $s->context ?? [];
echo 'etapa='.($c['etapa_venta'] ?? '?')."\n";
echo 'pendiente='.json_encode($c['pendiente_link_tarjeta'] ?? null)."\n";
echo 'order='.($c['ultimo_pedido_id'] ?? 0)."\n";
echo 'human='.($s->requires_human ? '1' : '0')."\n";
echo 'pending_service='.(app(\App\Services\ServicioLinkPagoTarjeta::class)->estaPendiente($s) ? 'yes' : 'no')."\n";

$ctx = app(\App\Services\ServicioContextoVentaChat::class)->forPhone($phone);
echo 'api_pending='.json_encode($ctx['card_payment_link']['pending'] ?? null)."\n";
