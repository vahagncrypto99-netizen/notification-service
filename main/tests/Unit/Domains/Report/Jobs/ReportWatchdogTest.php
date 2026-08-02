<?php

declare(strict_types=1);

use App\Domains\Report\Enum\ReportStatusEnum;
use App\Domains\Report\Jobs\GenerateReportJob;
use App\Domains\Report\Jobs\RedispatchStuckReportsJob;
use App\Models\NotificationReport;
use Illuminate\Support\Facades\Queue;

it('передиспатчивает отчёты, зависшие в pending (потерянный dispatch)', function () : void {
    Queue::fake();

    $report = NotificationReport::factory()->create();

    NotificationReport::query()->whereKey($report->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    app()->call([new RedispatchStuckReportsJob, 'handle']);

    Queue::assertPushed(GenerateReportJob::class, 1);
});

it('возвращает зависший processing в pending и передиспатчивает (убитый воркер)', function () : void {
    Queue::fake();

    $report = NotificationReport::factory()->create(['status' => ReportStatusEnum::Processing]);

    NotificationReport::query()->whereKey($report->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    app()->call([new RedispatchStuckReportsJob, 'handle']);

    expect($report->fresh()->status)->toBe(ReportStatusEnum::Pending);

    Queue::assertPushed(GenerateReportJob::class, 1);
});

it('не трогает свежие и терминальные отчёты', function () : void {
    Queue::fake();

    NotificationReport::factory()->create();
    NotificationReport::factory()->done()->create();

    $old_done = NotificationReport::factory()->done()->create();

    NotificationReport::query()->whereKey($old_done->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    app()->call([new RedispatchStuckReportsJob, 'handle']);

    Queue::assertNothingPushed();
});

it('дренирует бэклог больше одной пачки за один запуск', function () : void {
    Queue::fake();

    config(['notification.watchdog.batch_limit' => 2]);

    NotificationReport::factory()->count(5)->create();

    NotificationReport::query()->update(['updated_at' => now()->subMinutes(30)]);

    app()->call([new RedispatchStuckReportsJob, 'handle']);

    Queue::assertPushed(GenerateReportJob::class, 5);
});

it('повторный запуск сразу после передиспатча ничего не пушит', function () : void {
    Queue::fake();

    $report = NotificationReport::factory()->create();

    NotificationReport::query()->whereKey($report->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    app()->call([new RedispatchStuckReportsJob, 'handle']);
    app()->call([new RedispatchStuckReportsJob, 'handle']);

    Queue::assertPushed(GenerateReportJob::class, 1);
});
