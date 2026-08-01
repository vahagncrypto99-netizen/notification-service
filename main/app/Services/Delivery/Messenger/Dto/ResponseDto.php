<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger\Dto;

use Spatie\LaravelData\Data;

class ResponseDto extends Data
{
    /**
     * ResponseDto constructor.
     */
    public function __construct(
        public readonly bool $success,
        public readonly bool $should_unsubscribe,
        public readonly bool $should_retry,
        public readonly ?string $message = null,
    ) {
        //
    }

    /**
     * Успешный ответ.
     */
    public static function success() : ResponseDto
    {
        return self::from([
            'success' => true,
            'should_unsubscribe' => false,
            'should_retry' => false,
        ]);
    }

    /**
     * Ответ с ошибкой.
     */
    public static function error(
        string $message,
        bool $should_unsubscribe = false,
        bool $should_retry = false,
    ) : ResponseDto {
        return self::from([
            'success' => false,
            'should_unsubscribe' => $should_unsubscribe,
            'should_retry' => $should_retry,
            'message' => $message,
        ]);
    }
}
