<?php

declare(strict_types=1);

namespace Odden\Sales\Listeners;

use Odden\Core\Events\ContactsMerged;
use Illuminate\Support\Facades\DB;

/**
 * Moves the Sales records keyed to a merged-away contact onto the contact it was merged into.
 * Runs synchronously inside Core's merge transaction, so a failure rolls the merge back.
 *
 * Deals need nothing here: they're linked through Core associations, which Core has already moved.
 */
class MoveMergedRecords
{
    public function handleContactsMerged(ContactsMerged $event): void
    {
        $from = $event->secondary->getKey();
        $to = $event->primary->getKey();

        $this->resolveEnrollmentConflicts($from, $to);

        foreach (['sequence_enrollments', 'meeting_bookings'] as $key) {
            DB::table((string) config("odden-sales.tables.{$key}", "odden_sales_{$key}"))
                ->where('contact_id', $from)
                ->update(['contact_id' => $to]);
        }
    }

    /**
     * A contact has one enrollment per sequence (EnrollContactInSequenceAction upserts on it), so
     * when both contacts are in the same sequence keep one: a finished or stopped enrollment wins
     * over an active one, so outreach is never restarted; otherwise the further-along one, then
     * the older one.
     */
    private function resolveEnrollmentConflicts(mixed $from, mixed $to): void
    {
        $table = (string) config('odden-sales.tables.sequence_enrollments', 'odden_sales_sequence_enrollments');

        $sequenceIds = DB::table($table)
            ->where('contact_id', $from)
            ->whereIn('sequence_id', DB::table($table)->where('contact_id', $to)->select('sequence_id'))
            ->distinct()
            ->pluck('sequence_id');

        foreach ($sequenceIds as $sequenceId) {
            $ids = DB::table($table)
                ->where('sequence_id', $sequenceId)
                ->whereIn('contact_id', [$from, $to])
                ->orderByRaw("CASE WHEN status = 'active' THEN 1 ELSE 0 END")
                ->orderByDesc('current_step')
                ->orderBy('id')
                ->pluck('id');

            DB::table($table)->whereIn('id', $ids->slice(1)->all())->delete();
        }
    }
}
