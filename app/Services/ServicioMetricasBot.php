<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ServicioMetricasBot
{
    protected string $dashboardKey = 'bot:metrics:dashboard';

    public function incrementIntent(string $intent): void
    {
        $this->increment("bot:metrics:intent:{$intent}");
        $this->incrementDashboardBucket('intents', $intent);
    }

    public function incrementRoute(string $route): void
    {
        $this->increment("bot:metrics:route:{$route}");
        $this->incrementDashboardBucket('routes', $route);
    }

    public function incrementError(string $errorType): void
    {
        $this->increment("bot:metrics:error:{$errorType}");
        $this->incrementDashboardBucket('errors', $errorType);
    }

    protected function increment(string $key): void
    {
        $current = (int) Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addDays(7));
    }

    protected function incrementDashboardBucket(string $bucket, string $name): void
    {
        $snapshot = Cache::get($this->dashboardKey, [
            'intents' => [],
            'routes' => [],
            'errors' => [],
            'updated_at' => now()->toDateTimeString(),
        ]);

        if (!isset($snapshot[$bucket][$name])) {
            $snapshot[$bucket][$name] = 0;
        }
        $snapshot[$bucket][$name]++;
        $snapshot['updated_at'] = now()->toDateTimeString();

        Cache::put($this->dashboardKey, $snapshot, now()->addDays(7));
    }

    public function getDashboardSnapshot(): array
    {
        return Cache::get($this->dashboardKey, [
            'intents' => [],
            'routes' => [],
            'errors' => [],
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function registerFailureStreak(string $phone): int
    {
        $key = "bot:metrics:streak:{$phone}";
        $current = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $current, now()->addHours(12));
        return $current;
    }

    public function resetFailureStreak(string $phone): void
    {
        Cache::forget("bot:metrics:streak:{$phone}");
    }
}

