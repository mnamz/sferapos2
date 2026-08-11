<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            // A pending serial-deletion request awaiting admin approval. Null =
            // no request. Purely additive; existing serials are untouched.
            $table->timestamp('deletion_requested_at')->nullable()->after('order_id');
            $table->foreignId('deletion_requested_by')->nullable()->after('deletion_requested_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deletion_requested_by');
            $table->dropColumn('deletion_requested_at');
        });
    }
};
