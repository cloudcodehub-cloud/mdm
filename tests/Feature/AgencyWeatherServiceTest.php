<?php

namespace Tests\Feature;

use App\Services\AgencyWeatherService;
use App\Services\SettingsService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgencyWeatherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.weather.enabled' => true]);
        Cache::flush();
    }

    public function test_it_maps_open_meteo_payload_without_live_http(): void
    {
        app(SettingsService::class)->updateOrganization([
            'city' => 'Lakeside',
            'state' => 'OH',
            'postal_code' => '44001',
        ]);

        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [[
                    'name' => 'Lakeside',
                    'admin1' => 'Ohio',
                    'admin1_code' => 'US-OH',
                    'latitude' => 41.5431,
                    'longitude' => -82.751,
                ]],
            ], 200),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 68.4,
                    'weather_code' => 2,
                ],
            ], 200),
        ]);

        $payload = app(AgencyWeatherService::class)->current();

        $this->assertTrue($payload['available']);
        $this->assertSame('68°F', $payload['temperature']);
        $this->assertSame('Partly cloudy', $payload['condition']);
        $this->assertSame('Lakeside, OH', $payload['location']);
        Http::assertSentCount(2);
    }

    public function test_provider_failure_returns_neutral_fallback(): void
    {
        app(SettingsService::class)->updateOrganization([
            'city' => 'Lakeside',
            'state' => 'OH',
        ]);

        Http::fake([
            '*' => Http::response('nope', 503),
        ]);

        $payload = app(AgencyWeatherService::class)->shared();

        $this->assertFalse($payload['available']);
        $this->assertNull($payload['temperature']);
        $this->assertSame('Lakeside, OH', $payload['location']);
    }

    public function test_disabled_weather_does_not_call_the_network(): void
    {
        config(['services.weather.enabled' => false]);
        Http::fake();

        $payload = app(AgencyWeatherService::class)->current();

        $this->assertFalse($payload['available']);
        Http::assertNothingSent();
    }

    public function test_demo_seeded_agency_location_is_what_weather_displays(): void
    {
        config(['services.weather.enabled' => false]);
        $this->seed(DemoDataSeeder::class);

        $payload = app(AgencyWeatherService::class)->shared();

        $this->assertSame('Reynoldsburg, OH', app(SettingsService::class)->agencyLocation()['label']);
        $this->assertSame('Reynoldsburg, OH', $payload['location']);
        $this->assertFalse($payload['available']);
    }
}
