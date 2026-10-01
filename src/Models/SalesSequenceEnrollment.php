<?php

declare(strict_types=1);

namespace Focal\Sales\Models;

use Carbon\CarbonInterface;
use Focal\Core\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sequence_id
 * @property int $contact_id
 * @property int $current_step
 * @property string $status
 * @property CarbonInterface|null $next_step_due_at
 * @property int|null $enrolled_by_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SalesSequence $sequence
 * @property-read Contact $contact
 */
class SalesSequenceEnrollment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sequence_id',
        'contact_id',
        'current_step',
        'status',
        'next_step_due_at',
        'enrolled_by_id',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('focal-sales.tables.sequence_enrollments', 'focal_sales_sequence_enrollments');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'next_step_due_at' => 'date',
        ];
    }

    /**
     * The sequence this enrollment belongs to.
     *
     * @return BelongsTo<SalesSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(SalesSequence::class, 'sequence_id');
    }

    /**
     * The contact enrolled in this sequence.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Advance to the next sequence step or complete if on final step.
     */
    public function advanceStep(): self
    {
        $totalSteps = $this->sequence->totalSteps();

        if ($this->current_step >= $totalSteps) {
            $this->update([
                'status' => 'completed',
                'next_step_due_at' => null,
            ]);

            return $this;
        }

        $nextStepIndex = $this->current_step; // 0-based for the next step
        $steps = $this->sequence->steps ?? [];
        $nextStepDef = $steps[$nextStepIndex] ?? null;
        $delayDays = $nextStepDef !== null ? $nextStepDef['delay_days'] : 1;

        $this->update([
            'current_step' => $this->current_step + 1,
            'next_step_due_at' => now()->addDays($delayDays)->toDateString(),
        ]);

        return $this;
    }
}
