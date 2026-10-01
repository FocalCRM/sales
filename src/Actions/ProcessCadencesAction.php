<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Focal\Core\Enums\ActivityStatus;
use Focal\Core\Enums\ActivityType;
use Focal\Core\Enums\LeadStatus;
use Focal\Core\Models\Activity;
use Focal\Sales\Models\SalesEmailTemplate;
use Focal\Sales\Models\SalesSequenceEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ProcessCadencesAction
{
    /**
     * Process due steps across all active sales sequence enrollments.
     *
     * @return array{
     *     processed: int,
     *     emails_sent: int,
     *     tasks_created: int,
     *     unenrolled: int,
     *     completed: int
     * }
     */
    public function execute(): array
    {
        $todayStr = Carbon::today()->toDateString();

        $stats = [
            'processed' => 0,
            'emails_sent' => 0,
            'tasks_created' => 0,
            'unenrolled' => 0,
            'completed' => 0,
        ];

        /** @var Collection<int, SalesSequenceEnrollment> $dueEnrollments */
        $dueEnrollments = SalesSequenceEnrollment::query()
            ->where('status', 'active')
            ->where(function ($q) use ($todayStr): void {
                $q->whereNull('next_step_due_at')
                    ->orWhereDate('next_step_due_at', '<=', $todayStr);
            })
            ->with(['contact.companies', 'sequence'])
            ->get();

        foreach ($dueEnrollments as $enrollment) {
            $contact = $enrollment->contact;
            $sequence = $enrollment->sequence;

            if (! $sequence->is_active) {
                continue;
            }

            $stats['processed']++;

            // Auto-exit if contact has already become a Customer or has bad timing/unqualified
            if ($contact->lead_status === LeadStatus::Unqualified || $contact->lead_status === LeadStatus::BadTiming) {
                $enrollment->update([
                    'status' => 'unenrolled',
                    'next_step_due_at' => null,
                ]);
                $stats['unenrolled']++;

                continue;
            }

            $steps = $sequence->steps ?? [];
            $stepIndex = $enrollment->current_step - 1;
            $stepDef = $steps[$stepIndex] ?? null;

            if ($stepDef === null) {
                $enrollment->update([
                    'status' => 'completed',
                    'next_step_due_at' => null,
                ]);
                $stats['completed']++;

                continue;
            }

            $stepType = $stepDef['type'];
            $stepTitle = $stepDef['title'];

            if ($stepType === 'email') {
                $templateId = $stepDef['template_id'] ?? null;

                $subject = "Outbound: {$stepTitle}";
                $body = "Automated email sent via cadence [{$sequence->name}].";

                if ($templateId !== null) {
                    /** @var SalesEmailTemplate|null $template */
                    $template = SalesEmailTemplate::find($templateId);
                    if ($template !== null) {
                        $rendered = $template->renderWithContext($contact);
                        $subject = $rendered['subject'];
                        $body = $rendered['body_html'];
                    }
                }

                $contact->logActivity(
                    type: ActivityType::Email,
                    title: $subject,
                    body: $body,
                    status: ActivityStatus::Completed,
                    creatorId: $enrollment->enrolled_by_id
                );

                $contact->markContacted();

                if ($contact->lead_status === LeadStatus::New) {
                    $contact->updateQuietly(['lead_status' => LeadStatus::InProgress]);
                }

                $enrollment->advanceStep();
                $stats['emails_sent']++;
            } else {
                // Manual action: Call, LinkedIn, or Task
                $actType = match ($stepType) {
                    'call' => ActivityType::Call,
                    'linkedin' => ActivityType::LinkedIn,
                    default => ActivityType::Task,
                };

                // Check if existing activity was already created for this enrollment step
                /** @var Activity|null $existingActivity */
                $existingActivity = Activity::query()
                    ->where('subject_type', $contact->getMorphClass())
                    ->where('subject_id', $contact->id)
                    ->where('type', $actType)
                    ->where(function ($q) use ($enrollment, $sequence): void {
                        $q->where('metadata->sequence_enrollment_id', $enrollment->id)
                            ->where('metadata->step', $enrollment->current_step)
                            ->orWhere('body', 'like', "%Cadence [{$sequence->name}] Step {$enrollment->current_step}%");
                    })
                    ->latest('id')
                    ->first();

                if ($existingActivity !== null) {
                    if ($existingActivity->status === ActivityStatus::Completed) {
                        // Rep finished manual step - advance sequence to next step
                        $contact->markContacted();
                        $enrollment->advanceStep();
                    }
                    // If still pending, wait for rep completion
                } else {
                    $contact->logActivity(
                        type: $actType,
                        title: "{$actType->label()}: {$stepTitle}",
                        body: "Cadence [{$sequence->name}] Step {$enrollment->current_step}",
                        metadata: [
                            'sequence_enrollment_id' => $enrollment->id,
                            'step' => $enrollment->current_step,
                        ],
                        status: ActivityStatus::Pending,
                        dueAt: now(),
                        creatorId: $enrollment->enrolled_by_id
                    );
                    $stats['tasks_created']++;
                }
            }
        }

        return $stats;
    }
}
