<?php

declare(strict_types=1);

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Enum\ChannelEnum;
use App\Domains\Notification\Exceptions\ChannelSenderNotConfiguredException;
use Illuminate\Contracts\Container\Container;

class ChannelSenderResolver
{
    /**
     * ChannelSenderResolver constructor.
     *
     * @param  array<string, class-string>  $channels  карта «канал → класс-отправитель»
     */
    public function __construct(
        protected Container $container,
        protected array $channels,
    ) {
        //
    }

    /**
     * Получение отправителя для канала.
     *
     * @throws ChannelSenderNotConfiguredException
     */
    public function resolve(ChannelEnum $channel) : ChannelSenderInterface
    {
        $sender_class = $this->channels[$channel->value] ?? null;

        if ($sender_class === null || ! is_subclass_of($sender_class, ChannelSenderInterface::class)) {
            throw new ChannelSenderNotConfiguredException($channel);
        }

        return $this->container->make($sender_class);
    }
}
