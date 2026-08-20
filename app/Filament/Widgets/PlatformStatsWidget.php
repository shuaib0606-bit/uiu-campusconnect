<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember('admin:platform-stats', now()->addMinutes(5), fn () => [
            'users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'posts' => Post::where('is_deleted', false)->count(),
            'open_reports' => Report::whereIn('status', ['open', 'pending', 'reviewing'])->count(),
        ]);

        return [
            Stat::make('Total users', $stats['users']),
            Stat::make('Active users', $stats['active_users']),
            Stat::make('Public posts', $stats['posts']),
            Stat::make('Open reports', $stats['open_reports'])
                ->color($stats['open_reports'] > 0 ? 'danger' : 'success'),
        ];
    }
}
