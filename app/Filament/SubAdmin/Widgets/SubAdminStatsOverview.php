<?php

namespace App\Filament\SubAdmin\Widgets;

use App\Models\ScheduleImport;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubAdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $user = Auth::user();
        $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;

        // Total Employees under this sub-admin
        $totalEmployees = User::where('role', 'employee')
            ->where('tenant_id', $tenantId)
            ->count();

        // Active Schedules (completed imports)
        $activeSchedules = ScheduleImport::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->count();

        // Total Routes across all schedules
        $totalRoutes = ScheduleImport::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('total_routes');

        // Active Services (distinct route types from routes table)
        $activeServices = DB::table('routes')
            ->join('schedule_imports', 'routes.schedule_import_id', '=', 'schedule_imports.id')
            ->where('schedule_imports.tenant_id', $tenantId)
            ->where('schedule_imports.status', 'completed')
            ->distinct('routes.route_type')
            ->count('routes.route_type');

        return [
            Stat::make('Total Employees', $totalEmployees)
                ->description('Team members')
                ->descriptionIcon('heroicon-o-user-group', IconPosition::Before)
                ->color('primary')
                ->chart([3, 5, 4, 6, 7, 5, 6, 8]),

            Stat::make('Active Services', $activeServices)
                ->description('Transport modes')
                ->descriptionIcon('heroicon-o-truck', IconPosition::Before)
                ->color('success')
                ->chart([1, 1, 2, 2, 2, 3, 3, 3]),

            Stat::make('Active Schedules', $activeSchedules)
                ->description('Completed imports')
                ->descriptionIcon('heroicon-o-calendar-days', IconPosition::Before)
                ->color('warning')
                ->chart([2, 4, 5, 6, 5, 7, 8, 9]),

            Stat::make('Total Routes', $totalRoutes)
                ->description('Across all schedules')
                ->descriptionIcon('heroicon-o-map', IconPosition::Before)
                ->color('info')
                ->chart([5, 10, 15, 20, 25, 30, 35, 40]),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
