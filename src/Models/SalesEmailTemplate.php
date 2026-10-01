<?php

declare(strict_types=1);

namespace Focal\Sales\Models;

use Carbon\CarbonInterface;
use Focal\Core\Models\Contact;
use Focal\Core\Support\UserModel;
use Focal\Sales\Services\TemplateParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $body_html
 * @property string $category
 * @property int|null $user_id
 * @property bool $is_shared
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class SalesEmailTemplate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subject',
        'body_html',
        'category',
        'user_id',
        'is_shared',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('focal-sales.tables.email_templates', 'focal_sales_email_templates');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
        ];
    }

    /**
     * The user who created this template.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = UserModel::className();

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Render the template by replacing merge tags with values.
     *
     * @param  array<string, string>  $variables
     * @return array{subject: string, body_html: string}
     */
    public function render(array $variables = []): array
    {
        $subject = $this->subject;
        $body = $this->body_html;

        foreach ($variables as $key => $value) {
            $tag = '{{ '.$key.' }}';
            $subject = str_replace($tag, $value, $subject);
            $body = str_replace($tag, $value, $body);

            // Also support without spaces: {{key}}
            $tagNoSpaces = '{{'.$key.'}}';
            $subject = str_replace($tagNoSpaces, $value, $subject);
            $body = str_replace($tagNoSpaces, $value, $body);
        }

        return [
            'subject' => $subject,
            'body_html' => $body,
        ];
    }

    /**
     * Render with full CRM models context (Contact, Deal, User).
     *
     * @param  array<string, mixed>  $extra
     * @return array{subject: string, body_html: string}
     */
    public function renderWithContext(?Contact $contact = null, ?Deal $deal = null, ?Model $user = null, array $extra = []): array
    {
        $parser = app(TemplateParser::class);
        $context = $parser->buildContext($contact, $deal, $user, $extra);

        return [
            'subject' => $parser->parse($this->subject, $context),
            'body_html' => $parser->parse($this->body_html, $context),
        ];
    }
}
