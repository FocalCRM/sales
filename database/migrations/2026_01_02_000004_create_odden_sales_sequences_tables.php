<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Odden\Core\Support\UserModel;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sequencesTable = config('odden-sales.tables.sequences', 'odden_sales_sequences');
        $enrollmentsTable = config('odden-sales.tables.sequence_enrollments', 'odden_sales_sequence_enrollments');
        $contactsTable = config('odden-core.tables.contacts', 'odden_contacts');

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
        Schema::dropIfExists(config('odden-sales.tables.sequence_enrollments', 'odden_sales_sequence_enrollments'));
        Schema::dropIfExists(config('odden-sales.tables.sequences', 'odden_sales_sequences'));
    }
};
