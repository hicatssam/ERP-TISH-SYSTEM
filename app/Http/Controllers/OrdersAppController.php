<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OrdersAppController extends Controller
{
    public function index(): View
    {
        return view('orders-app.index');
    }

    public function manifest(): JsonResponse
    {
        return response()->json([
            'id' => '/orders-app',
            'name' => 'طلبات دهب',
            'short_name' => 'طلبات دهب',
            'description' => 'مركز متابعة طلبات حلويات دهب',
            'lang' => 'ar',
            'dir' => 'rtl',
            'start_url' => '/orders-app',
            'scope' => '/orders-app',
            'display' => 'standalone',
            'background_color' => '#101c2d',
            'theme_color' => '#101c2d',
            'icons' => [
                ['src' => route('pwa.icon', ['size' => 192], false), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => route('pwa.icon', ['size' => 512], false), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ])->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'no-store');
    }
}
