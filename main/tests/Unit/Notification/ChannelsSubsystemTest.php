<?php

declare(strict_types=1);

use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Channels\TelegramChannelSender;
use App\Base\Notification\Dto\ChannelMessageDto;
use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\Delivery\Mail\DefaultSender;
use App\Services\Delivery\Mail\Dto\SenderDto;
use App\Services\Delivery\Mail\SenderFactory;
use App\Services\Delivery\Messenger\Dto\ResponseDto;
use App\Services\Delivery\Messenger\Dto\SenderDto as MessengerSenderDto;
use App\Services\Delivery\Messenger\MessageFormatter;
use App\Services\Delivery\Messenger\Telegram\TelegramClient;
use App\Services\Delivery\PermanentDeliveryException;
use Illuminate\Support\Facades\Log;

function channelMessage(int $notification_id = 1, int $user_id = 7, string $message = 'Привет') : ChannelMessageDto
{
    return new ChannelMessageDto(
        notification_id: $notification_id,
        user_id: $user_id,
        message: $message,
    );
}

describe('почтовый канал', function () : void {
    it('отправляет письмо дефолтным сендером с вычисленным from', function () : void {
        Log::spy();

        app(EmailChannelSender::class)->send(channelMessage());

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message) => str_contains($message, 'user-7@example.stub')
        )->once();
    });

    it('невалидный адрес получателя — неисправимый отказ', function () : void {
        expect(fn () => app(SenderFactory::class)->mail()->send(
            SenderDto::from([
                'from_email' => null,
                'from_name' => null,
                'to_email' => 'не-адрес',
                'subject' => 'x',
                'message' => 'y',
            ])
        ))->toThrow(PermanentDeliveryException::class);
    });
});

describe('фабрика сендеров', function () : void {
    it('отдаёт дефолтный сендер по конфигу', function () : void {
        expect(app(SenderFactory::class)->mail())->toBeInstanceOf(DefaultSender::class);
    });

    it('бросает исключение для неизвестного сендера', function () : void {
        app(SenderFactory::class)->mail('unisender');
    })->throws(RuntimeException::class);
});

describe('telegram канал', function () : void {
    it('отправляет сообщение через клиент с троттлингом', function () : void {
        Log::spy();

        app(TelegramChannelSender::class)->send(channelMessage(1, 9, 'В телегу'));

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message) => str_contains($message, 'user-9')
        )->once();
    });

    it('транзиентный сбой клиента пробрасывается для ретрая джобы', function () : void {
        config(['notification.simulate_failures' => true]);

        app(TelegramChannelSender::class)->send(channelMessage());
    })->throws(RuntimeException::class);

    it('блокировка бота получателем гасит уведомление без ретраев', function () : void {
        $blocked_client = new class extends TelegramClient
        {
            public function send(MessengerSenderDto $data) : ResponseDto
            {
                return ResponseDto::error('Получатель заблокировал бота', true, false);
            }
        };

        app()->instance(TelegramClient::class, $blocked_client);

        $notification = Notification::factory()->channel(
            ChannelEnum::Telegram
        )->create();

        app()->call([new SendNotificationJob($notification->id), 'handle']);

        $fresh = $notification->fresh();

        expect($fresh->status)->toBe(NotificationStatusEnum::Failed)->and(
            $fresh->last_error
        )->toContain('недоступен');
    });
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
