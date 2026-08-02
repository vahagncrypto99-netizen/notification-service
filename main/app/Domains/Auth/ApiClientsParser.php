<?php

declare(strict_types=1);

namespace App\Domains\Auth;

use RuntimeException;

class ApiClientsParser
{
    /**
     * Минимальная длина секрета подписи.
     */
    private const MIN_SECRET_LENGTH = 12;

    /**
     * Разбор пар «токен:секрет_подписи» из конфигурации.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException при некорректной или пустой конфигурации
     */
    public function parse(string $raw) : array
    {
        $clients = [];

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            $token = strstr($pair, ':', true);
            $secret = (string) substr((string) strstr($pair, ':'), 1);

            if ($token === false || $token === '' || $secret === '') {
                throw new RuntimeException(
                    "Некорректная пара в API_AUTH_CLIENTS: '{$pair}' — ожидается формат token:secret."
                );
            }

            if (isset($clients[$token])) {
                throw new RuntimeException("Дубликат токена в API_AUTH_CLIENTS: '{$token}'.");
            }

            if (strlen($secret) < self::MIN_SECRET_LENGTH) {
                throw new RuntimeException(
                    "Секрет токена '{$token}' короче ".self::MIN_SECRET_LENGTH.' символов.'
                );
            }

            $clients[$token] = $secret;
        }

        /**
         * Пустая карта — все запросы молча получали бы 401;
         * ошибка конфигурации должна быть видна на старте.
         */
        if ($clients === []) {
            throw new RuntimeException('API_AUTH_CLIENTS пуст — не настроен ни один клиент API.');
        }

        return $clients;
    }

    /**
     * Проверка, что настроенный HMAC-алгоритм поддерживается.
     *
     * @throws RuntimeException для неподдерживаемого алгоритма
     */
    public function validatedAlgo(string $algo) : string
    {
        if (! in_array($algo, hash_hmac_algos(), true)) {
            throw new RuntimeException(
                "Неподдерживаемый алгоритм подписи в API_SIGNATURE_ALGO: '{$algo}'."
            );
        }

        return $algo;
    }
}
