<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Enum\NotificationStatusEnum;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Состояние модели по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        return [
            'message' => fake()->text(200),
            'status' => NotificationStatusEnum::Processing,
            'user_id' => fake()->numberBetween(1, 100000),
            'channel' => fake()->randomElement(ChannelEnum::cases()),
            'attempts_count' => 0,
            'last_attempted_at' => null,
            'last_error' => null,
        ];
    }

    /**
     * Уведомление отправлено.
     */
    public function sent() : static
    {
        return $this->state(fn () => [
            'status' => NotificationStatusEnum::Sent,
            'attempts_count' => 1,
            'last_attempted_at' => now(),
        ]);
    }

    /**
     * Уведомление завершилось ошибкой.
     */
    public function failed() : static
    {
        return $this->state(fn () => [
            'status' => NotificationStatusEnum::Failed,
            'attempts_count' => 5,
            'last_attempted_at' => now(),
            'last_error' => 'Simulated delivery failure.',
        ]);
    }

    /**
     * Уведомление в конкретном канале.
     */
    public function channel(ChannelEnum $channel) : static
    {
        return $this->state(fn () => ['channel' => $channel]);
    }
}
