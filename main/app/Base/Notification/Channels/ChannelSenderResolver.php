<?php

declare(strict_types=1);

namespace App\Base\Notification\Channels;

use App\Base\Notification\Enum\ChannelEnum;
use App\Base\Notification\Exceptions\ChannelSenderNotConfiguredException;

class ChannelSenderResolver
{
    /**
     * Получение отправителя для канала.
     *
     *
     * @throws ChannelSenderNotConfiguredException
     */
    public function resolve(ChannelEnum $channel) : ChannelSenderInterface
    {
        $map = config('notification.channels', []);

        $sender_class = $map[$channel->value] ?? null;

        if ($sender_class === null || ! is_subclass_of($sender_class, ChannelSenderInterface::class)) {
            throw new ChannelSenderNotConfiguredException($channel);
        }

        return app($sender_class);
    }
}
