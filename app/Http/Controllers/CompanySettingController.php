<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class CompanySettingController extends Controller
{
    public function index()
    {
        return Inertia::render('CompanySettings/Index');
    }
}
