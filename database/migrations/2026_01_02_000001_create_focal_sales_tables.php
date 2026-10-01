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
        $historyTable = config('focal-sales.tables.stage_history', 'focal_deal_stage_history');

        Schema::create($pipelinesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($stagesTable, function (Blueprint $table) use ($pipelinesTable): void {
            $table->id();
            $table->foreignId('pipeline_id')->constrained($pipelinesTable)->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('probability')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_closed_won')->default(false)->index();
            $table->boolean('is_closed_lost')->default(false)->index();
            $table->timestamps();

            $table->index(['pipeline_id', 'sort_order']);
            $table->unique(['pipeline_id', 'code']);
        });

        Schema::create($dealsTable, function (Blueprint $table) use ($pipelinesTable, $stagesTable): void {
            $table->id();
            $table->foreignId('pipeline_id')->constrained($pipelinesTable)->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained($stagesTable)->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->default('open')->index();
            $table->date('expected_close_date')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->text('lost_reason')->nullable();
            $table->json('properties')->nullable();
            $table->foreignIdFor(UserModel::className(), 'owner_id')->nullable()->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_id', 'stage_id']);
            $table->index(['pipeline_id', 'status']);
        });

        Schema::create($historyTable, function (Blueprint $table) use ($dealsTable, $stagesTable): void {
            $table->id();
            $table->foreignId('deal_id')->constrained($dealsTable)->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained($stagesTable)->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained($stagesTable)->cascadeOnDelete();
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->index();
            $table->unsignedBigInteger('duration_in_stage_seconds')->nullable();
            $table->timestamp('entered_at')->index();
            $table->timestamp('exited_at')->nullable()->index();
            $table->timestamps();

            $table->index(['deal_id', 'entered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('focal-sales.tables.stage_history', 'focal_deal_stage_history'));
        Schema::dropIfExists(config('focal-sales.tables.deals', 'focal_deals'));
        Schema::dropIfExists(config('focal-sales.tables.stages', 'focal_pipeline_stages'));
        Schema::dropIfExists(config('focal-sales.tables.pipelines', 'focal_pipelines'));
    }
};
