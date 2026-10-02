<?php

declare(strict_types=1);

namespace Odden\Sales\Actions;

use Illuminate\Database\Eloquent\Collection;
use Odden\Sales\Enums\QuoteStatus;
use Odden\Sales\Enums\StageAutomationActionType;
use Odden\Sales\Exceptions\StageRequirementException;
use Odden\Sales\Models\Deal;
use Odden\Sales\Models\PipelineStage;
use Odden\Sales\Models\StageAutomation;

class ExecuteStageAutomationsAction
{
    /**
     * Run stage automation rules and enforce stage entry guards.
     *
     * @throws StageRequirementException
     */
    public function execute(Deal $deal, PipelineStage $stage, int|string|null $userId = null): void
    {
        /** @var Collection<int, StageAutomation> $automations */
        $automations = StageAutomation::query()
            ->where('stage_id', $stage->id)
            ->where('is_active', true)
            ->where('event_trigger', 'enter_stage')
            ->orderBy('sort_order')
            ->get();

        foreach ($automations as $automation) {
            $this->processRule($automation, $deal, $stage, $userId);
        }
    }

    protected function processRule(StageAutomation $automation, Deal $deal, PipelineStage $stage, int|string|null $userId = null): void
    {
        $payload = $automation->action_payload ?? [];

        switch ($automation->action_type) {
            case StageAutomationActionType::RequireAssociatedContact:
                if ($deal->contacts()->count() === 0) {
                    throw new StageRequirementException("Stage [{$stage->name}] requires at least one associated contact.");
                }
                break;

            case StageAutomationActionType::RequireDealProducts:
                if ($deal->products()->count() === 0) {
                    throw new StageRequirementException("Stage [{$stage->name}] requires at least one line item / product.");
                }
                break;

            case StageAutomationActionType::RequireActiveQuote:
                if (! $deal->quotes()->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Approved, QuoteStatus::Accepted])->exists()) {
                    throw new StageRequirementException("Stage [{$stage->name}] requires a prepared or sent quote.");
                }
                break;

            case StageAutomationActionType::RequireAcceptedQuote:
                if (! $deal->quotes()->where('status', QuoteStatus::Accepted)->exists()) {
                    throw new StageRequirementException("Stage [{$stage->name}] requires an accepted and signed quote.");
                }
                break;

            case StageAutomationActionType::CreateTask:
                $dueDays = isset($payload['due_days']) && is_numeric($payload['due_days']) ? (int) $payload['due_days'] : 2;
                $title = isset($payload['task_subject']) && is_string($payload['task_subject'])
                    ? $payload['task_subject']
                    : "Follow up: {$deal->name} in [{$stage->name}]";

                $deal->logTask(
                    title: $title,
                    dueAt: now()->addDays($dueDays),
                    body: "Automated task triggered upon entering {$stage->name}.",
                    creatorId: $userId !== null ? (int) $userId : null
                );
                break;

            case StageAutomationActionType::NotifyOwner:
                if ($deal->owner_id !== null) {
                    $deal->logNote(
                        title: "Deal entered [{$stage->name}]",
                        body: "Notification: {$deal->name} moved to {$stage->name}.",
                        creatorId: $userId !== null ? (int) $userId : null
                    );
                }
                break;
        }
    }
}
