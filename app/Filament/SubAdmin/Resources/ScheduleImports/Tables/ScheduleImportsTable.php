<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label(__('schedules.filename'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("schedules.status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): Heroicon => match ($state) {
                        'completed' => Heroicon::CheckCircle,
                        'processing' => Heroicon::Clock,
                        'failed' => Heroicon::XCircle,
                        default => Heroicon::QuestionMarkCircle,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_routes')
                    ->label(__('schedules.stats_routes'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('total_stops')
                    ->label(__('schedules.stats_stops'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('total_trips')
                    ->label(__('schedules.stats_trips'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label(__('schedules.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
