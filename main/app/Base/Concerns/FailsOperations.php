<?php

declare(strict_types=1);

namespace App\Base\Concerns;

use App\Exceptions\OperationException;
use Illuminate\Support\Facades\Log;
use Throwable;

trait FailsOperations
{
    /**
     * Неожиданный сбой операции: лог + Sentry, наружу — OperationException.
     *
     * @param  array<string, mixed>  $context  идентификаторы и параметры операции для мониторинга
     *
     * @throws OperationException
     */
    private function fail(string $message, Throwable $exception, array $context = []) : never
    {
        Log::error($message, $context + ['error' => $exception->getMessage()]);

        report($exception);

        throw new OperationException($message, 500, $exception);
    }
}
