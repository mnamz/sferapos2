<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            // Why a manager asked to delete this serial (shown to approvers here and at HQ).
            $table->text('deletion_reason')->nullable()->after('deletion_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropColumn('deletion_reason');
        });
    }
};
