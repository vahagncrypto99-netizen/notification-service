<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

class SenderFactory
{
    /**
     * Кэш созданных mail-сендеров.
     *
     * @var array<string, MailSenderInterface>
     */
    protected array $mail_senders = [];

    /**
     * SenderFactory constructor.
     *
     * @param  array<string, mixed>  $config  mail-конфигурация подсистемы доставки
     */
    public function __construct(
        protected Container $container,
        protected array $config,
    ) {
        //
    }

    /**
     * Получение реализации сендера почты по имени
     * (без имени — дефолтный из конфига).
     *
     * @throws RuntimeException для ненастроенного сендера
     */
    public function mail(?string $sender = null) : MailSenderInterface
    {
        $sender = $sender ?: (string) $this->config['default_sender'];

        if (! empty($this->mail_senders[$sender])) {
            return $this->mail_senders[$sender];
        }

        return $this->mail_senders[$sender] = $this->resolveMailSender($sender);
    }

    /**
     * Создание реализации сендера почты.
     *
     * @throws RuntimeException
     */
    protected function resolveMailSender(string $sender_name) : MailSenderInterface
    {
        $handler = $this->config['senders'][$sender_name]['handler'] ?? null;

        if (empty($handler)) {
            throw new RuntimeException("Не найден сендер mail \"{$sender_name}\".");
        }

        $sender = $this->container->make($handler);

        if (! $sender instanceof MailSenderInterface) {
            throw new RuntimeException(
                "Сендер mail \"{$sender_name}\" не реализует ".MailSenderInterface::class.'.'
            );
        }

        return $sender;
    }
}
