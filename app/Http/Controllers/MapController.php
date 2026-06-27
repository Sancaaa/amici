<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;

class MapController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index()
    {
        $tenants = [];
        try {
            $tenants = $this->odooService->getTenants();
        } catch (\Exception $e) {
            Log::error('Gagal mengambil data tenant untuk peta: ' . $e->getMessage());
        }

        return Inertia::render('Map/Index', [
            'restaurants' => $tenants
        ]);
    }
}