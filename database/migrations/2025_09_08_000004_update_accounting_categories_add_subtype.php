<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_categories', function (Blueprint $table) {
            $table->enum('subtype', ['general', 'payroll', 'cogs'])->default('general')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_categories', function (Blueprint $table) {
            $table->dropColumn('subtype');
        });
    }
};


