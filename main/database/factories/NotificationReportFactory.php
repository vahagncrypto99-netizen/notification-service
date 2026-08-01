<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Base\Notification\Enum\ReportStatusEnum;
use App\Models\NotificationReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationReport>
 */
class NotificationReportFactory extends Factory
{
    /**
     * Состояние модели по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        return [
            'user_id' => fake()->numberBetween(1, 100000),
            'period_from' => now()->subMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'status' => ReportStatusEnum::Pending,
            'file_path' => null,
            'error' => null,
        ];
    }

    /**
     * Отчёт готов.
     */
    public function done(string $file_path = 'reports/report_1.csv') : static
    {
        return $this->state(fn () => [
            'status' => ReportStatusEnum::Done,
            'file_path' => $file_path,
        ]);
    }

    /**
     * Генерация отчёта упала.
     */
    public function failed() : static
    {
        return $this->state(fn () => [
            'status' => ReportStatusEnum::Failed,
            'error' => 'Simulated report generation failure.',
        ]);
    }
}
