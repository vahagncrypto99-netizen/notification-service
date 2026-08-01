<?php

declare(strict_types=1);

use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Channels\TelegramChannelSender;
use App\Base\Notification\Dto\ChannelMessageDto;
use App\Models\NotificationMailQueue;
use App\Models\NotificationMessengerQueue;
use App\Services\Notifications\Channels\Mail\DefaultSender;
use App\Services\Notifications\Channels\Mail\Dto\SenderDto;
use App\Services\Notifications\Channels\Mail\Schedule as MailSchedule;
use App\Services\Notifications\Channels\Messenger\MessageFormatter;
use App\Services\Notifications\Channels\Messenger\MessengerResolver;
use App\Services\Notifications\Notification;

function channelMessage(int $notification_id = 1, int $user_id = 7, string $message = 'Привет') : ChannelMessageDto
{
    return new ChannelMessageDto(
        notification_id: $notification_id,
        user_id: $user_id,
        message: $message,
    );
}

describe('почтовый канал', function () : void {
    it('кладёт письмо в очередь канала с дефолтным отправителем', function () : void {
        app(EmailChannelSender::class)->send(channelMessage());

        $record = NotificationMailQueue::query()->firstOrFail();

        expect($record->to_email)->toBe('user-7@example.stub')->and(
            $record->from_email
        )->toBe(config('app_notifications.mail.from.default.email'))->and(
            $record->sender
        )->toBe('default')->and($record->priority)->toBe(1);
    });

    it('крон отправляет пачку по приоритету и чистит очередь', function () : void {
        app(EmailChannelSender::class)->send(channelMessage(1, 7));
        app(EmailChannelSender::class)->send(channelMessage(2, 8));

        app(MailSchedule::class)->send();

        expect(NotificationMailQueue::query()->count())->toBe(0);
    });

    it('отложенное письмо не отправляется раньше времени', function () : void {
        app(Notification::class)->mail()->send(
            SenderDto::from([
                'from_email' => null,
                'from_name' => null,
                'to_email' => 'user@example.stub',
                'subject' => 'Отложенное',
                'message' => 'позже',
                'queue' => true,
                'send_at' => now()->addHour()->toDateTimeString(),
            ])
        );

        app(MailSchedule::class)->send();

        expect(NotificationMailQueue::query()->count())->toBe(1);
    });

    it('невалидный адрес получателя пропускается без записи в очередь', function () : void {
        app(Notification::class)->mail()->send(
            SenderDto::from([
                'from_email' => null,
                'from_name' => null,
                'to_email' => 'не-адрес',
                'subject' => 'x',
                'message' => 'y',
                'queue' => true,
            ])
        );

        expect(NotificationMailQueue::query()->count())->toBe(0);
    });
});

describe('фабрика сендеров', function () : void {
    it('отдаёт дефолтный сендер по конфигу', function () : void {
        expect(app(Notification::class)->mail())->toBeInstanceOf(DefaultSender::class);
    });

    it('бросает исключение для неизвестного сендера', function () : void {
        app(Notification::class)->mail('unisender');
    })->throws(RuntimeException::class);
});

describe('telegram канал', function () : void {
    it('кладёт сообщение в очередь мессенджера', function () : void {
        app(TelegramChannelSender::class)->send(channelMessage(1, 9, 'В телегу'));

        $record = NotificationMessengerQueue::query()->firstOrFail();

        expect($record->messenger)->toBe('telegram')->and(
            $record->messenger_id
        )->toBe('user-9')->and($record->message)->toBe('В телегу');
    });

    it('крон отправляет пачку и чистит очередь', function () : void {
        app(TelegramChannelSender::class)->send(channelMessage());

        app(MessengerResolver::class)->makeSchedule('telegram')->send();

        expect(NotificationMessengerQueue::query()->count())->toBe(0);
    });

    it('при сбое отправки сообщение возвращается в очередь на повтор', function () : void {
        app(TelegramChannelSender::class)->send(channelMessage());

        config(['notification.simulate_failures' => true]);

        app(MessengerResolver::class)->makeSchedule('telegram')->send();

        expect(NotificationMessengerQueue::query()->count())->toBe(1);
    });

    it('резолвер бросает исключение для неизвестного мессенджера', function () : void {
        app(MessengerResolver::class)->makeSchedule('vk');
    })->throws(InvalidArgumentException::class);
});

describe('форматтер сообщений', function () : void {
    it('включает смайлы из literal-последовательностей', function () : void {
        expect(
            app(MessageFormatter::class)->prepareMessage('Привет \uD83D\uDE0A')
        )->toBe('Привет 😊');
    });

    it('вырезает html-теги', function () : void {
        expect(
            app(MessageFormatter::class)->prepareMessage('<b>жирный</b> текст')
        )->toBe('жирный текст');
    });

    it('удаляет управляющие символы', function () : void {
        expect(
            app(MessageFormatter::class)->prepareMessage("чистый\x00\x1Fтекст")
        )->toBe('чистыйтекст');
    });
});
