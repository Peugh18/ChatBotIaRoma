<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class SedeShalomController extends Controller
{
    public function index()
    {
        return Inertia::render('SedesShalom/Index');
    }
}
