<?php

declare(strict_types=1);

use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Repository\NotificationRepository;
use App\Models\Notification;

it('переводит processing в sent', function () : void {
    $notification = Notification::factory()->create();

    $transitioned = app(NotificationRepository::class)->markAsSent($notification->id);

    expect($transitioned)->toBeTrue()->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Sent);
});

it('переводит processing в failed с фиксацией ошибки', function () : void {
    $notification = Notification::factory()->create();

    $transitioned = app(NotificationRepository::class)->markAsFailed(
        $notification->id,
        'SMTP недоступен'
    );

    $fresh = $notification->fresh();

    expect($transitioned)->toBeTrue()->and($fresh->status)->toBe(
        NotificationStatusEnum::Failed
    )->and($fresh->last_error)->toBe('SMTP недоступен');
});

it('не переводит sent в failed', function () : void {
    $notification = Notification::factory()->sent()->create();

    $transitioned = app(NotificationRepository::class)->markAsFailed($notification->id, 'ошибка');

    expect($transitioned)->toBeFalse()->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Sent);
});

it('не переводит failed в sent', function () : void {
    $notification = Notification::factory()->failed()->create();

    $transitioned = app(NotificationRepository::class)->markAsSent($notification->id);

    expect($transitioned)->toBeFalse()->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Failed);
});
