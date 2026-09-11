<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PlansBillingDisabledTest extends TestCase
{
    use RefreshDatabase;
    public function test_plans_and_billing_features_are_disabled_by_default()
    {
        $this->assertFalse(Feature::active('plans'));
        $this->assertFalse(Feature::active('billing'));
    }

    public function test_plans_and_billing_routes_are_inaccessible_when_disabled()
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $this->actingAs($user);

        $this->get(route('plans.index'))->assertNotFound();
        $this->get(route('billing.index'))->assertNotFound();
        $this->get(route('admin.billing.index'))->assertNotFound();
    }
}
