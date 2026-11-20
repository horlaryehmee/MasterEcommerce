<?php

namespace Tests;

use App\Models\BusinessSetting;
use App\Models\HomepageSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings are memoised per request; reset between tests.
        BusinessSetting::forgetResolved();
        HomepageSetting::forgetResolved();

        $this->withoutVite();
        Storage::fake('public');
    }

    protected function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }

    protected function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin());
    }
}
