<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('crm.messages', function ($user) {
    return $user !== null;
});

Broadcast::channel('crm.escalations', function ($user) {
    return $user !== null;
});
