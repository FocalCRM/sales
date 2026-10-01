<?php

declare(strict_types=1);

namespace Focal\Sales\Models;

use Carbon\CarbonInterface;
use Focal\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $title
 * @property int $duration_minutes
 * @property string|null $description
 * @property array<string, list<string>>|null $working_hours
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model $user
 */
class SalesMeetingLink extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'duration_minutes',
        'description',
        'working_hours',
        'is_active',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('focal-sales.tables.meeting_links', 'focal_sales_meeting_links');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'working_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The sales representative who owns this scheduler link.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }
}
