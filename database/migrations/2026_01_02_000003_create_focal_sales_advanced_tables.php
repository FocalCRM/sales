<?php

declare(strict_types=1);

use Focal\Core\Support\UserModel;
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
        $pipelinesTable = config('focal-sales.tables.pipelines', 'focal_pipelines');
        $stagesTable = config('focal-sales.tables.stages', 'focal_pipeline_stages');
        $dealsTable = config('focal-sales.tables.deals', 'focal_deals');
        $quotesTable = config('focal-sales.tables.quotes', 'focal_quotes');
        $quoteItemsTable = config('focal-sales.tables.quote_items', 'focal_quote_items');
        $automationsTable = config('focal-sales.tables.automations', 'focal_stage_automations');
        $quotasTable = config('focal-sales.tables.quotas', 'focal_sales_quotas');
        $templatesTable = config('focal-sales.tables.email_templates', 'focal_sales_email_templates');

        // 1. Quotes Table
        Schema::create($quotesTable, function (Blueprint $table) use ($dealsTable): void {
            $table->id();
            $table->foreignId('deal_id')->constrained($dealsTable)->cascadeOnDelete();
            $table->string('quote_number')->unique();
            $table->string('title');
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->date('expires_at')->nullable()->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->string('signed_by_name')->nullable();
            $table->string('signed_by_email')->nullable();
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deal_id', 'status']);
        });

        // 2. Quote Items Table
        Schema::create($quoteItemsTable, function (Blueprint $table) use ($quotesTable): void {
            $table->id();
            $table->foreignId('quote_id')->constrained($quotesTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quote_id', 'sort_order']);
        });

        // 3. Stage Automations Table
        Schema::create($automationsTable, function (Blueprint $table) use ($stagesTable): void {
            $table->id();
            $table->foreignId('stage_id')->constrained($stagesTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('event_trigger', 30)->default('enter_stage');
            $table->string('action_type', 40);
            $table->json('action_payload')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['stage_id', 'is_active']);
        });

        // 4. Sales Quotas Table
        Schema::create($quotasTable, function (Blueprint $table) use ($pipelinesTable): void {
            $table->id();
            $table->foreignIdFor(UserModel::className(), 'user_id')->index();
            $table->foreignId('pipeline_id')->nullable()->constrained($pipelinesTable)->nullOnDelete();
            $table->string('period_type', 20)->default('monthly');
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->decimal('target_amount', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['user_id', 'period_start', 'period_end']);
        });

        // 5. Sales Email Templates Table
        Schema::create($templatesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('body_html');
            $table->string('category', 40)->default('general')->index();
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->index();
            $table->boolean('is_shared')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('focal-sales.tables.email_templates', 'focal_sales_email_templates'));
        Schema::dropIfExists(config('focal-sales.tables.quotas', 'focal_sales_quotas'));
        Schema::dropIfExists(config('focal-sales.tables.automations', 'focal_stage_automations'));
        Schema::dropIfExists(config('focal-sales.tables.quote_items', 'focal_quote_items'));
        Schema::dropIfExists(config('focal-sales.tables.quotes', 'focal_quotes'));
    }
};
