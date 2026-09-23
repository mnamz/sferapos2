<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // product = retail item (stocked), part = spare part used in repairs
            // (stocked), service = labour / non-stock charge (never decrements).
            $table->string('type', 20)->default('product')->after('name');
            $table->string('sku')->nullable()->after('barcode');
            $table->string('brand')->nullable()->after('sku');
            $table->string('compatible_models')->nullable()->after('brand');
            $table->unsignedInteger('warranty_days')->nullable()->after('compatible_models');
            $table->index('type');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('product')->after('product_name');
            $table->unsignedInteger('warranty_days')->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'warranty_days']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'sku', 'brand', 'compatible_models', 'warranty_days']);
        });
    }
};
