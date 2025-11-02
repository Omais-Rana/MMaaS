<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use App\Jobs\ProcessScheduleImport;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditScheduleImport extends EditRecord
{
    protected static string $resource = ScheduleImportResource::class;

    protected ?string $filePath = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Remove the file field from being pre-filled since it's not stored in DB
        unset($data['file']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If a new file was uploaded, store its path and remove from data
        if (isset($data['file'])) {
            $this->filePath = $data['file'];
            unset($data['file']);

            // Update status to pending since we'll reprocess
            $data['status'] = 'pending';
            $data['error_message'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // If a new file was uploaded, reprocess the import
        if (isset($this->filePath)) {
            ProcessScheduleImport::dispatch($this->record, $this->filePath);

            Notification::make()
                ->title('Schedule Update Processing')
                ->body('Your updated GTFS file is being processed. New records will be added and existing records will be updated with any changes. Records not in the new file will be preserved.')
                ->info()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
