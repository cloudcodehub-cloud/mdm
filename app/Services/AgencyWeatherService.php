<?php

namespace App\Services;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AgencyWeatherService
{
    /**
     * Shared top-bar weather payload. Never throws.
     *
     * @return array{available: bool, temperature: string|null, condition: string|null, location: string, source: string|null}
     */
    public function shared(): array
    {
        try {
            return $this->current();
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * @return array{available: bool, temperature: string|null, condition: string|null, location: string, source: string|null}
     */
    public function current(): array
    {
        $location = app(SettingsService::class)->agencyLocation();
        $fallback = $this->unavailable($location['label']);

        if (! (bool) config('services.weather.enabled', true)) {
            return $fallback;
        }

        $ttl = max(60, (int) config('services.weather.cache_ttl', 900));
        $cacheKey = 'agency-weather:'.md5(implode('|', [
            $location['city'],
            $location['state'],
            $location['postal_code'],
            $location['label'],
        ]));

        /** @var array{available: bool, temperature: string|null, condition: string|null, location: string, source: string|null} $payload */
        $payload = Cache::remember($cacheKey, $ttl, function () use ($location, $fallback): array {
            $coordinates = $this->geocode($location);

            if ($coordinates === null) {
                return $fallback;
            }

            $forecast = $this->forecast($coordinates['latitude'], $coordinates['longitude']);

            if ($forecast === null) {
                return $fallback;
            }

            return [
                'available' => true,
                'temperature' => $forecast['temperature'],
                'condition' => $forecast['condition'],
                'location' => $location['label'] !== '' ? $location['label'] : $coordinates['label'],
                'source' => 'Open-Meteo',
            ];
        });

        return $payload;
    }

    /**
     * @param  array{city: string, state: string, postal_code: string, label: string}  $location
     * @return array{latitude: float, longitude: float, label: string}|null
     */
    public function geocode(array $location): ?array
    {
        $query = trim($location['city'] !== ''
            ? $location['city']
            : $location['label']);

        if ($query === '') {
            return null;
        }

        $url = (string) config('services.weather.geocode_url');

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->get($url, [
                    'name' => $query,
                    'count' => 5,
                    'language' => 'en',
                    'format' => 'json',
                    'countryCode' => 'US',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $results = $response->json('results');

        if (! is_array($results) || $results === []) {
            return null;
        }

        $match = $this->bestGeocodeMatch($results, $location);

        if ($match === null) {
            return null;
        }

        $latitude = $match['latitude'] ?? null;
        $longitude = $match['longitude'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $city = is_string($match['name'] ?? null) ? $match['name'] : $query;
        $admin = is_string($match['admin1'] ?? null) ? $match['admin1'] : $location['state'];

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'label' => $this->formatLabel($city, $admin, $location['state']),
        ];
    }

    /**
     * @return array{temperature: string, condition: string}|null
     */
    public function forecast(float $latitude, float $longitude): ?array
    {
        $url = (string) config('services.weather.forecast_url');
        $timezone = app(SettingsService::class)->timezone();

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->get($url, [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,weather_code',
                    'temperature_unit' => 'fahrenheit',
                    'timezone' => $timezone,
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $temperature = $response->json('current.temperature_2m');
        $code = $response->json('current.weather_code');

        if (! is_numeric($temperature)) {
            return null;
        }

        return [
            'temperature' => sprintf('%d°F', (int) round((float) $temperature)),
            'condition' => $this->conditionLabel(is_numeric($code) ? (int) $code : null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  array{city: string, state: string, postal_code: string, label: string}  $location
     * @return array<string, mixed>|null
     */
    private function bestGeocodeMatch(array $results, array $location): ?array
    {
        $state = strtolower($location['state']);

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $admin = strtolower((string) ($result['admin1'] ?? ''));
            $code = strtolower((string) ($result['admin1_code'] ?? ''));

            if ($state !== '' && ($admin === $state || str_ends_with($code, '-'.$state) || str_contains($admin, $state))) {
                return $result;
            }
        }

        $first = $results[0];

        return is_array($first) ? $first : null;
    }

    private function conditionLabel(?int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear',
            $code === 1 => 'Mostly clear',
            $code === 2 => 'Partly cloudy',
            $code === 3 => 'Overcast',
            $code === 45, $code === 48 => 'Fog',
            $code !== null && $code >= 51 && $code <= 67 => 'Rain',
            $code !== null && $code >= 71 && $code <= 77 => 'Snow',
            $code !== null && $code >= 80 && $code <= 82 => 'Showers',
            $code !== null && $code >= 95 => 'Thunderstorms',
            default => 'Current conditions',
        };
    }

    private function formatLabel(string $city, string $admin, string $state): string
    {
        $region = $state !== '' ? $state : $admin;

        if (strlen($region) > 2 && $state !== '') {
            $region = $state;
        } elseif (strlen($region) > 2) {
            $region = $this->stateAbbreviation($region) ?? $region;
        }

        if ($city === '' || $region === '') {
            return $city !== '' ? $city : $region;
        }

        return $city.', '.$region;
    }

    private function stateAbbreviation(string $name): ?string
    {
        $map = [
            'ohio' => 'OH',
            'new york' => 'NY',
            'pennsylvania' => 'PA',
        ];

        return $map[strtolower($name)] ?? null;
    }

    /**
     * @return array{available: bool, temperature: string|null, condition: string|null, location: string, source: string|null}
     */
    private function unavailable(string $location = ''): array
    {
        if ($location === '') {
            $location = app(SettingsService::class)->agencyLocation()['label'];
        }

        return [
            'available' => false,
            'temperature' => null,
            'condition' => null,
            'location' => $location,
            'source' => null,
        ];
    }
}
