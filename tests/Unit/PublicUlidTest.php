<?php

namespace Tests\Unit;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicUlidTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_get_a_ulid_route_key(): void
    {
        $customer = Customer::factory()->create();

        $this->assertTrue(Str::isUlid($customer->ulid));
        $this->assertSame('ulid', $customer->getRouteKeyName());
        $this->assertSame($customer->ulid, $customer->getRouteKey());
    }
}
