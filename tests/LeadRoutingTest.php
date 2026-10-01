<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Enums\ActivityType;
use Focal\Core\Enums\LeadStatus;
use Focal\Core\Models\Contact;
use Focal\Sales\Actions\RouteLeadAction;
use Focal\Sales\Enums\LeadRoutingStrategy;
use Focal\Sales\Models\LeadRoutingRule;
use Focal\Sales\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeadRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_route_leads_using_round_robin_strategy(): void
    {
        $rep1 = User::factory()->create(['name' => 'Alice']);
        $rep2 = User::factory()->create(['name' => 'Bob']);

        LeadRoutingRule::query()->create([
            'name' => 'Inbound Round Robin',
            'strategy' => LeadRoutingStrategy::RoundRobin,
            'assigned_user_ids' => [$rep1->id, $rep2->id],
            'last_assigned_index' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $contact1 = Contact::factory()->create(['lead_status' => LeadStatus::New]);
        $contact2 = Contact::factory()->create(['lead_status' => LeadStatus::New]);

        $action = new RouteLeadAction;

        // First lead goes to rep2 (index 1)
        $result1 = $action->execute($contact1);
        $this->assertNotNull($result1);
        $this->assertSame($rep2->id, $result1['assigned_user_id']);
        $this->assertSame($rep2->id, $contact1->fresh()->owner_id);

        // Second lead wraps around to rep1 (index 0)
        $result2 = $action->execute($contact2);
        $this->assertNotNull($result2);
        $this->assertSame($rep1->id, $result2['assigned_user_id']);
        $this->assertSame($rep1->id, $contact2->fresh()->owner_id);

        $this->assertDatabaseHas('focal_activities', [
            'subject_type' => $contact1->getMorphClass(),
            'subject_id' => $contact1->id,
            'type' => ActivityType::Note->value,
            'title' => 'Lead Routed to Bob',
        ]);
    }
}
