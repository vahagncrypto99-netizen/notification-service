<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Dto\RequestSignatureDto;
use App\Services\ApiSignatureValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSignatureAuth
{
    /**
     * ApiSignatureAuth constructor.
     */
    public function __construct(
        private readonly ApiSignatureValidator $signature_validator
    ) {
        //
    }

    /**
     * Service-to-service аутентификация: Bearer-токен + HMAC-подпись
     * запроса. Проверка — в ApiSignatureValidator.
     */
    public function handle(Request $request, Closure $next) : Response
    {
        $token = $request->bearerToken();
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        /**
         * Неполный набор заголовков отсекается до создания DTO —
         * валидатор всегда получает полные данные подписи.
         */
        if ($token === null || $timestamp === null || $signature === null) {
            return $this->unauthenticated();
        }

        $valid = $this->signature_validator->validate(new RequestSignatureDto(
            token: $token,
            timestamp: $timestamp,
            signature: $signature,
            method: $request->getMethod(),
            uri: $request->getRequestUri(),
            body: $request->getContent(),
        ));

        if (! $valid) {
            return $this->unauthenticated();
        }

        return $next($request);
    }

    private function unauthenticated() : Response
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
