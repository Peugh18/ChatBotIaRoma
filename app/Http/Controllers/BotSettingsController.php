<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BotSettingsController extends Controller
{
    public function index()
    {
        return inertia('BotSettings/Index');
    }
}
