<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\Notifications\Contracts\MailSender;
use RuntimeException;

class Notification
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
     * @var array<string, MailSender>
     */
    protected array $mail_senders = [];

    /**
     * Notification constructor.
     */
    public function __construct()
    {
        $this->config = (array) config('app_notifications');
    }

    /**
     * Получение реализации сендера почты по имени
     * (без имени — дефолтный из конфига).
     */
    public function mail(?string $sender = null) : MailSender
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
    protected function resolveMailSender(string $sender_name) : MailSender
    {
        $handler = $this->config['mail']['senders'][$sender_name]['handler'] ?? null;

        if (empty($handler)) {
            throw new RuntimeException("Не найден сендер mail \"{$sender_name}\".");
        }

        $sender = app($handler);

        if (! $sender instanceof MailSender) {
            throw new RuntimeException(
                "Сендер mail \"{$sender_name}\" не реализует ".MailSender::class.'.'
            );
        }

        return $sender;
    }
}
