<?php
namespace App\Http\Controllers;
use Inertia\Inertia;
use App\Services\OdooService;
use Illuminate\Support\Facades\Log;
class HomeController extends Controller {
    protected $odooService;
    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }
    public function index()
    {
        $tenants = [];
        try {
            // Mengambil data tenant aktif dari Odoo
            $tenants = $this->odooService->getTenants();
        } catch (\Exception $e) {
            Log::error('Gagal mengambil data tenant untuk halaman utama: ' . $e->getMessage());
        }
        return Inertia::render('welcome', [
            'restaurants' => $tenants
        ]);
    }
}