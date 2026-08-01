<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Services\Delivery\Messenger\Repository\MessengerQueueRepository;
use InvalidArgumentException;

class MessengerResolver
{
    /**
     * MessengerResolver constructor.
     *
     * @param  array<string, array<string, class-string>>  $messengers  карта «мессенджер → реализация»
     */
    public function __construct(
        protected array $messengers,
    ) {
        //
    }

    /**
     * Создание Schedule отправки для мессенджера.
     */
    public function makeSchedule(string $messenger) : Schedule
    {
        $config = $this->resolve($messenger);

        return new Schedule(
            app(MessengerQueueRepository::class),
            app($config['sender']),
            app($config['message_formatter'] ?? MessageFormatter::class),
            $messenger,
        );
    }

    /**
     * Список настроенных мессенджеров.
     *
     * @return array<int, string>
     */
    public function available() : array
    {
        return array_keys($this->messengers);
    }

    /**
     * Проверка валидности мессенджера.
     */
    public function isValid(string $messenger) : bool
    {
        return isset($this->messengers[$messenger]);
    }

    // ****************************************************************
    // *************************** Support ****************************
    // ****************************************************************

    /**
     * Получение конфигурации мессенджера.
     *
     * @return array<string, class-string>
     *
     * @throws InvalidArgumentException
     */
    protected function resolve(string $messenger) : array
    {
        if (! $this->isValid($messenger)) {
            throw new InvalidArgumentException("Неизвестный мессенджер: {$messenger}");
        }

        return $this->messengers[$messenger];
    }
}
