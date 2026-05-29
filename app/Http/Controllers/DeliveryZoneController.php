<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        return Inertia::render('DeliveryZones/Index');
    }
}
