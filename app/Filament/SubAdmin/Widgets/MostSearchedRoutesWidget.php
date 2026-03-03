<?php

namespace App\Filament\SubAdmin\Widgets;

use App\Models\RouteSearch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MostSearchedRoutesWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected function hasColumnToggle(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;

        $tenantScope = function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
                ->orWhereHas('user', fn($u) => $u->where('tenant_id', $tenantId));
        };

        // Group by all three dimensions so every row shows full origin → destination @ hour detail.
        // LPAD(MIN(HOUR(...))) satisfies only_full_group_by: all rows in the group share the same
        // hour value, so MIN() is identical to the raw value but is a valid aggregate expression.
        $innerQuery = RouteSearch::selectRaw(
            'origin_name,
             destination_name,
             LPAD(MIN(HOUR(search_time)), 2, "0") AS departure_hour,
             count(*) AS total,
             MIN(id) AS id'
        )
            ->where($tenantScope)
            ->whereNotNull('search_time')
            ->groupBy('origin_name', 'destination_name', DB::raw('HOUR(search_time)'))
            ->orderByDesc('total');

        $totalSearches = RouteSearch::where($tenantScope)->count();

        $originOptions = RouteSearch::where($tenantScope)
            ->distinct()->orderBy('origin_name')
            ->pluck('origin_name', 'origin_name')->toArray();

        $destinationOptions = RouteSearch::where($tenantScope)
            ->distinct()->orderBy('destination_name')
            ->pluck('destination_name', 'destination_name')->toArray();

        $hourOptions = RouteSearch::where($tenantScope)
            ->whereNotNull('search_time')
            ->selectRaw('LPAD(MIN(HOUR(search_time)), 2, "0") AS h')
            ->groupBy(DB::raw('HOUR(search_time)'))
            ->orderBy('h')
            ->pluck('h', 'h')
            ->mapWithKeys(fn($h) => [$h => "{$h}:00 – " . str_pad((int) $h + 1, 2, '0', STR_PAD_LEFT) . ':00'])
            ->toArray();

        return $table
            ->query(RouteSearch::fromSub($innerQuery, 'route_searches'))
            ->heading('Most Searched Routes')
            ->description("{$totalSearches} total " . str('search')->plural($totalSearches) . ' by registered users')
            ->filters([
                SelectFilter::make('origin_name')
                    ->label('Start')
                    ->options($originOptions)
                    ->attribute('origin_name')
                    ->placeholder('All origins'),
                SelectFilter::make('destination_name')
                    ->label('Destination')
                    ->options($destinationOptions)
                    ->attribute('destination_name')
                    ->placeholder('All destinations'),
                SelectFilter::make('departure_hour')
                    ->label('Departure Hour')
                    ->options($hourOptions)
                    ->attribute('departure_hour')
                    ->placeholder('All hours'),
            ])
            ->deferFilters(false)
            ->columns([
                TextColumn::make('origin_name')
                    ->label('Start')
                    ->sortable(),
                TextColumn::make('destination_name')
                    ->label('Destination')
                    ->sortable(),
                TextColumn::make('departure_hour')
                    ->label('Departs')
                    ->sortable()
                    ->formatStateUsing(fn(string $state): string => "{$state}:00"),
                TextColumn::make('total')
                    ->label('Searches')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
            ])
            ->defaultSort('total', 'desc')
            ->paginated([10, 25, 50])
            ->columnManager(false);
    }
}
