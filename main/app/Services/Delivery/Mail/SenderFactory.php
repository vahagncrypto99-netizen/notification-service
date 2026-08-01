<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail;

use RuntimeException;

class SenderFactory
{
    /**
     * Конфигурация подсистемы уведомлений.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Кэш созданных mail-сендеров.
     *
     * @var array<string, MailSenderInterface>
     */
    protected array $mail_senders = [];

    /**
     * SenderFactory constructor.
     */
    public function __construct()
    {
        $this->config = (array) config('delivery');
    }

    /**
     * Получение реализации сендера почты по имени
     * (без имени — дефолтный из конфига).
     */
    public function mail(?string $sender = null) : MailSenderInterface
    {
        $sender = $sender ?: (string) $this->config['mail']['default_sender'];

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
        $handler = $this->config['mail']['senders'][$sender_name]['handler'] ?? null;

        if (empty($handler)) {
            throw new RuntimeException("Не найден сендер mail \"{$sender_name}\".");
        }

        $sender = app($handler);

        if (! $sender instanceof MailSenderInterface) {
            throw new RuntimeException(
                "Сендер mail \"{$sender_name}\" не реализует ".MailSenderInterface::class.'.'
            );
        }

        return $sender;
    }
}
