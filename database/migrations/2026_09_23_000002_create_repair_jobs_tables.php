<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // received by
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();

            // Device
            $table->string('device_type', 30)->default('phone');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('imei')->nullable()->index();
            $table->string('color')->nullable();
            $table->string('passcode_type', 20)->default('none'); // none|pin|password|pattern
            $table->string('passcode')->nullable();
            $table->json('accessories')->nullable();
            $table->json('pre_checks')->nullable();
            $table->text('condition_notes')->nullable();

            // Job
            $table->text('issue');
            $table->text('diagnosis')->nullable();
            $table->string('status', 30)->default('received')->index();
            $table->string('priority', 10)->default('normal');
            $table->decimal('estimated_cost', 10, 2)->default(0);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->string('deposit_method', 30)->nullable();
            $table->unsignedInteger('warranty_days')->default(30);
            $table->timestamp('promised_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repair_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('item_type', 20)->default('service'); // part|service|product
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->unsignedInteger('warranty_days')->nullable();
            $table->timestamps();
        });

        Schema::create('repair_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->boolean('customer_visible')->default(false);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('repair_job_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->text('repair_terms')->nullable();
            $table->unsignedInteger('default_warranty_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn(['repair_terms', 'default_warranty_days']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('repair_job_id');
        });
        Schema::dropIfExists('repair_job_logs');
        Schema::dropIfExists('repair_job_items');
        Schema::dropIfExists('repair_jobs');
    }
};
