<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\TableReservation;
use App\Models\DetailTableReservation;
use App\Services\OdooService;
use Illuminate\Support\Facades\Auth;

class TableReservationController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function reservationPage(Request $request)
    {
        $tenantId = $request->query('tenant_id');

        try {
            $tenants = $this->odooService->getTenants();
            if (empty($tenants)) {
                return Inertia::render('reservation', [
                    'restaurant' => null,
                    'tables' => [],
                    'message' => 'Belum ada restoran/tenant yang terdaftar di Odoo.'
                ]);
            }

            // Find the requested tenant or default to the first one
            $restaurant = collect($tenants)->firstWhere('id', $tenantId) ?? $tenants[0];

            // Get available tables. We might not have a specific date/time yet, 
            // so we just fetch all tables for this floor/tenant.
            // For now, we will fetch all active tables from Odoo.
            $tables = $this->odooService->execute('restaurant.table', 'search_read', [[['active', '=', true]]], [
                'fields' => ['id', 'name', 'seats', 'floor_id']
            ]);

            return Inertia::render('reservation', [
                'restaurant' => $restaurant,
                'tables' => $tables
            ]);

        } catch (\Exception $e) {
            \Log::error('Odoo XML-RPC error in reservationPage: ' . $e->getMessage());
            return Inertia::render('reservation', [
                'restaurant' => null,
                'tables' => [],
                'message' => 'Gagal terhubung ke server Odoo.'
            ]);
        }
    }

    public function store(Request $request)
    {
        Log::info('Reservation Request:', $request->all());

        $validator = Validator::make($request->all(), [
            'restaurant_id'     => 'required|integer',
            'name'              => 'required|string|max:255',
            'phone'             => 'nullable|string|max:50',
            'date'              => 'required|date|after_or_equal:today',
            'time'              => 'required|string',
            'guests'            => 'required|integer|min:1',
            'tables'            => 'required|array|min:1',
            'tables.*.table_id' => 'required|integer',
            'tables.*.count'    => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator->errors());
        }

        try {
            DB::beginTransaction();

            $timeString = $request->time;
            $parsedTime = Carbon::createFromFormat('H:i', date('H:i', strtotime($timeString)));
            $dateTime = Carbon::parse($request->date)->setTimeFrom($parsedTime);

            // Minimum spend is disabled for now since it requires Odoo table structure logic.
            // We just set it to 0 or fetch it if added to Odoo later.
            $minimumSpend = 0; 
            
            // For Odoo we need time in float (e.g. 14.5 for 14:30)
            $timeStartFloat = $parsedTime->hour + ($parsedTime->minute / 60);
            $timeEndFloat = $timeStartFloat + 1.5; // default 1.5 hours duration

            // Create temporary reservation in Laravel
            $reservation = TableReservation::create([
                'reservation_id'   => (string) Str::uuid(),
                'user_id'          => Auth::id() ?? null,
                'customer_name'    => $request->name,
                'customer_phone'   => $request->phone,
                'restaurant_id'    => $request->restaurant_id, // This is Odoo tenant_id
                'reservation_time' => $dateTime,
                'status'           => 'pending',
                'minimum_spend'    => $minimumSpend
            ]);

            $tableIds = [];
            foreach ($request->tables as $tableData) {
                for ($i = 0; $i < $tableData['count']; $i++) {
                    DetailTableReservation::create([
                        'detail_table_reservation_id' => (string) Str::uuid(),
                        'table_id' => (string)$tableData['table_id'], // Store Odoo table ID
                        'reservation_id' => $reservation->reservation_id
                    ]);
                    $tableIds[] = $tableData['table_id'];
                }
            }

            DB::commit();

            // If there's no food ordering needed, we could push to Odoo immediately.
            // But since the flow redirects to food reservation page (Midtrans), we keep it pending.
            return redirect()->route('food.reservation.page', [
                'reservation_id' => $reservation->reservation_id
            ])->with('success', 'Reservation created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reservation Error:', [
                'message' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to create reservation: ' . $e->getMessage()]);
        }
    }
}