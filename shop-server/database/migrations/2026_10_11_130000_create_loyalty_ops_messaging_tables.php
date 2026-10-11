<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_my');
            $table->string('name_en');
            $table->unsignedBigInteger('min_points_earned')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained()->cascadeOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->bigInteger('points');
            $table->string('status', 32)->default('available');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_reliability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->string('reason');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->jsonb('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('product_rating')->nullable();
            $table->unsignedTinyInteger('seller_rating')->nullable();
            $table->unsignedTinyInteger('delivery_rating')->nullable();
            $table->text('body')->nullable();
            $table->string('moderation_status', 32)->default('visible');
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('percent', 5, 2)->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('period_start');
            $table->string('period_end');
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('commission')->default(0);
            $table->unsignedBigInteger('net_payout')->default(0);
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('commission');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('code')->unique();
            $table->string('name');
            $table->string('locale', 8)->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('variables')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('message_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->foreignId('template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->string('audience_type', 32)->default('manual_excel');
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->jsonb('stats')->nullable();
            $table->timestamps();
        });

        Schema::create('message_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('message_campaigns')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->jsonb('variables')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('provider_ref')->nullable();
            $table->timestamps();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('message_campaigns')->nullOnDelete();
            $table->string('direction', 16)->default('outbound');
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->text('body_rendered');
            $table->string('status', 32)->default('queued');
            $table->string('provider')->nullable();
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->index(['channel', 'created_at']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16);
            $table->unsignedBigInteger('value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('targets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('message_campaign_recipients');
        Schema::dropIfExists('message_campaigns');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('customer_reliability_events');
        Schema::dropIfExists('point_ledgers');
        Schema::dropIfExists('tier_histories');
        Schema::dropIfExists('tiers');
    }
};
