<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_without_city_uses_default_city(): void
    {
        config(['weather.default_city' => 'Костанай']);
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['results' => []])]);

        $this->get('/')->assertOk()->assertSee('Костанай');
        Http::assertSent(fn ($r) => $r['name'] === 'Костанай');
    }
}
