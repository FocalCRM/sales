<?php

declare(strict_types=1);

namespace Odden\Sales\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Odden\Core\Support\UserModel;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array<int, array{step: int, type: string, delay_days: int, title: string, template_id?: int|null}> $steps
 * @property bool $is_active
 * @property int|null $user_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, SalesSequenceEnrollment> $enrollments
 */
class SalesSequence extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'steps',
        'is_active',
        'user_id',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('odden-sales.tables.sequences', 'odden_sales_sequences');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sequence enrollments.
     *
     * @return HasMany<SalesSequenceEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SalesSequenceEnrollment::class, 'sequence_id');
    }

    /**
     * The user / rep who created this cadence.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Total number of steps in this sequence.
     */
    public function totalSteps(): int
    {
        return count($this->steps ?? []);
    }
}
