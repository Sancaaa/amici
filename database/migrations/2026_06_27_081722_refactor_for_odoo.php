<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop old tables no longer needed (managed by Odoo)
        Schema::dropIfExists('table_gallery');
        Schema::dropIfExists('menu_gallery');
        
        // Disable foreign keys temporarily for SQLite
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('menus');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('restaurants');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('roles');
        
        // 2. Modify table_reservations to use Odoo IDs (integers/strings) and add customer details
        Schema::table('table_reservation', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_phone')->nullable()->after('customer_name');
            // user_id can now be nullable for guest walk-ins
            $table->uuid('user_id')->nullable()->change();
            
            // restaurant_id was a foreign key, we just drop the index/fk and keep it as string/int
            // If using SQLite, dropping foreign keys might be tricky depending on version, 
            // but we disabled constraints above, so it shouldn't error when we drop the restaurants table.
        });

        // 3. Detail table reservations
        // Since we dropped 'tables', the foreign key is invalid. 
        // We will just recreate this table to be safe, or leave it since FKs are disabled.
        // It's a pivot table, let's just drop and recreate it to use integer for Odoo table_id.
        Schema::dropIfExists('detail_table_reservations');
        Schema::create('detail_table_reservations', function (Blueprint $table) {
            $table->uuid('detail_table_reservation_id')->primary();
            $table->string('table_id'); // Odoo ID
            $table->uuid('reservation_id');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Reverse operations are too complex for a complete architectural shift.
        // Left intentionally blank or minimal.
    }
};
