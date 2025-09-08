<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->date('date')->index();
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['credit', 'debit'])->index();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entries');
    }
};


