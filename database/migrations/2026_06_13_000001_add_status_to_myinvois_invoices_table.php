<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myinvois_invoices', function (Blueprint $table) {
            // active | cancelled | credited
            $table->string('status')->default('active')->after('invoice_code_number');
        });
    }

    public function down(): void
    {
        Schema::table('myinvois_invoices', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
