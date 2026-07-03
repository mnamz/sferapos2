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
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('shop_address')->nullable()->change();
            $table->string('shop_phone')->nullable()->change();
            $table->string('shop_email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->string('shop_address')->nullable(false)->change();
            $table->string('shop_phone')->nullable(false)->change();
            $table->string('shop_email')->nullable(false)->change();
        });
    }
};
