<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use App\Jobs\ProcessScheduleImport;
use App\Models\ScheduleImport;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateScheduleImport extends CreateRecord
{
    protected static string $resource = ScheduleImportResource::class;

    protected ?string $filePath = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // File is already stored in schedule-imports/ by FileUpload component
        $this->filePath = $data['file'];

        $tenantId = Auth::user()->isSubAdmin() ? Auth::id() : Auth::user()->tenant_id;

        // Check if filename already exists for this tenant
        if (isset($data['filename'])) {
            $existingSchedule = ScheduleImport::where('tenant_id', $tenantId)
                ->where('filename', $data['filename'])
                ->first();

            if ($existingSchedule) {
                Notification::make()
                    ->warning()
                    ->title('Duplicate File Detected')
                    ->body("A schedule with the filename '{$data['filename']}' already exists (Schedule ID: {$existingSchedule->id}). Proceeding will create duplicate data. Consider updating the existing schedule instead.")
                    ->persistent()
                    ->send();
            }
        }

        // The filename is already set by storeFileNamesIn()
        $data['tenant_id'] = $tenantId;
        $data['status'] = 'pending';

        // Remove 'file' from data as it's not a column in the table
        unset($data['file']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (isset($this->filePath)) {
            ProcessScheduleImport::dispatch($this->record, $this->filePath);

            Notification::make()
                ->title('Import Started')
                ->body('Your schedule import has been queued for processing. You will be notified when it completes.')
                ->info()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
