<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class OperationException extends Exception
{
    /**
     * OperationException constructor.
     *
     * @param  string  $message  человекочитаемое сообщение для ответа API
     * @param  int  $status_code  HTTP-статус ответа
     * @param  Throwable|null  $previous  исходное исключение
     */
    public function __construct(
        string $message,
        private readonly int $status_code = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * HTTP-статус для ответа API.
     */
    public function getStatusCode() : int
    {
        return $this->status_code;
    }
}
