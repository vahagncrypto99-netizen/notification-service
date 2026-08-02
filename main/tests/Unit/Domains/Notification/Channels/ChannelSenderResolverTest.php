<?php

declare(strict_types=1);

use App\Domains\Notification\Channels\ChannelSenderResolver;
use App\Domains\Notification\Channels\EmailChannelSender;
use App\Domains\Notification\Channels\TelegramChannelSender;
use App\Domains\Notification\Enum\ChannelEnum;
use App\Domains\Notification\Exceptions\ChannelSenderNotConfiguredException;

it('резолвит email-отправитель', function () : void {
    expect(
        app(ChannelSenderResolver::class)->resolve(ChannelEnum::Email)
    )->toBeInstanceOf(EmailChannelSender::class);
});

it('резолвит telegram-отправитель', function () : void {
    expect(
        app(ChannelSenderResolver::class)->resolve(ChannelEnum::Telegram)
    )->toBeInstanceOf(TelegramChannelSender::class);
});

it('бросает исключение для канала без отправителя', function () : void {
    config(['notification.channels' => []]);

    app(ChannelSenderResolver::class)->resolve(ChannelEnum::Email);
})->throws(ChannelSenderNotConfiguredException::class);

it('бросает исключение для класса, не реализующего контракт', function () : void {
    config(['notification.channels' => ['email' => stdClass::class]]);

    app(ChannelSenderResolver::class)->resolve(ChannelEnum::Email);
})->throws(ChannelSenderNotConfiguredException::class);
