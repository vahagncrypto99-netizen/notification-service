<?php

declare(strict_types=1);

namespace App\Services\Delivery\Mail\Dto;

use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;

class SenderDto extends Data
{
    /**
     * SenderDto constructor.
     *
     * @param  array<string, mixed>|null  $additionally
     */
    public function __construct(
        public ?string $from_email,
        public ?string $from_name,
        public string $to_email,
        public string $subject,
        public string $message,
        public ?array $additionally = null,
        public ?bool $queue = null,
        public ?int $priority = null,
        public ?string $send_at = null,
    ) {
        $this->from_email = trim((string) $this->from_email);
        $this->to_email = trim($this->to_email);

        /**
         * Заполнение дефолтными значениями.
         */
        if (empty($this->from_email)) {
            $this->from_email = (string) config('app_notifications.mail.from.default.email');
        }

        if (empty($this->from_name)) {
            $this->from_name = (string) config('app_notifications.mail.from.default.name');
        }

        $this->queue = $this->queue ?? true;
        $this->priority = $this->priority ?? 1;
    }

    /**
     * Данные отправителя: письмо с from вне разрешённого списка уходит
     * от дефолтного адреса, оригинальный from — в Reply-To.
     */
    public function getFromAddress() : FromAddressDto
    {
        $from_list = (array) config('app_notifications.mail.from');

        $from_list_emails = Arr::map($from_list, fn (array $value) => $value['email']);

        if (! in_array($this->from_email, $from_list_emails, true)) {
            $reply_to = $this->from_email;
            $from_email = $from_list['default']['email'];
        }

        return FromAddressDto::from([
            'from_email' => $from_email ?? $this->from_email,
            'from_name' => $this->from_name,
            'reply_to' => $reply_to ?? null,
        ]);
    }

    /**
     * Является ли письмо высокоприоритетным.
     */
    public function isHighPriority() : bool
    {
        return $this->priority >= 10;
    }
}
