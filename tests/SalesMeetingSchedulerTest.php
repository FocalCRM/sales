<?php

declare(strict_types=1);

namespace Focal\Sales\Tests;

use Focal\Core\Enums\ActivityType;
use Focal\Core\Models\Contact;
use Focal\Sales\Models\SalesMeetingLink;
use Focal\Sales\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SalesMeetingSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_public_scheduler_page(): void
    {
        $user = User::factory()->create(['name' => 'Beth Caldwell']);
        $link = SalesMeetingLink::query()->create([
            'user_id' => $user->id,
            'slug' => 'beth-caldwell-30min',
            'title' => '30 Min Product Strategy',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $response = $this->get("/meet/{$link->slug}");

        $response->assertSuccessful();
        $response->assertSee('Beth Caldwell');
        $response->assertSee('30 Min Product Strategy');
        $response->assertSee('Confirm Meeting');
    }

    public function test_can_book_meeting_and_create_contact_with_activity(): void
    {
        $user = User::factory()->create(['name' => 'Beth Caldwell']);
        $link = SalesMeetingLink::query()->create([
            'user_id' => $user->id,
            'slug' => 'beth-caldwell-demo',
            'title' => 'Product Demo Call',
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $response = $this->post("/meet/{$link->slug}/book", [
            'name' => 'Clark Kent',
            'email' => 'clark@dailyplanet.com',
            'phone' => '+1 (555) 123-4567',
            'date' => now()->addDay()->toDateString(),
            'time' => '14:00',
            'notes' => 'Looking to migrate our entire newsroom workflow.',
        ]);

        $response->assertRedirect("/meet/{$link->slug}");
        $response->assertSessionHas('status');

        $contact = Contact::query()->whereEmail('clark@dailyplanet.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Clark', $contact->first_name);
        $this->assertSame('Kent', $contact->last_name);
        $this->assertSame($user->id, $contact->owner_id);

        $this->assertDatabaseHas('focal_activities', [
            'subject_type' => $contact->getMorphClass(),
            'subject_id' => $contact->id,
            'type' => ActivityType::Meeting->value,
            'title' => 'Product Demo Call with Clark Kent',
        ]);
    }
}
