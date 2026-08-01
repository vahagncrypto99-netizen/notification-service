<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger\Telegram;

use App\Services\Delivery\Messenger\Dto\ResponseDto;
use App\Services\Delivery\Messenger\Dto\SenderDto;
use App\Services\Delivery\Messenger\MessengerSenderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

class TelegramClient implements MessengerSenderInterface
{
    /**
     * Название ключа rate limiter-а.
     */
    protected const RATE_LIMITER_KEY = 'telegram_api_sender';

    /**
     * Ограничение количества запросов к API в секунду (лимит Telegram: 30 msg/sec).
     */
    protected const LIMIT_REQUEST_TO_API_PER_SECOND = 30;

    /**
     * Интервал ожидания при превышении лимита, микросекунды.
     */
    protected const RATE_LIMIT_SLEEP_MICROSECONDS = 100_000;

    /**
     * Время жизни записи в лимитере, секунды.
     */
    protected const RATE_LIMITER_DECAY_SECONDS = 1;

    /**
     * Отправка сообщения в Telegram.
     *
     * По условию задания реальной отправки нет: вместо вызова Bot API
     * (POST sendMessage) сообщение фиксируется в логе. Механика боевой
     * реализации сохранена: троттлинг под лимит API и контракт ответа
     * (заблокировал бота → отписка, 429/5xx → повторная попытка).
     */
    public function send(SenderDto $data) : ResponseDto
    {
        try {
            $this->enforceApiLimit();

            if (config('delivery.simulate_failures')) {
                throw new RuntimeException('Симулированный сбой отправки в Telegram.');
            }

            Log::info('[telegram] Сообщение отправлено.', [
                'chat_id' => $data->chat_id,
                'message' => $data->message,
            ]);

            return ResponseDto::success();
        } catch (Throwable $exception) {
            Log::error('[telegram] Ошибка отправки сообщения.', [
                'chat_id' => $data->chat_id,
                'error' => $exception->getMessage(),
            ]);

            return ResponseDto::error($exception->getMessage(), false, true);
        }
    }

    // ****************************************************************
    // *************************** Support ****************************
    // ****************************************************************

    /**
     * Ожидание снятия лимита API (если превышен) и инкремент счётчика.
     */
    protected function enforceApiLimit() : void
    {
        while (RateLimiter::tooManyAttempts(self::RATE_LIMITER_KEY, self::LIMIT_REQUEST_TO_API_PER_SECOND)) {
            usleep(self::RATE_LIMIT_SLEEP_MICROSECONDS);
        }

        RateLimiter::hit(self::RATE_LIMITER_KEY, self::RATE_LIMITER_DECAY_SECONDS);
    }
}
