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
        'meeting_bookings' => 'focal_sales_meeting_bookings',
        'lead_routing_rules' => 'focal_sales_lead_routing_rules',
    ],

    'default_currency' => env('FOCAL_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | Every email Sales sends (sequence email steps and meeting confirmations)
    | is a queued mailable, so run a queue worker. Leave a value null to use
    | the app's default mailer, queue connection, queue, or "from" address.
    | With sequences.send_as_owner, sequence emails are sent from the rep who
    | enrolled the contact (or the sequence author); otherwise the rep is
    | only used as the reply-to address.
    |
    */
    'mail' => [
        'mailer' => env('FOCAL_SALES_MAILER'),
        'connection' => env('FOCAL_SALES_QUEUE_CONNECTION'),
        'queue' => env('FOCAL_SALES_MAIL_QUEUE'),

        'from' => [
            'address' => env('FOCAL_SALES_FROM_ADDRESS'),
            'name' => env('FOCAL_SALES_FROM_NAME'),
        ],

        'sequences' => [
            'send_as_owner' => (bool) env('FOCAL_SALES_SEND_AS_OWNER', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meeting links
    |--------------------------------------------------------------------------
    |
    | default_working_hours applies to meeting links whose working_hours is
    | empty. Keys are lowercase English day names; each value is a list of
    | "HH:MM-HH:MM" windows in the link's timezone. booking_window_days is
    | how far ahead visitors can book.
    |
    */
    'meetings' => [
        'default_working_hours' => [
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'wednesday' => ['09:00-17:00'],
            'thursday' => ['09:00-17:00'],
            'friday' => ['09:00-17:00'],
        ],

        'booking_window_days' => (int) env('FOCAL_SALES_BOOKING_WINDOW_DAYS', 60),
    ],

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
