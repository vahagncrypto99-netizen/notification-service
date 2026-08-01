<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use App\Services\Delivery\DeliveryResultHandlerInterface;
use App\Services\Delivery\Messenger\Repository\MessengerQueueRepository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class MessengerResolver
{
    /**
     * MessengerResolver constructor.
     *
     * @param  array<string, array<string, class-string>>  $messengers  карта «мессенджер → реализация»
     */
    public function __construct(
        protected Container $container,
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

        $sender = $config['sender'] ?? null;

        if ($sender === null || ! is_subclass_of($sender, MessengerSenderInterface::class)) {
            throw new InvalidArgumentException(
                "Сендер мессенджера '{$messenger}' не настроен или не реализует ".MessengerSenderInterface::class.'.'
            );
        }

        return new Schedule(
            $this->container->make(MessengerQueueRepository::class),
            $this->container->make($sender),
            $this->container->make($config['message_formatter'] ?? MessageFormatter::class),
            $this->container->make(DeliveryResultHandlerInterface::class),
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
