<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\RequestSignatureDto;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ApiSignatureValidator
{
    /**
     * Секрет для выравнивания времени ответа на неизвестный токен.
     */
    private const TIMING_DUMMY_SECRET = 'timing-equalization-dummy-secret';

    /**
     * ApiSignatureValidator constructor.
     *
     * @param  array<string, string>  $clients  карта «токен → секрет подписи»
     * @param  int  $signature_ttl  окно валидности timestamp, секунды
     * @param  string  $signature_algo  алгоритм HMAC
     * @param  Repository  $nonce_cache  хранилище одноразовых nonce
     */
    public function __construct(
        private readonly array $clients,
        private readonly int $signature_ttl,
        private readonly string $signature_algo,
        private readonly CacheRepository $nonce_cache,
    ) {
        //
    }

    /**
     * Проверка HMAC-подписи запроса.
     *
     * Bearer-токен идентифицирует сервис-клиент, подпись доказывает
     * владение его секретом (секрет по сети не передаётся):
     *
     *   X-Signature = hmac(secret, METHOD \n URI \n TIMESTAMP \n NONCE \n BODY)
     *
     * Подпись привязана к методу, пути с query, времени, nonce и телу —
     * её нельзя переиспользовать для другого запроса. Окно timestamp
     * ограничивает жизнь подписи, одноразовость nonce закрывает replay
     * внутри окна.
     */
    public function validate(RequestSignatureDto $dto) : bool
    {
        if (! $this->isFreshTimestamp($dto->timestamp)) {
            return false;
        }

        $secret = $this->secretForToken($dto->token);

        /**
         * Для неизвестного токена HMAC считается с фиктивным секретом —
         * время ответа не выдаёт, существует ли токен (timing-оракул).
         */
        $expected_signature = hash_hmac(
            $this->signature_algo,
            $this->canonicalString($dto),
            $secret ?? self::TIMING_DUMMY_SECRET
        );

        if ($secret === null || ! hash_equals($expected_signature, $dto->signature)) {
            return false;
        }

        return $this->consumeNonce($dto->token, $dto->nonce);
    }

    /**
     * Известен ли предъявленный токен (для ключа rate limiting-а
     * до аутентификации).
     */
    public function knownToken(string $token) : bool
    {
        return $this->secretForToken($token) !== null;
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
            $dto->nonce,
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
     * Timestamp внутри окна валидности.
     */
    private function isFreshTimestamp(string $timestamp) : bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= $this->signature_ttl;
    }

    /**
     * Одноразовость nonce: повтор уже принятой подписи внутри окна
     * TTL — replay, запрос отклоняется.
     */
    private function consumeNonce(string $token, string $nonce) : bool
    {
        return $this->nonce_cache->add(
            'api_nonce:'.hash('sha256', $token.':'.$nonce),
            true,
            $this->signature_ttl
        );
    }
}
