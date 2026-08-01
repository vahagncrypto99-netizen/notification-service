<?php

declare(strict_types=1);

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\ReportStatusEnum;
use App\Base\Notification\Jobs\GenerateReportJob;
use App\Models\Notification;
use App\Models\NotificationReport;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('запрос генерации отчёта', function () : void {
    it('создаёт отчёт в статусе pending и ставит джобу генерации', function () : void {
        Queue::fake();

        $payload = [
            'user_id' => 42,
            'period_from' => now()->subMonth()->toDateString(),
            'period_to' => now()->toDateString(),
        ];

        $response = apiPost('/api/reports', $payload);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateReportJob::class, 1);
    });

    it('отклоняет период, где period_to раньше period_from', function () : void {
        $payload = [
            'user_id' => 42,
            'period_from' => '2026-08-01',
            'period_to' => '2026-07-01',
        ];

        apiPost('/api/reports', $payload)->assertUnprocessable()->assertJsonValidationErrors(['period_to']);
    });

    it('отклоняет некорректные даты', function () : void {
        $payload = [
            'user_id' => 42,
            'period_from' => 'вчера',
            'period_to' => 'сегодня',
        ];

        apiPost('/api/reports', $payload)->assertUnprocessable();
    });
});

describe('генерация отчёта', function () : void {
    it('агрегирует уведомления по каналам с учётом границ периода', function () : void {
        Storage::fake('local');

        Notification::factory()->channel(ChannelEnum::Email)->sent()->count(2)->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Email)->failed()->create(['user_id' => 1]);
        Notification::factory()->channel(ChannelEnum::Telegram)->sent()->create(['user_id' => 1]);

        /**
         * Вне периода и чужой пользователь — не должны попасть в отчёт.
         */
        Notification::factory()->channel(ChannelEnum::Email)->sent()->create([
            'user_id' => 1,
            'created_at' => now()->subMonths(2),
        ]);
        Notification::factory()->channel(ChannelEnum::Email)->sent()->create(['user_id' => 2]);

        $report = NotificationReport::factory()->create([
            'user_id' => 1,
            'period_from' => now()->subWeek()->toDateString(),
            'period_to' => now()->toDateString(),
        ]);

        app()->call([new GenerateReportJob($report->id), 'handle']);

        $fresh = $report->fresh();

        expect($fresh->status)->toBe(ReportStatusEnum::Done)->and($fresh->file_path)->not->toBeNull();

        Storage::disk('local')->assertExists($fresh->file_path);

        $lines = explode("\n", trim(Storage::disk('local')->get($fresh->file_path)));

        expect($lines)->toBe([
            'channel,total,failed',
            'email,3,1',
            'telegram,1,0',
        ]);
    });

    it('сбой генерации переводит отчёт в failed и подчищает временный файл', function () : void {
        Storage::fake('local');

        $report = NotificationReport::factory()->create();

        $job = new GenerateReportJob($report->id);

        /**
         * Имитация падения на середине: отчёт уже в processing,
         * временный файл записан, финального нет.
         */
        $report->status = ReportStatusEnum::Processing;
        $report->save();

        Storage::disk('local')->put(
            "reports/tmp/report_{$report->id}.csv.tmp",
            'partial'
        );

        $job->failed(new RuntimeException('Обрыв соединения с БД'));

        $fresh = $report->fresh();

        expect($fresh->status)->toBe(ReportStatusEnum::Failed)->and(
            $fresh->error
        )->toBe('Обрыв соединения с БД');

        Storage::disk('local')->assertMissing("reports/tmp/report_{$report->id}.csv.tmp");
    });

    it('повторная джоба по готовому отчёту ничего не делает', function () : void {
        Storage::fake('local');

        $report = NotificationReport::factory()->done()->create();

        app()->call([new GenerateReportJob($report->id), 'handle']);

        expect($report->fresh()->status)->toBe(ReportStatusEnum::Done);
    });
});

describe('статус и скачивание', function () : void {
    it('возвращает статус отчёта', function () : void {
        $report = NotificationReport::factory()->failed()->create();

        apiGet("/api/reports/{$report->id}")->assertOk()->assertJsonPath(
            'data.status',
            'failed'
        )->assertJsonPath('data.error', 'Simulated report generation failure.');
    });

    it('возвращает 404 для несуществующего отчёта', function () : void {
        apiGet('/api/reports/999999')->assertNotFound();
    });

    it('отдаёт файл готового отчёта', function () : void {
        Storage::fake('local');

        Storage::disk('local')->put('reports/report_7.csv', "channel,total,failed\nemail,1,0\n");

        $report = NotificationReport::factory()->done('reports/report_7.csv')->create();

        apiGet("/api/reports/{$report->id}/download")->assertOk()->assertDownload(
            "notification_report_{$report->id}.csv"
        );
    });

    it('не отдаёт файл, пока отчёт не готов', function () : void {
        $report = NotificationReport::factory()->create();

        apiGet("/api/reports/{$report->id}/download")->assertStatus(409);
    });

    it('не отдаёт частичный файл упавшей генерации', function () : void {
        Storage::fake('local');

        Storage::disk('local')->put('reports/tmp/report_9.csv.tmp', 'partial');

        $report = NotificationReport::factory()->failed()->create();

        apiGet("/api/reports/{$report->id}/download")->assertStatus(409);
    });
});
