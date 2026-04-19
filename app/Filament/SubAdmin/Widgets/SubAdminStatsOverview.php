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

        // Trips scheduled for today across completed schedules
        $tripsToday = 0;
        $currentDate = now()->toDateString();
        $currentWeekday = strtolower(now()->englishDayOfWeek);

        $completedImportIds = ScheduleImport::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->pluck('id')
            ->toArray();

        if (! empty($completedImportIds)) {
            $activeServiceIds = DB::table('calendars')
                ->where('tenant_id', $tenantId)
                ->whereIn('schedule_import_id', $completedImportIds)
                ->whereDate('start_date', '<=', $currentDate)
                ->whereDate('end_date', '>=', $currentDate)
                ->where($currentWeekday, true)
                ->pluck('service_id')
                ->toArray();

            $addedServiceIds = DB::table('calendar_dates')
                ->where('tenant_id', $tenantId)
                ->whereIn('schedule_import_id', $completedImportIds)
                ->whereDate('date', $currentDate)
                ->where('exception_type', 1)
                ->pluck('service_id')
                ->toArray();

            $removedServiceIds = DB::table('calendar_dates')
                ->where('tenant_id', $tenantId)
                ->whereIn('schedule_import_id', $completedImportIds)
                ->whereDate('date', $currentDate)
                ->where('exception_type', 2)
                ->pluck('service_id')
                ->toArray();

            $activeServiceIds = array_values(array_unique(array_merge($activeServiceIds, $addedServiceIds)));
            if (! empty($removedServiceIds)) {
                $activeServiceIds = array_values(array_filter($activeServiceIds, fn($serviceId) => ! in_array($serviceId, $removedServiceIds)));
            }

            $tripsToday = empty($activeServiceIds)
                ? 0
                : DB::table('trips')
                ->join('schedule_imports', 'trips.schedule_import_id', '=', 'schedule_imports.id')
                ->where('schedule_imports.tenant_id', $tenantId)
                ->where('schedule_imports.status', 'completed')
                ->whereIn('schedule_imports.id', $completedImportIds)
                ->whereIn('trips.service_id', $activeServiceIds)
                ->distinct('trips.id')
                ->count('trips.id');
        }

        return [
            Stat::make('Total Employees', $totalEmployees)
                ->description('Team members')
                ->descriptionIcon('heroicon-o-user-group', IconPosition::Before)
                ->color('primary')
                ->chart([3, 5, 4, 6, 7, 5, 6, 8]),

            Stat::make('Trips Today', $tripsToday)
                ->description('Scheduled today')
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
