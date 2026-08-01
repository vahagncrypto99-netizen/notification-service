<?php

declare(strict_types=1);

use App\Base\Notification\Enum\ReportStatusEnum;
use App\Base\Notification\Jobs\GenerateReportJob;
use App\Models\NotificationReport;
use Illuminate\Support\Facades\Queue;

it('передиспатчивает отчёты, зависшие в pending (потерянный dispatch)', function () : void {
    Queue::fake();

    $report = NotificationReport::factory()->create();

    NotificationReport::query()->whereKey($report->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    $this->artisan('notification:redispatch-stuck-reports')->assertSuccessful();

    Queue::assertPushed(GenerateReportJob::class, 1);
});

it('возвращает зависший processing в pending и передиспатчивает (убитый воркер)', function () : void {
    Queue::fake();

    $report = NotificationReport::factory()->create(['status' => ReportStatusEnum::Processing]);

    NotificationReport::query()->whereKey($report->id)->update([
        'updated_at' => now()->subMinutes(30),
    ]);

    $this->artisan('notification:redispatch-stuck-reports')->assertSuccessful();

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

    $this->artisan('notification:redispatch-stuck-reports')->assertSuccessful();

    Queue::assertNothingPushed();
});
