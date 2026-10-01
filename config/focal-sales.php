<?php

declare(strict_types=1);

return [
    'tables' => [
        'pipelines' => 'focal_pipelines',
        'stages' => 'focal_pipeline_stages',
        'deals' => 'focal_deals',
        'stage_history' => 'focal_deal_stage_history',
        'products' => 'focal_deal_products',
        'quotes' => 'focal_quotes',
        'quote_items' => 'focal_quote_items',
        'automations' => 'focal_stage_automations',
        'quotas' => 'focal_sales_quotas',
        'email_templates' => 'focal_sales_email_templates',
        'sequences' => 'focal_sales_sequences',
        'sequence_enrollments' => 'focal_sales_sequence_enrollments',
        'playbooks' => 'focal_sales_playbooks',
        'meeting_links' => 'focal_sales_meeting_links',
        'lead_routing_rules' => 'focal_sales_lead_routing_rules',
    ],

    'default_currency' => env('FOCAL_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The public quote e-sign portal and meeting scheduler. The group accepts
    | a domain, prefix and middleware. Set "enabled" to false to register your
    | own routes instead; keep the focal.quotes.* and focal.meetings.* route
    | names, since quotes and meeting links are generated from them.
    |
    */
    'routes' => [
        'enabled' => (bool) env('FOCAL_SALES_ROUTES_ENABLED', true),

        'web' => [
            'domain' => env('FOCAL_SALES_DOMAIN'),
            'prefix' => env('FOCAL_SALES_PREFIX', ''),
            'middleware' => ['web'],
        ],
    ],
];
