<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Reads the shuttle timetable in config/shuttle.php and returns the departures still to come. */
class ShuttleSchedule
{
    public const DEFAULT_ROUTE = 'notun-bazar';

    /** Days ahead to look when today's departures are exhausted. */
    private const LOOKAHEAD_DAYS = 2;

    /**
     * Every configured route, each with its own upcoming departures.
     *
     * @return array<int, array{key:string,name:string,provisional:bool,stops:array,departures:array}>
     */
    public static function allRoutes(int $limitPerRoute = 14): array
    {
        $out = [];

        foreach (array_keys(config('shuttle.routes', [])) as $key) {
            $out[] = [
                'key' => $key,
                'name' => static::routeName($key),
                'provisional' => (bool) config("shuttle.routes.{$key}.provisional", false),
                'stops' => static::stops($key),
                'departures' => static::upcoming($key, $limitPerRoute),
            ];
        }

        return $out;
    }

    /**
     * Upcoming departures for one route across both directions, soonest first.
     *
     * @return array<int, array{direction:string,label:string,from:string,to:string,depart:Carbon,window_until:?string,timestamp:int,is_today:bool}>
     */
    public static function upcoming(string $routeKey = self::DEFAULT_ROUTE, int $limit = 4): array
    {
        $config = config('shuttle');
        $route = $config['routes'][$routeKey] ?? null;

        if (! $route) {
            return [];
        }

        $tz = $config['timezone'] ?? config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $stops = $route['stops'] ?? [];
        $first = $stops[0] ?? 'Start';
        $last = $stops[count($stops) - 1] ?? 'Campus';

        $departures = [];

        for ($dayOffset = 0; $dayOffset <= self::LOOKAHEAD_DAYS; $dayOffset++) {
            $day = $now->copy()->addDays($dayOffset)->startOfDay();

            foreach (['inbound', 'outbound'] as $direction) {
                foreach ($route[$direction] ?? [] as $trip) {
                    $depart = $day->copy()->setTimeFromTimeString($trip['depart']);

                    if ($depart->lessThanOrEqualTo($now)) {
                        continue;
                    }

                    $departures[] = [
                        'direction' => $direction,
                        'label' => $direction === 'inbound' ? 'To campus' : 'From campus',
                        'from' => $direction === 'inbound' ? $first : $last,
                        'to' => $direction === 'inbound' ? $last : $first,
                        'depart' => $depart,
                        'window_until' => $trip['window_until'] ?? null,
                        'timestamp' => $depart->getTimestamp(),
                        'is_today' => $depart->isSameDay($now),
                    ];
                }
            }
        }

        usort($departures, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return array_slice($departures, 0, $limit);
    }

    public static function routeName(string $routeKey = self::DEFAULT_ROUTE): string
    {
        return config("shuttle.routes.{$routeKey}.name", 'Shuttle');
    }

    public static function stops(string $routeKey = self::DEFAULT_ROUTE): array
    {
        return config("shuttle.routes.{$routeKey}.stops", []);
    }
}
