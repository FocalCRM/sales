<?php

declare(strict_types=1);

namespace Focal\Sales\Models;

use Carbon\CarbonInterface;
use Focal\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $category
 * @property string $framework
 * @property string|null $description
 * @property array<int, array{id: string, label: string, type: string, options?: list<string>, target_property?: string, help?: string}> $questions
 * @property bool $is_active
 * @property int|null $user_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model|null $user
 */
class SalesPlaybook extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'framework',
        'description',
        'questions',
        'is_active',
        'user_id',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('focal-sales.tables.playbooks', 'focal_sales_playbooks');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The author / manager who created this playbook.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Factory preset for BANT qualification playbook.
     *
     * @return array<string, mixed>
     */
    public static function defaultBantPreset(): array
    {
        return [
            'name' => 'BANT Qualification Playbook',
            'slug' => 'bant-qualification',
            'category' => 'qualification',
            'framework' => 'bant',
            'description' => 'Budget, Authority, Need, and Timeline discovery script.',
            'questions' => [
                [
                    'id' => 'budget_status',
                    'label' => 'Has budget been allocated or approved for this initiative?',
                    'type' => 'select',
                    'options' => ['Allocated & Approved', 'Informal Estimate', 'Needs Justification', 'No Budget'],
                    'target_property' => 'qualification_budget',
                ],
                [
                    'id' => 'authority_role',
                    'label' => 'What is the contact\'s role in the decision process?',
                    'type' => 'select',
                    'options' => ['Final Economic Buyer', 'Technical Evaluator', 'Internal Champion', 'End User'],
                    'target_property' => 'qualification_authority',
                ],
                [
                    'id' => 'core_pain',
                    'label' => 'What is the urgent business problem they are trying to solve?',
                    'type' => 'textarea',
                    'target_property' => 'qualification_need',
                ],
                [
                    'id' => 'target_timeline',
                    'label' => 'When do they need this solution implemented and live?',
                    'type' => 'select',
                    'options' => ['Immediately (< 30 days)', 'This Quarter (1-3 months)', 'Next Quarter (3-6 months)', 'Uncertain (> 6 months)'],
                    'target_property' => 'qualification_timeline',
                ],
            ],
            'is_active' => true,
        ];
    }

    /**
     * Factory preset for MEDDIC qualification playbook.
     *
     * @return array<string, mixed>
     */
    public static function defaultMeddicPreset(): array
    {
        return [
            'name' => 'MEDDIC Enterprise Playbook',
            'slug' => 'meddic-enterprise',
            'category' => 'qualification',
            'framework' => 'meddic',
            'description' => 'Metrics, Economic Buyer, Decision Criteria, Decision Process, Identify Pain, Champion.',
            'questions' => [
                ['id' => 'metrics', 'label' => 'Quantifiable business metrics (e.g. 20% cost reduction)?', 'type' => 'text', 'target_property' => 'meddic_metrics'],
                ['id' => 'economic_buyer', 'label' => 'Who owns the P&L and has final sign-off authority?', 'type' => 'text', 'target_property' => 'meddic_economic_buyer'],
                ['id' => 'decision_criteria', 'label' => 'Technical, vendor, and financial criteria required?', 'type' => 'textarea', 'target_property' => 'meddic_decision_criteria'],
                ['id' => 'decision_process', 'label' => 'Step-by-step evaluation, security review, and legal flow?', 'type' => 'textarea', 'target_property' => 'meddic_decision_process'],
                ['id' => 'pain_point', 'label' => 'Cost of inaction if they do nothing?', 'type' => 'textarea', 'target_property' => 'meddic_pain'],
                ['id' => 'champion', 'label' => 'Who is actively selling on our behalf internally?', 'type' => 'text', 'target_property' => 'meddic_champion'],
            ],
            'is_active' => true,
        ];
    }
}
