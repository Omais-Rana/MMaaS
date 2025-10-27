<?php

namespace App\Filament\SubAdmin\Resources\ScheduleImports\Pages;

use App\Filament\SubAdmin\Resources\ScheduleImports\ScheduleImportResource;
use App\Jobs\ProcessScheduleImport;
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

        // The filename is already set by storeFileNamesIn()
        $data['tenant_id'] = Auth::user()->isSubAdmin() ? Auth::id() : Auth::user()->tenant_id;
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
