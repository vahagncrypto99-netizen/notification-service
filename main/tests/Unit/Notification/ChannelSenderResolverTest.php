<?php

declare(strict_types=1);

use App\Base\Notification\Channels\ChannelSenderResolver;
use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Channels\TelegramChannelSender;
use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Exceptions\ChannelSenderNotConfiguredException;

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
