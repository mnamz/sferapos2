<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->enum('ar_ap_type', ['AR', 'AP'])->nullable()->after('type')->index();
            $table->string('party_name')->nullable()->after('ar_ap_type');
            $table->foreignId('party_id')->nullable()->after('party_name'); // could reference customers or suppliers; keep generic for now
            $table->date('due_date')->nullable()->after('date')->index();
            $table->boolean('is_payroll')->default(false)->after('description')->index();
            $table->string('reference')->nullable()->after('description'); // invoice/bill number
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn(['ar_ap_type', 'party_name', 'party_id', 'due_date', 'is_payroll', 'reference']);
        });
    }
};


