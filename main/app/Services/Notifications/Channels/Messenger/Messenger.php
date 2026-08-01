<?php

declare(strict_types=1);

namespace App\Services\Notifications\Channels\Messenger;

class Messenger
{
    /**
     * Телеграм.
     */
    public const TELEGRAM = 'telegram';

    /**
     * Проверка валидности имени мессенджера.
     */
    public static function isValid(string $messenger) : bool
    {
        return in_array($messenger, [self::TELEGRAM], true);
    }
}
