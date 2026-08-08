<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number');
            $table->enum('status', ['available', 'sold'])->default('available');
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
        });

        // Global uniqueness among LIVE rows only (SQLite partial unique index):
        // soft-deleted serial numbers may be re-added.
        DB::statement('CREATE UNIQUE INDEX product_serials_serial_number_unique ON product_serials (serial_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS product_serials_serial_number_unique');
        Schema::dropIfExists('product_serials');
    }
};
