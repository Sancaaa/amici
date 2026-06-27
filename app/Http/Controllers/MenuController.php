<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\OdooService;

class MenuController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index(Request $request)
    {
        try {
            $tenantId = $request->query('tenant_id');
            $menus = $this->odooService->getProducts($tenantId);

            return response()->json([
                'status' => true,
                'message' => 'Daftar semua menu dari Odoo.',
                'data' => $menus
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch menus from Odoo: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil data dari Odoo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // In Odoo, we fetch a single product.template
            $products = $this->odooService->execute('product.template', 'search_read', [[['id', '=', (int)$id]]], [
                'fields' => ['id', 'name', 'list_price', 'tenant_id', 'description', 'image_1920'],
                'limit' => 1
            ]);

            if (empty($products)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Menu tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $products[0]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error Odoo connection.'
            ], 500);
        }
    }

    public function recommend(Request $request)
    {
        $city = $request->input('city', 'Denpasar');
        $apiKey = env('OPENWEATHER_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key cuaca belum dikonfigurasi'], 500);
        }

        $weatherResponse = Http::get("https://api.openweathermap.org/data/2.5/weather", [
            'q' => $city,
            'appid' => $apiKey,
            'units' => 'metric',
        ]);

        if ($weatherResponse->failed()) {
            return response()->json(['error' => 'Gagal mengambil data cuaca'], 500);
        }

        $weatherData = $weatherResponse->json();
        $weather = $weatherData['weather'][0]['main'] ?? 'Unknown';
        $temp = $weatherData['main']['temp'] ?? 0;

        $hour = now()->format('H');
        if ($hour >= 5 && $hour < 11) {
            $dayTime = 'pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            $dayTime = 'siang';
        } elseif ($hour >= 15 && $hour < 18) {
            $dayTime = 'sore';
        } else {
            $dayTime = 'malam';
        }

        try {
            $menus = $this->odooService->getProducts();
            
            $menuList = collect($menus)->map(function($m) {
                $price = $m['list_price'] ?? 0;
                $name = $m['name'] ?? 'Menu';
                return "- {$name} ({$price})";
            })->implode("\n");

            $prompt = "
            Cuaca saat ini: $weather ($temp°C)
            Waktu: $dayTime
            Daftar menu tersedia:
            $menuList

            Dari daftar menu di atas, rekomendasikan 3 makanan yang paling cocok dengan kondisi cuaca dan waktu ini.
            Jelaskan alasan singkat untuk masing-masing pilihan.
            ";

            return response()->json([
                'success' => true,
                'prompt' => $prompt,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal mengambil data menu Odoo'], 500);
        }
    }
}
