<?php

declare(strict_types=1);

use App\Domains\Notification\Channels\EmailChannelSender;
use App\Domains\Notification\Channels\TelegramChannelSender;
use App\Domains\Notification\Dto\ChannelMessageDto;
use App\Domains\Notification\Enum\ChannelEnum;
use App\Domains\Notification\Enum\NotificationStatusEnum;
use App\Domains\Notification\Events\NotificationFailed;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\Delivery\Messenger\Dto\ResponseDto;
use App\Services\Delivery\Messenger\Dto\SenderDto as MessengerSenderDto;
use App\Services\Delivery\Messenger\Telegram\TelegramClient;
use Illuminate\Support\Facades\Event;
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
            fn (string $message, array $context = []) => ($context['to_email'] ?? null) === 'user-7@example.stub'
        )->once();
    });
});

describe('telegram канал', function () : void {
    it('отправляет сообщение через клиент с троттлингом', function () : void {
        Log::spy();

        app(TelegramChannelSender::class)->send(channelMessage(1, 9, 'В телегу'));

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context = []) => ($context['chat_id'] ?? null) === 'user-9'
        )->once();
    });

    it('транзиентный сбой клиента пробрасывается для ретрая джобы', function () : void {
        config(['delivery.simulate_failures' => true]);

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

        Event::fake([NotificationFailed::class]);

        app()->call([new SendNotificationJob($notification->id), 'handle']);

        $fresh = $notification->fresh();

        expect($fresh->status)->toBe(NotificationStatusEnum::Failed)->and(
            $fresh->last_error
        )->toContain('недоступен');

        Event::assertDispatched(NotificationFailed::class);
    });
});
