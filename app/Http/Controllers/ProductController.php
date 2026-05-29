<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Products/Index');
    }

    public function create()
    {
        return Inertia::render('Products/Create');
    }

    public function edit(Product $product)
    {
        $product->load(['category', 'variants']);
        return Inertia::render('Products/Edit', [
            'product' => $product,
        ]);
    }
}
