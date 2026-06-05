<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$settings = App\Models\BotSetting::first();
echo "auto_reply=".($settings->auto_reply_enabled ? '1' : '0')."\n";
echo "3min={$settings->reminder_3min_seconds}s 15min={$settings->reminder_15min_seconds}s\n";
echo "3min_msg=".mb_substr($settings->reminder_3min_message, 0, 60)."...\n\n";

$etapas = app(App\Ventas\MaquinaEstados\MaquinaEstadosVentas::class)->etapasConRecordatorio();
echo "etapas_recordatorio: ".implode(', ', $etapas)."\n\n";

foreach (App\Models\ConversationState::all() as $c) {
    $etapa = $c->context['etapa_venta'] ?? '-';
    $inList = in_array($etapa, $etapas, true) || in_array(
        App\Ventas\MaquinaEstados\EtapaVentas::LEGACY_MAP[$etapa] ?? $etapa,
        $etapas,
        true
    );
    $idle = $c->last_activity_at ? now()->diffInSeconds($c->last_activity_at) : 0;
    echo "{$c->phone_number} etapa={$etapa} human=".($c->requires_human ? '1' : '0');
    echo " rem={$c->last_reminder_sent} idle={$idle}s elegible=".($inList && ! $c->requires_human ? 'si' : 'no')."\n";
}

echo "\nEjecutando checkReminders...\n";
$sent = app(App\Services\ServicioRecordatorios::class)->checkReminders();
echo 'enviados='.count($sent)."\n";
print_r($sent);
