<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class BotDebugController extends Controller
{
    public function index()
    {
        return Inertia::render('BotDebug/Index');
    }
}
