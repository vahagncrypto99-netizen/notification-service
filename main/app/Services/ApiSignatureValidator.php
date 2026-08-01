<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\RequestSignatureDto;

class ApiSignatureValidator
{
    /**
     * Проверка HMAC-подписи запроса.
     *
     * Bearer-токен идентифицирует сервис-клиент, подпись доказывает
     * владение его секретом (секрет по сети не передаётся):
     *
     *   X-Signature = hmac_sha256(secret, timestamp . "." . body)
     *
     * Окно валидности timestamp защищает от повторного проигрывания
     * перехваченного запроса (replay).
     */
    public function validate(RequestSignatureDto $dto) : bool
    {
        if ($dto->token === null || $dto->timestamp === null || $dto->signature === null) {
            return false;
        }

        $secret = $this->secretForToken($dto->token);

        if ($secret === null || ! $this->isFreshTimestamp($dto->timestamp)) {
            return false;
        }

        $expected_signature = hash_hmac(
            'sha256',
            $dto->timestamp.'.'.$dto->body,
            $secret
        );

        return hash_equals($expected_signature, $dto->signature);
    }

    /**
     * Секрет подписи для предъявленного токена (timing-safe поиск).
     */
    private function secretForToken(string $provided_token) : ?string
    {
        foreach ($this->clients() as $token => $secret) {
            if (hash_equals($token, $provided_token)) {
                return $secret;
            }
        }

        return null;
    }

    /**
     * Timestamp внутри окна валидности — защита от replay.
     */
    private function isFreshTimestamp(string $timestamp) : bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $ttl = (int) config('auth.api.signature_ttl');

        return abs(time() - (int) $timestamp) <= $ttl;
    }

    /**
     * Карта «токен → секрет подписи» из конфигурации.
     *
     * @return array<string, string>
     */
    private function clients() : array
    {
        $raw = (string) config('auth.api.clients', '');

        $clients = [];

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            if (! str_contains($pair, ':')) {
                continue;
            }

            [$token, $secret] = explode(':', $pair, 2);

            $clients[$token] = $secret;
        }

        return $clients;
    }
}
