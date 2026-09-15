<?php

namespace Tests;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /** Indicates whether the default seeder should run before each test. */
    protected bool $seed = true;

    /** The seeder that runs before each test. */
    protected string $seeder = PermissionsSeeder::class;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
