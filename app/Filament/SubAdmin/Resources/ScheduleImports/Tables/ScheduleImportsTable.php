<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Tables;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduleImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label(__('schedules.filename'))
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => __("schedules.status_{$state}"))
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->icon(fn(string $state): Heroicon => match ($state) {
                        'completed' => Heroicon::CheckCircle,
                        'processing' => Heroicon::Clock,
                        'failed' => Heroicon::XCircle,
                        default => Heroicon::Clock,
                    })
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
                TextColumn::make('total_shapes')
                    ->label('Shapes')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('schedules.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('schedules.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('schedules.status'))
                    ->options([
                        'pending' => __('schedules.status_pending'),
                        'processing' => __('schedules.status_processing'),
                        'completed' => __('schedules.status_completed'),
                        'failed' => __('schedules.status_failed'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('update_file')
                    ->label('Update File')
                    ->icon('heroicon-s-arrow-up-tray')
                    ->color('warning')
                    ->url(fn($record) => ScheduleImportResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
