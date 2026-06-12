<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myinvois_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('myinvois_invoice_id')->constrained('myinvois_invoices')->onDelete('cascade');
            $table->string('submission_uid')->nullable();
            $table->string('uuid')->nullable();
            $table->string('credit_note_code_number')->nullable();
            $table->text('reason');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myinvois_credit_notes');
    }
};
