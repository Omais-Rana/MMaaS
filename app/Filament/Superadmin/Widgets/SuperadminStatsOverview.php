<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class SuperadminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        $totalOperatorsQuery = User::where('role', 'subadmin');
        if ($user?->role === 'superadmin') {
            $totalOperatorsQuery->where('tenant_id', $user->id);
        }

        $totalOperators = $totalOperatorsQuery->count();
        $totalCustomers = User::where('role', 'customer')->count();
        $subscribedCustomers = 0; // Will be updated when subscription system is implemented
        $customersThisMonth = User::where('role', 'customer')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return [
            Stat::make('Total Operators', $totalOperators)
                ->description('Operator accounts')
                ->descriptionIcon('heroicon-o-user-group', IconPosition::Before)
                ->color('primary')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Total Customers', $totalCustomers)
                ->description('Active customer accounts')
                ->descriptionIcon('heroicon-o-users', IconPosition::Before)
                ->color('success')
                ->chart([3, 5, 6, 7, 5, 8, 9, 10]),

            Stat::make('Subscribed Customers', $subscribedCustomers)
                ->description('Premium subscribers')
                ->descriptionIcon('heroicon-o-star', IconPosition::Before)
                ->color('warning')
                ->chart([2, 3, 4, 5, 6, 7, 6, 5]),

            Stat::make('New This Month', $customersThisMonth)
                ->description('Customers joined in '.now()->format('F'))
                ->descriptionIcon('heroicon-o-arrow-trending-up', IconPosition::Before)
                ->color('info')
                ->chart([1, 2, 3, 2, 4, 5, 6, 7]),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
