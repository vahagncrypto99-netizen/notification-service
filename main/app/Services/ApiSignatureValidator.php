<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\RequestSignatureDto;

class ApiSignatureValidator
{
    /**
     * ApiSignatureValidator constructor.
     *
     * @param  array<string, string>  $clients  карта «токен → секрет подписи»
     * @param  int  $signature_ttl  окно валидности timestamp, секунды
     * @param  string  $signature_algo  алгоритм HMAC
     */
    public function __construct(
        private readonly array $clients,
        private readonly int $signature_ttl,
        private readonly string $signature_algo,
    ) {
        //
    }

    /**
     * Проверка HMAC-подписи запроса.
     *
     * Bearer-токен идентифицирует сервис-клиент, подпись доказывает
     * владение его секретом (секрет по сети не передаётся):
     *
     *   X-Signature = hmac(secret, METHOD \n URI \n TIMESTAMP \n BODY)
     *
     * Подпись привязана к методу, пути с query, времени и телу — её нельзя
     * переиспользовать для другого запроса; окно timestamp закрывает replay.
     */
    public function validate(RequestSignatureDto $dto) : bool
    {
        $secret = $this->secretForToken($dto->token);

        if ($secret === null || ! $this->isFreshTimestamp($dto->timestamp)) {
            return false;
        }

        $expected_signature = hash_hmac(
            $this->signature_algo,
            $this->canonicalString($dto),
            $secret
        );

        return hash_equals($expected_signature, $dto->signature);
    }

    /**
     * Каноническая строка запроса — всё, что подпись обязана покрывать.
     */
    private function canonicalString(RequestSignatureDto $dto) : string
    {
        return implode("\n", [
            strtoupper($dto->method),
            $dto->uri,
            $dto->timestamp,
            $dto->body,
        ]);
    }

    /**
     * Секрет подписи для предъявленного токена (timing-safe поиск).
     */
    private function secretForToken(string $provided_token) : ?string
    {
        foreach ($this->clients as $token => $secret) {
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

        return abs(time() - (int) $timestamp) <= $this->signature_ttl;
    }
}
