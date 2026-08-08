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

        // Enforce global uniqueness among LIVE (non-soft-deleted) rows only, so a
        // soft-deleted serial number can later be re-added. The technique differs
        // by driver because neither engine's plain unique index does this directly:
        //  - SQLite: a partial unique index (WHERE deleted_at IS NULL).
        //  - MySQL/MariaDB: no partial indexes, so use a STORED generated column that
        //    holds the serial number only while the row is live (NULL once deleted),
        //    with a unique index on it — the engine allows many NULLs, so deleted
        //    rows never collide.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX product_serials_serial_number_unique ON product_serials (serial_number) WHERE deleted_at IS NULL');
        } else {
            DB::statement('ALTER TABLE product_serials ADD COLUMN serial_active VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN serial_number ELSE NULL END) STORED');
            DB::statement('CREATE UNIQUE INDEX product_serials_serial_number_unique ON product_serials (serial_active)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS product_serials_serial_number_unique');
        } else {
            DB::statement('DROP INDEX product_serials_serial_number_unique ON product_serials');
            DB::statement('ALTER TABLE product_serials DROP COLUMN serial_active');
        }

        Schema::dropIfExists('product_serials');
    }
};
