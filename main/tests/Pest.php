<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Unit');

beforeEach(function () : void {
    //
});

/**
 * Заголовки авторизации API для тестов (валидный Bearer-токен
 * из .env.testing).
 *
 * @return array<string, string>
 */
function apiHeaders() : array
{
    return ['Authorization' => 'Bearer testing-token'];
}
