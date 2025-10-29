<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\RelationManagers;

use App\Models\CalendarDate;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
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
                    ->wrap()
                    ->width('300px'),
                TextColumn::make('active_days')
                    ->label(__('schedules.calendar_active_days'))
                    ->getStateUsing(function ($record) {
                        // Check if this is a regular calendar (has any day set to true)
                        $hasRegularDays = $record->monday || $record->tuesday || $record->wednesday ||
                            $record->thursday || $record->friday || $record->saturday || $record->sunday;

                        if ($hasRegularDays) {
                            $days = [];
                            if ($record->monday) {
                                $days[] = __('schedules.day_mon');
                            }
                            if ($record->tuesday) {
                                $days[] = __('schedules.day_tue');
                            }
                            if ($record->wednesday) {
                                $days[] = __('schedules.day_wed');
                            }
                            if ($record->thursday) {
                                $days[] = __('schedules.day_thu');
                            }
                            if ($record->friday) {
                                $days[] = __('schedules.day_fri');
                            }
                            if ($record->saturday) {
                                $days[] = __('schedules.day_sat');
                            }
                            if ($record->sunday) {
                                $days[] = __('schedules.day_sun');
                            }

                            return implode(', ', $days);
                        }

                        // This is an exception-based schedule - analyze the actual dates
                        return $this->getExceptionBasedDays($record);
                    })
                    ->wrap()
                    ->badge()
                    ->color('success')
                    ->tooltip(fn ($record) => $this->getActiveDaysTooltip($record)),
                TextColumn::make('exceptions')
                    ->label(__('schedules.calendar_exceptions'))
                    ->getStateUsing(function ($record) {
                        $scheduleImportId = $this->getOwnerRecord()->id;
                        $exceptionCount = CalendarDate::where('service_id', $record->service_id)
                            ->where('schedule_import_id', $scheduleImportId)
                            ->where('exception_type', 2) // Only count removed dates (actual exceptions)
                            ->count();

                        return $exceptionCount > 0 ? $exceptionCount : null;
                    })
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->width('120px')
                    ->alignCenter(),
            ])
            ->actions([
                Action::make('view_exceptions')
                    ->label(__('schedules.view_schedule_details'))
                    ->icon('heroicon-o-calendar-days')
                    ->modalHeading(fn ($record) => __('schedules.schedule_details_for_service', ['service' => $record->service_id]))
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('schedules.close'))
                    ->infolist(fn ($record) => $this->getExceptionsInfolist($record))
                    ->visible(fn ($record) => $this->hasExceptions($record)),
            ])
            ->description(fn () => $this->getSchedulePeriodDescription())
            ->defaultSort('service_id')
            ->striped()
            ->paginated([10, 25, 50])
            ->emptyStateDescription(__('schedules.calendar_empty_state'));
    }

    protected function hasExceptions($record): bool
    {
        $scheduleImportId = $this->getOwnerRecord()->id;

        return CalendarDate::where('service_id', $record->service_id)
            ->where('schedule_import_id', $scheduleImportId)
            ->exists();
    }

    protected function getExceptionsInfolist($record): array
    {
        $scheduleImportId = $this->getOwnerRecord()->id;
        $exceptions = CalendarDate::where('service_id', $record->service_id)
            ->where('schedule_import_id', $scheduleImportId)
            ->orderBy('date')
            ->get();

        $added = $exceptions->where('exception_type', 1);
        $removed = $exceptions->where('exception_type', 2);

        $components = [];

        // Show removed dates (exceptions) first if they exist
        if ($removed->isNotEmpty()) {
            // Group dates by month for better readability
            $groupedByMonth = $removed->groupBy(fn ($e) => $e->date->format('F Y'));

            $dateEntries = [];
            foreach ($groupedByMonth as $month => $dates) {
                $dateEntries[] = TextEntry::make('removed_'.$month)
                    ->label($month)
                    ->getStateUsing(fn () => $dates->map(fn ($e) => $e->date->format('M d'))->join(' • '))
                    ->badge()
                    ->color('danger')
                    ->columnSpanFull();
            }

            $components[] = Section::make(__('schedules.exceptions_removed'))
                ->description(__('schedules.exceptions_removed_description'))
                ->schema($dateEntries)
                ->collapsible()
                ->collapsed(false);
        }

        // Show added dates (for exception-based schedules) if they exist
        if ($added->isNotEmpty()) {
            // Group dates by month for better readability
            $groupedByMonth = $added->groupBy(fn ($e) => $e->date->format('F Y'));

            $dateEntries = [];
            foreach ($groupedByMonth as $month => $dates) {
                $dateEntries[] = TextEntry::make('added_'.$month)
                    ->label($month)
                    ->getStateUsing(fn () => $dates->map(fn ($e) => $e->date->format('M d'))->join(' • '))
                    ->badge()
                    ->color('success')
                    ->columnSpanFull();
            }

            $components[] = Section::make(__('schedules.exceptions_added'))
                ->description(__('schedules.exceptions_added_description'))
                ->schema($dateEntries)
                ->collapsible()
                ->collapsed(false);
        }

        return $components;
    }

    protected function getSchedulePeriodDescription(): ?string
    {
        $calendars = $this->getOwnerRecord()->calendars;

        if ($calendars->isEmpty()) {
            return null;
        }

        $startDate = $calendars->min('start_date');
        $endDate = $calendars->max('end_date');

        if (! $startDate || ! $endDate) {
            return null;
        }

        return __('schedules.calendar_period_description', [
            'start' => $startDate->format('M d, Y'),
            'end' => $endDate->format('M d, Y'),
        ]);
    }

    protected function getActiveDaysTooltip($record): string
    {
        $pattern = [];

        if ($record->monday && $record->tuesday && $record->wednesday && $record->thursday && $record->friday && ! $record->saturday && ! $record->sunday) {
            return __('schedules.calendar_pattern_weekdays');
        }

        if (! $record->monday && ! $record->tuesday && ! $record->wednesday && ! $record->thursday && ! $record->friday && $record->saturday && ! $record->sunday) {
            return __('schedules.calendar_pattern_saturday');
        }

        if (! $record->monday && ! $record->tuesday && ! $record->wednesday && ! $record->thursday && ! $record->friday && ! $record->saturday && $record->sunday) {
            return __('schedules.calendar_pattern_sunday');
        }

        if ($record->monday && $record->tuesday && $record->wednesday && $record->thursday && $record->friday && $record->saturday && $record->sunday) {
            return __('schedules.calendar_pattern_daily');
        }

        return __('schedules.calendar_pattern_custom');
    }

    protected function getExceptionBasedDays($record): string
    {
        $scheduleImportId = $this->getOwnerRecord()->id;
        $exceptions = CalendarDate::where('service_id', $record->service_id)
            ->where('schedule_import_id', $scheduleImportId)
            ->where('exception_type', 1) // Only "service added" dates
            ->orderBy('date')
            ->get();

        if ($exceptions->isEmpty()) {
            return '—';
        }

        // Analyze which days of the week the service runs on
        $dayOfWeekCounts = [
            0 => 0, // Sunday
            1 => 0, // Monday
            2 => 0, // Tuesday
            3 => 0, // Wednesday
            4 => 0, // Thursday
            5 => 0, // Friday
            6 => 0, // Saturday
        ];

        foreach ($exceptions as $exception) {
            $dayOfWeek = $exception->date->dayOfWeek;
            $dayOfWeekCounts[$dayOfWeek]++;
        }

        // Find which days are consistently used
        $activeDays = [];
        foreach ($dayOfWeekCounts as $dayNum => $count) {
            if ($count > 0) {
                $activeDays[] = match ($dayNum) {
                    0 => __('schedules.day_sun'),
                    1 => __('schedules.day_mon'),
                    2 => __('schedules.day_tue'),
                    3 => __('schedules.day_wed'),
                    4 => __('schedules.day_thu'),
                    5 => __('schedules.day_fri'),
                    6 => __('schedules.day_sat'),
                };
            }
        }

        return ! empty($activeDays) ? implode(', ', $activeDays) : '—';
    }
}
