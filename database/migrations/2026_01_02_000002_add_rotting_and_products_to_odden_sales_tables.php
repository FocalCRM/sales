<?php

declare(strict_types=1);

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
        $stagesTable = config('odden-sales.tables.stages', 'odden_pipeline_stages');
        $dealsTable = config('odden-sales.tables.deals', 'odden_deals');
        $productsTable = config('odden-sales.tables.products', 'odden_deal_products');

        Schema::table($stagesTable, function (Blueprint $table): void {
            $table->unsignedInteger('rot_after_days')->nullable()->after('is_closed_lost');
        });

        Schema::table($dealsTable, function (Blueprint $table): void {
            $table->text('lost_notes')->nullable()->after('lost_reason');
        });

        Schema::create($productsTable, function (Blueprint $table) use ($dealsTable): void {
            $table->id();
            $table->foreignId('deal_id')->constrained($dealsTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('discount_percent', 5, 2)->default(0.00);
            $table->decimal('total_price', 15, 2)->default(0.00);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deal_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $stagesTable = config('odden-sales.tables.stages', 'odden_pipeline_stages');
        $dealsTable = config('odden-sales.tables.deals', 'odden_deals');
        $productsTable = config('odden-sales.tables.products', 'odden_deal_products');

        Schema::dropIfExists($productsTable);

        Schema::table($dealsTable, function (Blueprint $table): void {
            $table->dropColumn('lost_notes');
        });

        Schema::table($stagesTable, function (Blueprint $table): void {
            $table->dropColumn('rot_after_days');
        });
    }
};
