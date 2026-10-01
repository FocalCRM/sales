<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Sales\Models\SalesEmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_sales_email_template_with_merge_tags(): void
    {
        $template = SalesEmailTemplate::create([
            'name' => 'Demo Follow-up',
            'subject' => 'Next steps for {{ company.name }} - Focal CRM',
            'body_html' => '<p>Hi {{ contact.first_name }},</p><p>Great speaking with you about {{ deal.name }}. The proposal total is ${{ deal.amount }}.</p>',
            'category' => 'follow_up',
            'is_shared' => true,
        ]);

        $rendered = $template->render([
            'company.name' => 'Acme Labs',
            'contact.first_name' => 'Sarah',
            'deal.name' => 'Annual SaaS Plan',
            'deal.amount' => '12,000',
        ]);

        $this->assertSame('Next steps for Acme Labs - Focal CRM', $rendered['subject']);
        $this->assertStringContainsString('Hi Sarah,', $rendered['body_html']);
        $this->assertStringContainsString('about Annual SaaS Plan.', $rendered['body_html']);
        $this->assertStringContainsString('The proposal total is $12,000.', $rendered['body_html']);
    }
}
