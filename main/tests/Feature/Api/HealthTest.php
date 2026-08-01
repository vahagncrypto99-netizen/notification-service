<?php

declare(strict_types=1);

use App\Services\HealthChecker;
use Illuminate\Database\DatabaseManager;

it('отвечает без аутентификации и показывает состояние зависимостей', function () : void {
    $this->getJson('/api/health')->assertOk()->assertJson([
        'success' => true,
    ])->assertJsonPath('payload.checks.database', true)->assertJsonPath(
        'payload.checks.queue',
        true
    )->assertJsonPath('payload.checks.cache', true);
});

it('отвечает 503, когда зависимость нездорова', function () : void {
    $checker = new class(app(DatabaseManager::class), app('queue'), app('cache')) extends HealthChecker
    {
        public function checks() : array
        {
            return ['database' => true, 'queue' => false, 'cache' => true];
        }
    };

    app()->instance(HealthChecker::class, $checker);

    $this->getJson('/api/health')->assertStatus(503)->assertJson([
        'success' => false,
        'message' => 'Сервис нездоров.',
    ])->assertJsonPath('payload.checks.queue', false);
});
