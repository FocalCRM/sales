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
        $sequencesTable = config('focal-sales.tables.sequences', 'focal_sales_sequences');
        $enrollmentsTable = config('focal-sales.tables.sequence_enrollments', 'focal_sales_sequence_enrollments');
        $contactsTable = config('focal-core.tables.contacts', 'focal_contacts');

        Schema::create($sequencesTable, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('steps');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignIdFor(UserModel::className(), 'user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create($enrollmentsTable, function (Blueprint $table) use ($sequencesTable, $contactsTable): void {
            $table->id();
            $table->foreignId('sequence_id')->constrained($sequencesTable)->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained($contactsTable)->cascadeOnDelete();
            $table->integer('current_step')->default(1);
            $table->string('status', 20)->default('active')->index();
            $table->date('next_step_due_at')->nullable()->index();
            $table->unsignedBigInteger('enrolled_by_id')->nullable()->index();
            $table->timestamps();

            $table->index(['sequence_id', 'status']);
            $table->index(['contact_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('focal-sales.tables.sequence_enrollments', 'focal_sales_sequence_enrollments'));
        Schema::dropIfExists(config('focal-sales.tables.sequences', 'focal_sales_sequences'));
    }
};
