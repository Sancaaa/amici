<?php

namespace App\Http\Controllers;

use App\Models\FoodReservation;
use App\Models\DetailFoodReservation;
use App\Models\TableReservation;
use App\Models\Payment;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FoodReservationController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index(Request $request)
    {
        $reservationId = $request->query('reservation_id');
        $reservation = TableReservation::where('reservation_id', $reservationId)->first();
        
        if (!$reservation) {
            return redirect()->route('reservation.page')
                ->with('error', 'Reservation not found.');
        }

        try {
            // Fetch tenant and menus from Odoo
            $tenants = $this->odooService->getTenants();
            $restaurant = collect($tenants)->firstWhere('id', $reservation->restaurant_id);
            
            $menus = $this->odooService->getProducts($reservation->restaurant_id);

        } catch (\Exception $e) {
            return redirect()->route('reservation.page')
                ->with('error', 'Odoo Connection Failed.');
        }

        return Inertia::render('FoodReservation', [
            'reservation'   => $reservation,
            'restaurant'    => $restaurant,
            'menus'         => $menus,
            'minimumSpend'  => 0,
            'midtransClientKey' => config('midtrans.client_key'),
        ]);
    }

    public function store(Request $request)
    {
        Log::info('=== FOOD RESERVATION STORE START ===');

        $request->validate([
            'reservation_id' => 'required|exists:table_reservations,reservation_id',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer', // Odoo ID
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $reservation = TableReservation::where('reservation_id', $request->reservation_id)->first();

            $existingFoodReservation = FoodReservation::where('reservation_id', $request->reservation_id)->first();
            if ($existingFoodReservation) {
                $existingFoodReservation->delete();
            }

            $itemDetails = [];
            $totalFoodPrice = 0;

            // Fetch actual prices from Odoo to prevent tampering
            $menus = $this->odooService->getProducts($reservation->restaurant_id);

            foreach ($request->items as $item) {
                $odooProduct = collect($menus)->firstWhere('id', $item['menu_id']);
                if (!$odooProduct) continue;

                $quantity = (int) $item['quantity'];
                $price = (float) $odooProduct['list_price'];
                $subtotal = $quantity * $price;

                $totalFoodPrice += $subtotal;

                $itemDetails[] = [
                    'id' => (string) $odooProduct['id'],
                    'price' => (int) round($price),
                    'quantity' => $quantity,
                    'name' => mb_substr($odooProduct['name'], 0, 50),
                ];
            }

            $tax = $totalFoodPrice * 0.10;
            $service = $totalFoodPrice * 0.05;
            $grandTotal = $totalFoodPrice + $tax + $service;

            $itemDetails[] = [
                'id' => 'TAX-' . time(),
                'price' => (int) round($tax),
                'quantity' => 1,
                'name' => 'Tax (10%)',
            ];

            $itemDetails[] = [
                'id' => 'SERVICE-' . time(),
                'price' => (int) round($service),
                'quantity' => 1,
                'name' => 'Service Charge (5%)',
            ];

            $calculatedGrossAmount = 0;
            foreach ($itemDetails as $item) {
                $calculatedGrossAmount += $item['price'] * $item['quantity'];
            }

            $foodReservationId = 'FR-' . strtoupper(Str::random(10));
            
            $foodReservation = FoodReservation::create([
                'food_reservation_id' => $foodReservationId,
                'reservation_id' => $request->reservation_id,
                'total_food_price' => $totalFoodPrice,
                'tax' => $tax,
                'service_charge' => $service,
                'grand_total' => $grandTotal,
                'status' => 'pending',
            ]);

            foreach ($request->items as $item) {
                $odooProduct = collect($menus)->firstWhere('id', $item['menu_id']);
                if (!$odooProduct) continue;
                
                DetailFoodReservation::create([
                    'detail_food_reservation_id' => (string) Str::uuid(),
                    'food_reservation_id' => $foodReservation->food_reservation_id,
                    'menu_id' => (string)$item['menu_id'],
                    'quantity' => $item['quantity'],
                    'price' => $odooProduct['list_price'],
                    'subtotal' => $item['quantity'] * $odooProduct['list_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Setup Midtrans
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $params = [
                'transaction_details' => [
                    'order_id' => $foodReservationId,
                    'gross_amount' => $calculatedGrossAmount,
                ],
                'customer_details' => [
                    'first_name' => $reservation->customer_name ?? 'Guest',
                    'phone' => $reservation->customer_phone ?? '081234567890',
                ],
                'item_details' => $itemDetails,
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);

            Payment::create([
                'payment_id' => 'PAY-' . strtoupper(Str::random(10)),
                'food_reservation_id' => $foodReservation->food_reservation_id,
                'amount' => $grandTotal,
                'payment_method' => 'midtrans',
                'payment_status' => 'pending',
                'snap_token' => $snapToken,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'snapToken' => $snapToken,
                'reservationId' => $foodReservation->food_reservation_id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FOOD RESERVATION ERROR: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function midtransCallback(Request $request)
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $notification = new \Midtrans\Notification();

            $orderId = $notification->order_id;
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status ?? 'accept';

            $payment = Payment::where('food_reservation_id', $orderId)->first();
            $foodReservation = FoodReservation::with('details')->find($orderId);

            if (!$payment || !$foodReservation) {
                return response()->json(['message' => 'Not found'], 404);
            }

            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                if ($fraudStatus == 'accept' && $payment->payment_status !== 'success') {
                    $payment->payment_status = 'success';
                    $foodReservation->status = 'confirmed';
                    $payment->save();
                    $foodReservation->save();

                    // Push to Odoo
                    $this->syncToOdoo($foodReservation);
                }
            } else if ($transactionStatus == 'deny' || $transactionStatus == 'cancel' || $transactionStatus == 'expire') {
                $payment->payment_status = 'failed';
                $foodReservation->status = 'cancelled';
                $payment->save();
                $foodReservation->save();
            }

            return response()->json(['message' => 'Notification processed successfully']);

        } catch (\Exception $e) {
            Log::error('Midtrans callback error: ' . $e->getMessage());
            return response()->json(['message' => 'Error processing notification'], 500);
        }
    }

    protected function syncToOdoo($foodReservation)
    {
        try {
            $tableReservation = TableReservation::with('details')->where('reservation_id', $foodReservation->reservation_id)->first();
            
            if (!$tableReservation) return;

            // 1. Submit Table Reservation
            $tableIds = $tableReservation->details->pluck('table_id')->map(fn($id) => (int)$id)->toArray();
            
            $odooResId = $this->odooService->submitReservation([
                'customer_name' => $tableReservation->customer_name ?? 'Guest Customer',
                'customer_phone' => $tableReservation->customer_phone ?? '',
                'reservation_date' => \Carbon\Carbon::parse($tableReservation->reservation_time)->format('Y-m-d'),
                'time_start' => \Carbon\Carbon::parse($tableReservation->reservation_time)->format('H.i'),
                'time_end' => \Carbon\Carbon::parse($tableReservation->reservation_time)->addHours(1.5)->format('H.i'),
                'guest_count' => count($tableIds) * 2, // approximation if guests not saved
                'table_ids' => $tableIds,
                'notes' => 'Paid via Midtrans online.'
            ]);

            // 2. Submit POS Order
            $items = [];
            foreach ($foodReservation->details as $detail) {
                $items[] = [
                    'product_id' => (int) $detail->menu_id,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                ];
            }

            $this->odooService->submitOrder($odooResId, $items, $foodReservation->total_food_price);

            Log::info("Successfully synced reservation {$odooResId} to Odoo.");

        } catch (\Exception $e) {
            Log::error("Failed to sync to Odoo: " . $e->getMessage());
        }
    }
}