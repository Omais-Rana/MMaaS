<?php

namespace App\Filament\SubAdmin\Widgets;

use App\Models\Activity;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class SubAdminRecentActivity extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function hasColumnToggle(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user->role === 'subadmin' ? $user->id : $user->tenant_id;

        return $table
            ->query(
                Activity::query()
                    ->where('tenant_id', $tenantId)
                    ->with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
            )
            ->heading('Recent Activity')
            ->columns([
                IconColumn::make('type')
                    ->label('')
                    ->icon(fn(string $state): string => match ($state) {
                        'scheduleimport_created' => 'heroicon-o-calendar-days',
                        'scheduleimport_updated' => 'heroicon-o-arrow-path',
                        'scheduleimport_deleted' => 'heroicon-o-trash',
                        'user_created' => 'heroicon-o-user-plus',
                        'user_updated' => 'heroicon-o-pencil',
                        'user_deleted' => 'heroicon-o-user-minus',
                        'role_created' => 'heroicon-o-shield-check',
                        'role_updated' => 'heroicon-o-pencil-square',
                        'data_edited' => 'heroicon-o-pencil',
                        default => 'heroicon-o-bell',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'scheduleimport_created' => 'success',
                        'scheduleimport_updated' => 'info',
                        'scheduleimport_deleted' => 'danger',
                        'user_created' => 'info',
                        'user_updated' => 'warning',
                        'user_deleted' => 'danger',
                        'role_created' => 'primary',
                        'role_updated' => 'warning',
                        'data_edited' => 'warning',
                        default => 'gray',
                    })
                    ->size('lg'),

                TextColumn::make('description')
                    ->label('Activity')
                    ->weight('medium'),

                TextColumn::make('user.name')
                    ->label('User')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->since(),
            ])
            ->paginated(false)
            ->columnManager(false);
    }
}
