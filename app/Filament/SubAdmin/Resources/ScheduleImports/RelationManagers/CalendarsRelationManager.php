<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use App\Models\CalendarDate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarsRelationManager extends RelationManager
{
    protected static string $relationship = 'calendars';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_id')
                    ->label(__('schedules.calendar_service'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),
                TextColumn::make('start_date')
                    ->label(__('schedules.calendar_start'))
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('schedules.calendar_end'))
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('monday')
                    ->label(__('schedules.calendar_monday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('tuesday')
                    ->label(__('schedules.calendar_tuesday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('wednesday')
                    ->label(__('schedules.calendar_wednesday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('thursday')
                    ->label(__('schedules.calendar_thursday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('friday')
                    ->label(__('schedules.calendar_friday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('saturday')
                    ->label(__('schedules.calendar_saturday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('sunday')
                    ->label(__('schedules.calendar_sunday'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? '✓' : '—')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('exceptions')
                    ->label(__('schedules.calendar_exceptions'))
                    ->getStateUsing(function ($record) {
                        $scheduleImportId = $this->getOwnerRecord()->id;
                        $exceptionCount = CalendarDate::where('service_id', $record->service_id)
                            ->where('schedule_import_id', $scheduleImportId)
                            ->count();

                        return $exceptionCount > 0 ? $exceptionCount : null;
                    })
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $this->getExceptionTooltip($record))
                    ->alignCenter(),
            ])
            ->defaultSort('service_id')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateDescription(__('schedules.calendar_empty_state'));
    }

    protected function getExceptionTooltip($record): ?string
    {
        $scheduleImportId = $this->getOwnerRecord()->id;
        $exceptions = CalendarDate::where('service_id', $record->service_id)
            ->where('schedule_import_id', $scheduleImportId)
            ->orderBy('date')
            ->limit(10)
            ->get();

        if ($exceptions->isEmpty()) {
            return null;
        }

        $tooltip = "Exception dates:\n";
        foreach ($exceptions as $exception) {
            $type = $exception->exception_type === 1 ? 'Added' : 'Removed';
            $tooltip .= "{$exception->date->format('M d, Y')} ({$type})\n";
        }

        if ($exceptions->count() === 10) {
            $tooltip .= '... and more';
        }

        return $tooltip;
    }
}
