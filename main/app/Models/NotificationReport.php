<?php

declare(strict_types=1);

namespace App\Models;

use App\Base\Report\Enum\ReportStatusEnum;
use Carbon\Carbon;
use Database\Factories\NotificationReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property ReportStatusEnum $status
 * @property string|null $file_path
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class NotificationReport extends Model
{
    /** @use HasFactory<NotificationReportFactory> */
    use HasFactory;

    /**
     * Таблица модели.
     *
     * @var string
     */
    protected $table = 'notification_reports';

    /**
     * Приведение типов атрибутов.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'period_from' => 'date',
        'period_to' => 'date',
        'status' => ReportStatusEnum::class,
    ];

    /**
     * Находится ли отчёт в указанном статусе.
     */
    public function inStatus(ReportStatusEnum $status) : bool
    {
        return $this->status === $status;
    }
}
