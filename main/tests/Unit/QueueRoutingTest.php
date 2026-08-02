<?php

declare(strict_types=1);

use App\Domains\Notification\Dto\CreateNotificationDto;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Domains\Notification\NotificationManager;
use App\Domains\Report\Dto\RequestReportDto;
use App\Domains\Report\Jobs\GenerateReportJob;
use App\Domains\Report\ReportManager;
use App\Queue\QueueSettings;
use Illuminate\Support\Facades\Queue;

it('отправка уведомления уходит в высокоприоритетную очередь', function () : void {
    Queue::fake();

    app(NotificationManager::class)->create(CreateNotificationDto::validateAndCreate([
        'message' => 'Проверка очереди',
        'user_id' => 1,
        'channel' => 'email',
    ]));

    Queue::assertPushedOn(QueueSettings::HIGH_PRIORITY_QUEUE, SendNotificationJob::class);
});

it('генерация отчёта уходит в низкоприоритетную очередь', function () : void {
    Queue::fake();

    app(ReportManager::class)->requestReport(RequestReportDto::validateAndCreate([
        'user_id' => 1,
        'period_from' => '2026-07-01',
        'period_to' => '2026-07-31',
    ]));

    Queue::assertPushedOn(QueueSettings::LOW_PRIORITY_QUEUE, GenerateReportJob::class);
});

it('неизвестное имя очереди откатывается на низкоприоритетную', function () : void {
    $job = new SendNotificationJob(1);

    $job->onQueue('nonexistent_queue');

    expect($job->queue)->toBe(QueueSettings::LOW_PRIORITY_QUEUE);
});
