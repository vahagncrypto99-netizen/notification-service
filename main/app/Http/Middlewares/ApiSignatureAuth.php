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
        $valid = $this->signature_validator->validate(new RequestSignatureDto(
            token: $request->bearerToken(),
            timestamp: $request->header('X-Timestamp'),
            signature: $request->header('X-Signature'),
            body: $request->getContent(),
        ));

        if (! $valid) {
            /**
             * Без деталей — злоумышленнику не сообщаем, что именно не так.
             */
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
