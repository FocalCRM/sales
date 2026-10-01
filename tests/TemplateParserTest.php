<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use App\Models\User;
use Focal\Core\Enums\LeadStatus;
use Focal\Core\Models\Company;
use Focal\Core\Models\Contact;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Pipeline;
use Focal\Sales\Services\TemplateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_parse_contact_deal_and_user_merge_tags(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'job_title' => 'VP of Security',
            'lead_status' => LeadStatus::Open,
        ]);

        $company = Company::factory()->create([
            'name' => 'Cyberdyne Systems',
            'industry' => 'Defense Systems',
        ]);
        $contact->companies()->attach($company->id, ['parent_type' => $contact->getMorphClass(), 'child_type' => $company->getMorphClass()]);

        $pipeline = Pipeline::factory()->withStages()->create();
        $deal = Deal::factory()->create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $pipeline->stages->first()->id,
            'name' => 'Enterprise Defense System',
            'amount' => 75000.00,
        ]);

        $user = User::factory()->create([
            'name' => 'John Connor',
            'email' => 'john@resistance.org',
        ]);

        $parser = new TemplateParser;
        $context = $parser->buildContext($contact, $deal, $user);

        $template = 'Hi {{ contact.first_name }}, regarding {{ deal.name }} (${{ deal.amount }}) at {{ company.name }}, best regards {{ sender.name }}.';
        $rendered = $parser->parse($template, $context);

        $this->assertSame('Hi Sarah, regarding Enterprise Defense System ($75000.00) at Cyberdyne Systems, best regards John Connor.', $rendered);
    }
}
