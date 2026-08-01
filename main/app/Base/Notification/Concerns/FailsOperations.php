<?php

declare(strict_types=1);

namespace App\Base\Notification\Concerns;

use App\Exceptions\OperationException;
use Illuminate\Support\Facades\Log;
use Throwable;

trait FailsOperations
{
    /**
     * Неожиданный сбой операции: лог + Sentry, наружу — OperationException.
     *
     * @throws OperationException
     */
    private function fail(string $message, Throwable $exception) : never
    {
        Log::error($message, ['error' => $exception->getMessage()]);

        report($exception);

        throw new OperationException($message, 500, $exception);
    }
}
