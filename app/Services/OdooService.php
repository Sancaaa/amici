<?php

namespace App\Services;

use Ripcord\Ripcord;
use Exception;

class OdooService
{
    protected $url;
    protected $db;
    protected $username;
    protected $password;
    protected $uid;
    protected $models;

    public function __construct()
    {
        $this->url = env('ODOO_URL', 'http://localhost:8069');
        $this->db = env('ODOO_DB', 'foodcourt');
        $this->username = env('ODOO_USERNAME', 'admin');
        $this->password = env('ODOO_PASSWORD', 'admin');

        $this->authenticate();
    }

    /**
     * Authenticate with Odoo and get the user ID (uid).
     */
    protected function authenticate()
    {
        try {
            $common = Ripcord::client("{$this->url}/xmlrpc/2/common");
            $this->uid = $common->authenticate($this->db, $this->username, $this->password, []);
            
            if (!$this->uid) {
                throw new Exception("Odoo Authentication Failed. Check your credentials.");
            }

            $this->models = Ripcord::client("{$this->url}/xmlrpc/2/object");
        } catch (Exception $e) {
            \Log::error("Odoo Connection Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute an XML-RPC call to Odoo.
     */
    public function execute($model, $method, $args = [], $kwargs = [])
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        return $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $method,
            $args,
            $kwargs
        );
    }

    /**
     * Get all active tenants.
     */
    public function getTenants()
    {
        $domain = [['state', '=', 'active']];
        $fields = ['id', 'name', 'code', 'cuisine_type', 'image'];
        
        $tenants = $this->execute('foodcourt.tenant', 'search_read', [$domain], [
            'fields' => $fields,
            'limit' => 100
        ]);

        return $tenants;
    }

    /**
     * Get products (menus) for a specific tenant.
     */
    public function getProducts($tenantId = null)
    {
        $domain = [['available_in_pos', '=', true]];
        
        if ($tenantId) {
            $domain[] = ['tenant_id', '=', (int)$tenantId];
        }

        $fields = ['id', 'name', 'list_price', 'tenant_id', 'image_1920'];
        
        $products = $this->execute('product.template', 'search_read', [$domain], [
            'fields' => $fields,
            'limit' => 200
        ]);

        return $products;
    }

    /**
     * Check available tables for a specific date and time.
     */
    public function getAvailableTables($date, $timeStart, $timeEnd)
    {
        // First, get all active tables
        $tables = $this->execute('restaurant.table', 'search_read', [[['active', '=', true]]], [
            'fields' => ['id', 'name', 'seats', 'floor_id']
        ]);

        // Then, find overlapping reservations
        $domain = [
            ['reservation_date', '=', $date],
            ['state', 'in', ['confirmed', 'checked_in']],
            ['time_start', '<', (float)$timeEnd],
            ['time_end', '>', (float)$timeStart],
        ];

        $overlappingReservations = $this->execute('foodcourt.reservation', 'search_read', [$domain], [
            'fields' => ['table_ids']
        ]);

        $reservedTableIds = [];
        foreach ($overlappingReservations as $res) {
            $reservedTableIds = array_merge($reservedTableIds, $res['table_ids']);
        }
        $reservedTableIds = array_unique($reservedTableIds);

        // Filter out reserved tables
        $availableTables = array_filter($tables, function($table) use ($reservedTableIds) {
            return !in_array($table['id'], $reservedTableIds);
        });

        return array_values($availableTables);
    }

    /**
     * Create a new reservation.
     */
    public function submitReservation($data)
    {
        // $data should contain: customer_name, customer_phone, reservation_date, time_start, time_end, guest_count, table_ids, notes
        $reservationId = $this->execute('foodcourt.reservation', 'create', [[
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? '',
            'reservation_date' => $data['reservation_date'],
            'time_start' => (float)$data['time_start'],
            'time_end' => (float)$data['time_end'],
            'guest_count' => (int)$data['guest_count'],
            'table_ids' => [[6, 0, $data['table_ids']]], // Odoo Many2many syntax: (6, 0, [ids])
            'notes' => $data['notes'] ?? '',
        ]]);

        return $reservationId;
    }

    /**
     * Submit an order to POS.
     * Note: pos.order usually requires a POS session. 
     * If the session is closed, Odoo might reject it. 
     * Alternatively, we can create a draft sale.order, but pos.order is fine if session is open.
     */
    public function submitOrder($reservationId, $items, $totalAmount)
    {
        // Minimal pos.order creation. 
        // In a real scenario, you need an active POS session ID.
        // We will fetch the first open POS session as a fallback, or configure it via env.
        
        $sessions = $this->execute('pos.session', 'search_read', [[['state', '=', 'opened']]], ['limit' => 1, 'fields' => ['id', 'config_id']]);
        if (empty($sessions)) {
            \Log::warning("No open POS session found for Odoo. Creating draft pos.order might fail.");
            $sessionId = false;
        } else {
            $sessionId = $sessions[0]['id'];
        }

        $orderLines = [];
        foreach ($items as $item) {
            $orderLines[] = [0, 0, [
                'product_id' => $item['product_id'],
                'qty' => $item['quantity'],
                'price_unit' => $item['price'],
                'price_subtotal' => $item['quantity'] * $item['price'],
                'price_subtotal_incl' => $item['quantity'] * $item['price'],
            ]];
        }

        $orderData = [
            'session_id' => $sessionId,
            'amount_total' => $totalAmount,
            'amount_paid' => $totalAmount,
            'amount_return' => 0,
            'amount_tax' => 0,
            'lines' => $orderLines,
            'note' => 'Online Order from Frontend. Reservation ID: ' . $reservationId,
        ];

        $orderId = $this->execute('pos.order', 'create', [$orderData]);
        return $orderId;
    }
}
