<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewScheduleImport extends ViewRecord
{
    protected static string $resource = ScheduleImportResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('schedules.section_import_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('filename')
                                    ->label(__('schedules.import_filename')),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => __("schedules.status_{$state}"))
                                    ->color(fn(string $state): string => match ($state) {
                                        'completed' => 'success',
                                        'processing' => 'warning',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    })
                                    ->icon(fn(string $state): Heroicon => match ($state) {
                                        'completed' => Heroicon::CheckCircle,
                                        'processing' => Heroicon::Clock,
                                        'failed' => Heroicon::XCircle,
                                        default => Heroicon::QuestionMarkCircle,
                                    }),
                                TextEntry::make('created_at')
                                    ->label(__('schedules.import_uploaded_at'))
                                    ->dateTime(),
                            ]),
                    ]),
                Section::make(__('schedules.section_statistics'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_routes')
                                    ->label(__('schedules.stats_routes'))
                                    ->numeric()
                                    ->suffix(' routes'),
                                TextEntry::make('total_stops')
                                    ->label(__('schedules.stats_stops'))
                                    ->numeric()
                                    ->suffix(' stops'),
                                TextEntry::make('total_trips')
                                    ->label(__('schedules.stats_trips'))
                                    ->numeric()
                                    ->suffix(' trips'),
                            ]),
                    ])
                    ->visible(fn($record) => $record->status === 'completed'),
                Section::make(__('schedules.section_error_details'))
                    ->schema([
                        TextEntry::make('error_message')
                            ->label(__('schedules.error_message'))
                            ->columnSpanFull()
                            ->markdown(),
                    ])
                    ->visible(fn($record) => $record->status === 'failed' && $record->error_message),
            ]);
    }
}
