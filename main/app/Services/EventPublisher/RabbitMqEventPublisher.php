<?php

declare(strict_types=1);

namespace App\Services\EventPublisher;

use App\Dto\EventEnvelopeDto;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMqEventPublisher implements EventPublisherInterface
{
    /**
     * Канал публикации (отдельный от канала consumer-а).
     */
    private ?AMQPChannel $publish_channel = null;

    /**
     * RabbitMqEventPublisher constructor.
     *
     * @param  string  $exchange  имя fanout-exchange для событий
     */
    public function __construct(
        private readonly QueueFactory $queue_factory,
        private readonly string $exchange,
    ) {
        //
    }

    /**
     * Публикация конверта в durable fanout-exchange: каждый подписчик
     * (аналитика, аудит, ...) заводит свою очередь и биндит её к exchange —
     * издатель о подписчиках не знает.
     */
    public function publish(EventEnvelopeDto $envelope) : void
    {
        $channel = $this->channel();

        $channel->exchange_declare(
            $this->exchange,
            AMQPExchangeType::FANOUT,
            false,
            true,
            false
        );

        $message = new AMQPMessage($envelope->toJson(), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $channel->basic_publish($message, $this->exchange);
    }

    /**
     * Отдельный AMQP-канал публикации поверх общего соединения:
     * channel-level ошибка AMQP закрывает канал целиком, и публикация
     * не должна ронять канал consumer-а воркера.
     */
    private function channel() : AMQPChannel
    {
        if ($this->publish_channel !== null && $this->publish_channel->is_open()) {
            return $this->publish_channel;
        }

        $queue = $this->queue_factory->connection('rabbitmq');

        if (! $queue instanceof RabbitMQQueue) {
            throw new RuntimeException(
                'Публикация событий требует rabbitmq-коннекшена очередей.'
            );
        }

        return $this->publish_channel = $queue->getConnection()->channel();
    }
}
