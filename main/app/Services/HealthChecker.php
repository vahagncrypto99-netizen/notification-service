<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\DatabaseManager;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class HealthChecker
{
    /**
     * HealthChecker constructor.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly QueueFactory $queue_factory,
        private readonly CacheFactory $cache_factory,
    ) {
        //
    }

    /**
     * Состояние зависимостей сервиса.
     *
     * @return array<string, bool>
     */
    public function checks() : array
    {
        return [
            'database' => $this->databaseAlive(),
            'queue' => $this->queueAlive(),
            'cache' => $this->cacheAlive(),
        ];
    }

    /**
     * Все зависимости живы.
     *
     * @param  array<string, bool>  $checks
     */
    public function healthy(array $checks) : bool
    {
        return ! in_array(false, $checks, true);
    }

    /**
     * Postgres отвечает на запрос.
     */
    private function databaseAlive() : bool
    {
        try {
            $this->database->connection()->select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Кэш принимает запись и отдаёт её обратно.
     */
    private function cacheAlive() : bool
    {
        try {
            $store = $this->cache_factory->store();

            $store->put('health_check', 'ok', 5);

            return $store->get('health_check') === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * AMQP-канал брокера открывается.
     */
    private function queueAlive() : bool
    {
        try {
            $queue = $this->queue_factory->connection('rabbitmq');

            return $queue instanceof RabbitMQQueue && $queue->getChannel()->is_open();
        } catch (Throwable) {
            return false;
        }
    }
}
