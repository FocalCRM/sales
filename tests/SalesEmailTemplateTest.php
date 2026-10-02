<?php

declare(strict_types=1);

namespace Odden\Sales\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Odden\Sales\Models\SalesEmailTemplate;

class SalesEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_sales_email_template_with_merge_tags(): void
    {
        $template = SalesEmailTemplate::create([
            'name' => 'Demo Follow-up',
            'subject' => 'Next steps for {{ company.name }} - Odden CRM',
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

        $this->assertSame('Next steps for Acme Labs - Odden CRM', $rendered['subject']);
        $this->assertStringContainsString('Hi Sarah,', $rendered['body_html']);
        $this->assertStringContainsString('about Annual SaaS Plan.', $rendered['body_html']);
        $this->assertStringContainsString('The proposal total is $12,000.', $rendered['body_html']);
    }

    public function test_render_escapes_merge_values_in_body_html_but_not_subject(): void
    {
        $template = SalesEmailTemplate::create([
            'name' => 'Escaping',
            'subject' => 'Re: {{ deal.name }}',
            'body_html' => '<p>About {{deal.name}}</p>',
            'category' => 'follow_up',
            'is_shared' => true,
        ]);

        $rendered = $template->render(['deal.name' => 'R&D <b>Expansion</b>']);

        $this->assertSame('Re: R&D <b>Expansion</b>', $rendered['subject']);
        $this->assertSame('<p>About R&amp;D &lt;b&gt;Expansion&lt;/b&gt;</p>', $rendered['body_html']);
    }
}
