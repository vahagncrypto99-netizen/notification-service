<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next) : Response
    {
        $provided_token = $request->bearerToken();

        if ($provided_token !== null && $this->isValidToken($provided_token)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    /**
     * Проверка токена по списку разрешённых.
     */
    private function isValidToken(string $provided_token) : bool
    {
        foreach ($this->allowedTokens() as $token) {
            if (hash_equals($token, $provided_token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Список разрешённых токенов из конфигурации.
     *
     * @return array<int, string>
     */
    private function allowedTokens() : array
    {
        $raw = (string) config('auth.api.tokens', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
