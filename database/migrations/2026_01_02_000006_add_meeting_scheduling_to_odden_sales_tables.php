<?php

declare(strict_types=1);

use Odden\Core\Support\UserModel;
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
        $meetingLinksTable = config('odden-sales.tables.meeting_links', 'odden_sales_meeting_links');
        $bookingsTable = config('odden-sales.tables.meeting_bookings', 'odden_sales_meeting_bookings');
        $contactsTable = config('odden-core.tables.contacts', 'odden_contacts');

        Schema::table($meetingLinksTable, function (Blueprint $table): void {
            $table->unsignedInteger('buffer_minutes')->default(0)->after('duration_minutes');
            $table->string('timezone', 64)->nullable()->after('working_hours');
        });

        Schema::create($bookingsTable, function (Blueprint $table) use ($meetingLinksTable, $contactsTable): void {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('meeting_link_id')->constrained($meetingLinksTable)->cascadeOnDelete();
            $table->foreignIdFor(UserModel::className(), 'user_id')->index();
            $table->foreignId('contact_id')->nullable()->constrained($contactsTable)->nullOnDelete();
            $table->unsignedBigInteger('activity_id')->nullable()->index();
            $table->string('invitee_name');
            $table->string('invitee_email');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('odden-sales.tables.meeting_bookings', 'odden_sales_meeting_bookings'));

        Schema::table(config('odden-sales.tables.meeting_links', 'odden_sales_meeting_links'), function (Blueprint $table): void {
            $table->dropColumn(['buffer_minutes', 'timezone']);
        });
    }
};
