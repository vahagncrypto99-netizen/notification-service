<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Notification Service API',
    description: 'Сервис уведомлений: создание уведомлений, асинхронная доставка в каналы (email, telegram) с гарантией доставки, отчёты по уведомлениям пользователя за период.'
)]
#[OA\Server(url: '/api', description: 'REST API')]
#[OA\SecurityScheme(
    securityScheme: 'bearer_token',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer-токен, идентифицирующий сервис-клиент (пары token:secret — в API_AUTH_CLIENTS).'
)]
#[OA\SecurityScheme(
    securityScheme: 'request_timestamp',
    type: 'apiKey',
    in: 'header',
    name: 'X-Timestamp',
    description: 'Unix-время запроса; окно валидности — API_SIGNATURE_TTL секунд (replay-защита). В Swagger UI можно оставить пустым: интерцептор подставит текущее время автоматически.'
)]
#[OA\SecurityScheme(
    securityScheme: 'request_signature',
    type: 'apiKey',
    in: 'header',
    name: 'X-Signature',
    description: 'HMAC-подпись канонического запроса: hmac_sha256(secret, METHOD \n URI \n TIMESTAMP \n BODY). В Swagger UI введите сюда SIGNING SECRET клиента (например local-dev-secret) — интерцептор сам вычислит подпись для каждого запроса.'
)]
#[OA\Schema(
    schema: 'ApiError',
    description: 'Единый контракт ошибки',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Уведомление не найдено.'),
        new OA\Property(property: 'payload', type: 'object', example: []),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    description: 'Единый контракт ошибки валидации',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Ошибка валидации.'),
        new OA\Property(
            property: 'payload',
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['message' => ['The message field is required.']]
                ),
            ],
            type: 'object'
        ),
    ]
)]
#[OA\Schema(
    schema: 'Pagination',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
    ]
)]
class OpenApi
{
    //
}
