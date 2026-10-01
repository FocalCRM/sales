<?php

declare(strict_types=1);

namespace Focal\Sales\Actions;

use Focal\Core\Enums\ActivityType;
use Focal\Core\Models\Contact;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\SalesPlaybook;

class ExecuteSalesPlaybookAction
{
    /**
     * Submit answers for a sales playbook and synchronize to target properties.
     *
     * @param  array<string, mixed>  $answers
     */
    public function execute(Deal|Contact $target, SalesPlaybook $playbook, array $answers, int|string|null $userId = null): void
    {
        $summaryLines = [];
        $questions = $playbook->questions ?? [];

        foreach ($questions as $q) {
            $qId = $q['id'];
            $label = $q['label'];
            $targetProp = $q['target_property'] ?? null;
            $val = $answers[$qId] ?? null;

            if ($val !== null && $val !== '') {
                $displayVal = is_array($val) ? implode(', ', $val) : (string) $val;
                $summaryLines[] = "**{$label}**:\n{$displayVal}\n";

                if ($targetProp !== null) {
                    $target->setProperty($targetProp, $val);
                }
            }
        }

        $target->save();

        $body = "### Playbook Execution: {$playbook->name}\n\n".implode("\n", $summaryLines);

        $creatorId = $userId !== null ? (int) $userId : (auth()->id() !== null ? (int) auth()->id() : null);

        $target->logActivity(
            type: ActivityType::Note,
            title: "Playbook: {$playbook->name}",
            body: $body,
            creatorId: $creatorId
        );
    }
}
