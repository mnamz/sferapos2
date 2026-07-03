<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tangent_sales_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('receipt_count')->default(0);
            $table->decimal('gto', 12, 2)->default(0);
            $table->decimal('gst', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->unsignedInteger('no_of_pax')->default(0);
            $table->decimal('cash', 12, 2)->default(0);
            $table->decimal('tng', 12, 2)->default(0);
            $table->decimal('visa', 12, 2)->default(0);
            $table->decimal('mastercard', 12, 2)->default(0);
            $table->decimal('amex', 12, 2)->default(0);
            $table->decimal('voucher', 12, 2)->default(0);
            $table->decimal('others_amount', 12, 2)->default(0);
            $table->char('gst_registered', 1)->default('N');
            $table->string('payload_hash')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->unique(['sale_date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tangent_sales_hourly');
    }
};
