<?php

namespace App\Notifications;

use App\Models\ShowroomDailyReport;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ShowroomDailyReportWorkflowNotification extends Notification
{
    public function __construct(
        private readonly ShowroomDailyReport $report,
        private readonly string $event,
        private readonly User $actor,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $branchName = $this->report->location?->name ?? 'الفرع';
        $date = $this->report->business_date?->format('Y-m-d') ?? '';

        [$title, $message, $priority] = match ($this->event) {
            'submitted' => [
                'تقرير معرض بانتظار المراجعة',
                "تم إرسال تقرير {$branchName} ليوم {$date} للمحاسب العام.",
                'medium',
            ],
            'returned' => [
                'أُعيد تقرير المعرض للتصحيح',
                "أعاد المحاسب العام تقرير {$branchName} ليوم {$date} للتصحيح: "
                    . ($this->report->review_notes ?: 'يرجى مراجعة التقرير.'),
                'high',
            ],
            'approved' => [
                'تم اعتماد تقرير المعرض',
                "تم اعتماد تقرير {$branchName} ليوم {$date} من المحاسب العام.",
                'medium',
            ],
            default => [
                'تحديث تقرير المعرض',
                "يوجد تحديث على تقرير {$branchName} ليوم {$date}.",
                'medium',
            ],
        };

        return [
            'fingerprint' =>
                "showroom_daily_report_{$this->report->id}_{$this->event}_"
                . optional($this->report->updated_at)->timestamp,
            'type' => 'showroom_daily_report_workflow',
            'title' => $title,
            'message' => $message,
            'showroom_daily_report_id' => $this->report->id,
            'location_id' => $this->report->location_id,
            'business_date' => $date,
            'event' => $this->event,
            'actor_id' => $this->actor->id,
            'url' => '/showroom-daily-reports?location_id='
                . $this->report->location_id
                . '&date='
                . $date,
            'priority' => $priority,
        ];
    }
}
