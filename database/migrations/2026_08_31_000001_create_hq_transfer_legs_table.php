<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local record of which transfer legs this branch has already applied to its
 * own inventory, so a failed confirm (which leaves the transfer pending at HQ)
 * cannot cause the next poll to apply the same leg twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hq_transfer_legs', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number');
            $table->string('leg'); // 'deduct' | 'add'
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['transfer_number', 'leg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hq_transfer_legs');
    }
};
