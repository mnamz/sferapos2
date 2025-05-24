<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            
            // Add payment related fields
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            
            // Add delivery and payment method fields
            $table->string('delivery_method')->default('pickup'); // pickup, delivery, etc.
            $table->string('payment_method')->default('cash'); // cash, card, etc.
            
            // Add remarks field
            $table->text('remarks')->nullable();
            
            // Add status field if not exists
            if (!Schema::hasColumn('orders', 'status')) {
                $table->string('status')->default('pending'); // pending, processing, completed, etc.
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'paid_amount',
                'due_amount',
                'delivery_cost',
                'delivery_method',
                'payment_method',
                'remarks',
                'status'
            ]);
        });
    }
}; 