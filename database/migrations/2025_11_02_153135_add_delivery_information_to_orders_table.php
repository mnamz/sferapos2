<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_name')->nullable()->after('customer_id');
            $table->string('delivery_company_name')->nullable()->after('delivery_name');
            $table->text('delivery_address')->nullable()->after('delivery_company_name');
            $table->string('delivery_phone')->nullable()->after('delivery_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_name', 'delivery_company_name', 'delivery_address', 'delivery_phone']);
        });
    }
};
