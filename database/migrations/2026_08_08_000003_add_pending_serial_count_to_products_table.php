<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Units that existed as anonymous stock when serial tracking was enabled
            // and still need serial numbers keyed in. A reminder counter only — it
            // does not affect sellability (that is governed by available serials).
            $table->unsignedInteger('pending_serial_count')->default(0)->after('serial_tracked');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pending_serial_count');
        });
    }
};
