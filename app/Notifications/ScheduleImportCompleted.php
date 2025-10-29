<?php

namespace App\Notifications;

use App\Models\ScheduleImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ScheduleImportCompleted extends Notification
{
    use Queueable;

    public function __construct(
        public ScheduleImport $scheduleImport,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Schedule Import Completed')
            ->body("Successfully imported {$this->scheduleImport->filename}")
            ->success()
            ->icon('heroicon-o-check-circle')
            ->actions([
                Action::make('view')
                    ->button()
                    ->url(route('filament.panel.resources.schedules.index'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
