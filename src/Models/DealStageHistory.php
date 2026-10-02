<?php

declare(strict_types=1);

namespace Odden\Sales\Models;

use Carbon\CarbonInterface;
use Odden\Core\Support\UserModel;
use Odden\Sales\Database\Factories\DealStageHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deal_id
 * @property int|null $from_stage_id
 * @property int $to_stage_id
 * @property int|null $user_id
 * @property int|null $duration_in_stage_seconds
 * @property CarbonInterface $entered_at
 * @property CarbonInterface|null $exited_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class DealStageHistory extends Model
{
    /** @use HasFactory<DealStageHistoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'deal_id',
        'from_stage_id',
        'to_stage_id',
        'user_id',
        'duration_in_stage_seconds',
        'entered_at',
        'exited_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('odden-sales.tables.stage_history', 'odden_deal_stage_history');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_in_stage_seconds' => 'integer',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    /**
     * The deal this stage history belongs to.
     *
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    /**
     * The stage the deal moved from.
     *
     * @return BelongsTo<PipelineStage, $this>
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /**
     * The stage the deal moved to.
     *
     * @return BelongsTo<PipelineStage, $this>
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /**
     * The user who transitioned the stage.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): DealStageHistoryFactory
    {
        return DealStageHistoryFactory::new();
    }
}
