<?php

declare(strict_types=1);

use App\Base\Notification\Channels\EmailChannelSender;
use App\Base\Notification\Dto\ChannelMessageDto;
use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Base\Notification\Events\NotificationFailed;
use App\Models\Notification;
use App\Models\NotificationMailQueue;
use App\Models\NotificationMessengerQueue;
use App\Services\Delivery\Messenger\Dto\ResponseDto;
use App\Services\Delivery\Messenger\Dto\SenderDto as MessengerSenderDto;
use App\Services\Delivery\Messenger\MessengerResolver;
use App\Services\Delivery\Messenger\MessengerSenderInterface;
use App\Services\Delivery\Messenger\MessengerService;
use Illuminate\Support\Facades\Event;

/**
 * Клиент-заглушка «получатель заблокировал бота».
 */
class BlockedRecipientClient implements MessengerSenderInterface
{
    public function send(MessengerSenderDto $data) : ResponseDto
    {
        return ResponseDto::error('Получатель заблокировал бота', true, false);
    }
}

function enqueueMailFor(Notification $notification) : void
{
    app(EmailChannelSender::class)->send(new ChannelMessageDto(
        notification_id: $notification->id,
        user_id: $notification->user_id,
        message: $notification->message,
    ));
}

it('сбой крон-отправки оставляет письмо в очереди с инкрементом попыток', function () : void {
    $notification = Notification::factory()->channel(ChannelEnum::Email)->create();

    enqueueMailFor($notification);

    config(['notification.simulate_failures' => true]);

    deliverChannels();

    $record = NotificationMailQueue::query()->firstOrFail();

    expect($record->attempts)->toBe(1)->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Processing);
});

it('исчерпание попыток канала переводит уведомление в failed', function () : void {
    Event::fake([NotificationFailed::class]);

    $notification = Notification::factory()->channel(ChannelEnum::Email)->create();

    enqueueMailFor($notification);

    NotificationMailQueue::query()->update(['attempts' => 4]);

    config(['notification.simulate_failures' => true]);

    deliverChannels();

    expect(NotificationMailQueue::query()->count())->toBe(0)->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Failed);

    Event::assertDispatched(NotificationFailed::class);
});

it('отложенное сообщение мессенджера не отправляется раньше времени', function () : void {
    app(MessengerService::class)->enqueue(
        'telegram',
        'user-1',
        'позже',
        null,
        now()->addHour()->toDateTimeString(),
    );

    deliverChannels();

    expect(NotificationMessengerQueue::query()->count())->toBe(1);
});

it('блокировка бота получателем — терминальный отказ доставки', function () : void {
    Event::fake([NotificationFailed::class]);

    $notification = Notification::factory()->channel(ChannelEnum::Telegram)->create();

    app(MessengerService::class)->enqueue('telegram', 'user-1', 'текст', $notification->id);

    config(['delivery.messengers.telegram.sender' => BlockedRecipientClient::class]);

    /**
     * Резолвер — singleton, собранный из конфига: смена конфига
     * в тесте требует пересборки.
     */
    app()->forgetInstance(MessengerResolver::class);

    deliverChannels();

    expect(NotificationMessengerQueue::query()->count())->toBe(0)->and(
        $notification->fresh()->status
    )->toBe(NotificationStatusEnum::Failed);

    Event::assertDispatched(NotificationFailed::class);
});

it('повторная постановка в мессенджер по тому же уведомлению не дублируется', function () : void {
    $notification = Notification::factory()->channel(ChannelEnum::Telegram)->create();

    app(MessengerService::class)->enqueue('telegram', 'user-1', 'текст', $notification->id);
    app(MessengerService::class)->enqueue('telegram', 'user-1', 'текст', $notification->id);

    expect(NotificationMessengerQueue::query()->count())->toBe(1);
});
