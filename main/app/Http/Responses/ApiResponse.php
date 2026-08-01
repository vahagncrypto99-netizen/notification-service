<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Успешный ответ API в едином контракте.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function success(string $message, int $code = 200, array $payload = []) : JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'payload' => $payload,
        ], $code);
    }

    /**
     * Ответ об ошибке в едином контракте.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function error(string $message, int $code = 400, array $payload = []) : JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'payload' => $payload,
        ], $code);
    }
}
