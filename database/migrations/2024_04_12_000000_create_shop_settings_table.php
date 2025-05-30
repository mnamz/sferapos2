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
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name');
            $table->string('shop_address');
            $table->string('shop_phone');
            $table->string('shop_email');
            $table->string('currency', 3)->default('USD');
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->string('logo_path')->nullable();
            $table->string('invoice_logo_path')->nullable();
            $table->string('company_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('payment_details')->nullable();
            
            // Default methods and costs
            $table->string('default_payment_method')->default('cash');
            $table->string('default_delivery_method')->default('pickup');
            $table->decimal('default_delivery_cost', 10, 2)->default(0.00);
            
            // Receipt customization
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->text('footer_text')->nullable();
            
            // System settings
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i:s');
            $table->integer('low_stock_threshold')->default(10);
            
            // Feature flags
            $table->boolean('enable_guest_checkout')->default(true);
            $table->boolean('enable_tax')->default(true);
            $table->boolean('enable_delivery')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
}; 