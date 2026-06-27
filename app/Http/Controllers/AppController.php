<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\OdooService;

class AppController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index(Request $request)
    {
        try {
            $tenants = $this->odooService->getTenants();
            
            // Basic filtering if search exists
            if ($request->has('search') && !empty($request->search)) {
                $search = strtolower($request->search);
                $tenants = array_filter($tenants, function($t) use ($search) {
                    return strpos(strtolower($t['name'] ?? ''), $search) !== false;
                });
                $tenants = array_values($tenants);
            }
        } catch (\Exception $e) {
            $tenants = [];
            \Log::error('Failed to fetch tenants from Odoo: ' . $e->getMessage());
        }

        return Inertia::render('restaurants', [
            'restaurants' => $tenants, // Keep the prop name as 'restaurants' for frontend compatibility
            'filters' => $request->only(['search']),
        ]);
    }
}
