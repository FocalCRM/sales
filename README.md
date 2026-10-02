# Focal Sales (`focalcrm/sales`)

> This is a read-only split of the [focalcrm/focal](https://github.com/focalcrm/focal) monorepo. Please open issues and pull requests there.

The revenue and deal acceleration engine for the Focal RevOps platform. Delivers multi-pipeline Kanban tracking, CPQ quoting, automated outbound cadences, stage gate enforcement, weighted revenue forecasting, quota attainment, and intelligent lead routing.

---

## Architecture & Capabilities

```
+-------------------------------------------------------------------------+
|                               FOCAL SALES                               |
|                                                                         |
|  +--------------------+   +--------------------+   +-----------------+  |
|  | Multi-Pipelines &  |   |  Stage Gates &     |   | CPQ Quoting &   |  |
|  | Kanban Stages      |   |  Rotting Watchdogs |   | Deal Products   |  |
|  +--------------------+   +--------------------+   +-----------------+  |
|             \                       |                       /           |
|              v                      v                      v            |
|       +---------------------------------------------------------+       |
|       |             Deal Health Scoring & Stage Engine          |       |
|       |     (Automated triggers, velocity audits, health 0-100) |       |
|       +---------------------------------------------------------+       |
|             |                       |                       |           |
|             v                       v                       v           |
|  +--------------------+   +--------------------+   +-----------------+  |
|  | Outbound Cadences  |   | Intelligent Lead   |   | Quotas, Velocity|  |
|  | & Sales Sequences  |   | Routing Strategies |   | & Forecasting   |  |
|  +--------------------+   +--------------------+   +-----------------+  |
+-------------------------------------------------------------------------+
```

### Core Features

- **Multi-Pipeline Deal Management:** Manage complex sales cycles across multiple pipelines (e.g., *Enterprise New Business*, *Self-Serve Expansion*, *Channel Partners*) with distinct stages and probabilities.
- **Stage Gate Enforcement:** Require specific fields or custom properties before advancing deals (e.g., require `decision_maker_identified` and `budget_confirmed` before moving to Proposal).
- **Deal Rotting & Health Scoring:** Automatic calculation of deal vitality (0–100) based on stage stall duration, recent rep touchpoints, and upcoming scheduled activities.
- **CPQ & Product Catalog:** Attach line-item products to deals with volume discounts, margin tracking, and auto-sync deal amounts. Generate web quotes with customer self-service acceptance.
- **Outbound Cadences (Sequences):** Multi-day cadences that schedule email, call, and LinkedIn steps for reps and log each step to the contact timeline. Email steps send the step's rendered template to the contact as a queued email and log it to the timeline.
- **Stage Automations:** Automatically trigger tasks, send internal alerts, update fields, or dispatch webhooks when deals transition across pipeline stages.
- **Intelligent Lead & Deal Routing:** Route inbound records to reps using `RoundRobin`, `Weighted`, `Territory`, or `SkillBased` routing rules.
- **Weighted Revenue Forecasting & Quotas:** Calculate real-time pipeline forecasts ($\sum \text{amount} \times \text{probability}$) and track rep quota attainment over monthly, quarterly, or annual periods.
- **Sales Playbooks & Meeting Scheduler:** Provide reps with structured qualification scripts while letting prospects book open slots (from each link's working hours, minus existing bookings) via personalized booking links, with queued confirmation emails and .ics invites.

---

## Installation

```bash
composer require focalcrm/sales
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=focal-sales-migrations
php artisan vendor:publish --tag=focal-sales-config
```

Run migrations:

```bash
php artisan migrate
```

---

## Quick Start & Code Examples

### 1. Advancing Deal Stages with Gate Enforcement

```php
use Focal\Sales\Actions\ChangeDealStageAction;
use Focal\Sales\Exceptions\StageRequirementException;

try {
    app(ChangeDealStageAction::class)->execute(
        deal: $deal,
        targetStage: $negotiationStage,
        actorId: auth()->id()
    );
} catch (StageRequirementException $e) {
    // Thrown if required stage gate fields (e.g., 'procurement_contact') are missing
    logger()->warning("Cannot advance deal {$deal->id}: " . $e->getMessage());
}
```

### 2. CPQ: Attaching Products & Generating Quotes

```php
use Focal\Sales\Actions\GenerateQuoteFromDealAction;
use Focal\Sales\Models\DealProduct;

// Attach products with discounts
DealProduct::create([
    'deal_id' => $deal->id,
    'name' => 'Enterprise Platform License',
    'unit_price' => 12000,
    'quantity' => 2,
    'discount_percent' => 10, // Line total: $21,600
]);

// Generate quote with secure acceptance token
$quote = app(GenerateQuoteFromDealAction::class)->execute(
    deal: $deal,
    expiresAt: now()->addDays(30)
);

echo "Quote URL: " . route('sales.quotes.view', ['token' => $quote->public_token]);
```

### 3. Outbound Cadences (Sales Sequences)

```php
use Focal\Sales\Actions\EnrollContactInSequenceAction;
use Focal\Sales\Models\SalesSequence;

$sequence = SalesSequence::where('name', 'Outbound Enterprise SDR')->first();

app(EnrollContactInSequenceAction::class)->execute(
    sequence: $sequence,
    contact: $contact,
    enrolledById: auth()->id()
);

// Progress pending sequence steps via scheduler
// Schedule it yourself: php artisan sales:process-cadences (and run a queue worker for email steps)
```

### 4. Pipeline Forecasting & Quota Attainment

```php
use Focal\Sales\Actions\CalculatePipelineForecastAction;
use Focal\Sales\Actions\CalculateQuotaAttainmentAction;
use Focal\Sales\Enums\QuotaPeriod;

// Weighted and unweighted pipeline forecast
$forecast = app(CalculatePipelineForecastAction::class)->execute(pipelineId: $pipeline->id);
// Returns: ['total_pipeline' => 450000, 'weighted_pipeline' => 215000, 'by_stage' => [...]]

// Rep quota progress
$attainment = app(CalculateQuotaAttainmentAction::class)->execute(
    userId: $salesRep->id,
    period: QuotaPeriod::Quarterly,
    date: now()
);
// Returns: ['quota' => 200000, 'closed_won' => 165000, 'percent' => 82.5]
```

### 5. Automated Lead Routing

```php
use Focal\Sales\Actions\RouteLeadAction;

$assignedRepId = app(RouteLeadAction::class)->execute(
    lead: $inboundContact,
    territory: 'EMEA',
    dealSize: 75000
);
```

---

## Routes

The public quote e-sign portal (`/quotes/{token}`) and meeting scheduler (`/meet/{slug}`) are registered in the `web` group with no prefix by default.

Configure them in `config/focal-sales.php` (publish with `php artisan vendor:publish --tag=focal-sales-config`) or through environment variables:

```env
FOCAL_SALES_PREFIX=sales                # /quotes/{token} becomes /sales/quotes/{token}
FOCAL_SALES_DOMAIN=deals.example.com     # optional
FOCAL_SALES_ROUTES_ENABLED=true
```

Each group also accepts `middleware`. To register the routes yourself, set `routes.enabled` to `false` and define routes with the same names (`focal.quotes.*`, `focal.meetings.*`), because models, emails and notifications generate links from those names.

---

## Data Models & Schema Reference

| Model | Table | Responsibility |
| :--- | :--- | :--- |
| `Deal` | `deals` | Sales opportunities with amount, stage, health score, rotting status, and expected close date. |
| `Pipeline` | `pipelines` | Sales pipelines organizing sequential stages. |
| `PipelineStage` | `pipeline_stages` | Individual stages with probabilities, rotting limits, and gate requirements. |
| `DealProduct` | `deal_products` | Line-item catalog items attached to deals. |
| `DealStageHistory` | `deal_stage_histories` | Stage entry/exit timestamps and velocity audit logging. |
| `Quote` | `quotes` | Formal proposals with expiration dates and public acceptance links. |
| `QuoteItem` | `quote_items` | Individual line items on a quote. |
| `SalesQuota` | `sales_quotas` | Revenue targets assigned to reps over defined calendar intervals. |
| `SalesSequence` | `sales_sequences` | Multi-touch outbound engagement cadences. |
| `SalesSequenceEnrollment`| `sales_sequence_enrollments` | Contact state tracking across cadence steps. |
| `SalesPlaybook` | `sales_playbooks` | Interactive question checklists and qualification objection handlers. |
| `SalesMeetingLink` | `sales_meeting_links` | Rep calendar booking links and meeting slot configurations. |
| `SalesMeetingBooking` | `sales_meeting_bookings` | Booked slots; active bookings block the rep's availability. |
| `LeadRoutingRule` | `lead_routing_rules` | Assignment rules mapping inbound criteria to rep pools. |
| `StageAutomation` | `stage_automations` | Actions triggered upon entering or exiting pipeline stages. |

---

## Testing

```bash
vendor/bin/pest packages/sales/tests --compact
```
