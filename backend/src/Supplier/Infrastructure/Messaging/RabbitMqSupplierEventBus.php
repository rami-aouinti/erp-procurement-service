<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Messaging;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use AMQPTable;
use RuntimeException;
use Throwable;

final readonly class RabbitMqSupplierEventBus
{
    private const EVENTS_EXCHANGE = 'supplier.events';
    private const RETRY_EXCHANGE = 'supplier.retry';
    private const DEAD_EXCHANGE = 'supplier.dead';
    private const EVENTS_QUEUE = 'supplier.events.queue';
    private const RETRY_QUEUE = 'supplier.events.retry';
    private const DEAD_QUEUE = 'supplier.events.dead';
    private const ROUTING_KEY = 'supplier.changed';
    private const MAX_RETRIES = 5;

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $vhost,
    ) {
    }

    /** @param array<string, mixed> $event */
    public function publish(array $event): void
    {
        $connection = $this->connection();
        try {
            $channel = new AMQPChannel($connection);
            $this->declareTopology($channel);
            $channel->confirmSelect();
            $exchange = $this->exchange($channel, self::EVENTS_EXCHANGE);
            $published = $exchange->publish(
                json_encode($event, JSON_THROW_ON_ERROR),
                self::ROUTING_KEY,
                AMQP_NOPARAM,
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                    'message_id' => (string) ($event['eventId'] ?? ''),
                ],
            );
            if (!$published || !$channel->waitForConfirm(5.0)) {
                throw new RuntimeException('RabbitMQ did not confirm supplier event publication.');
            }
        } finally {
            $connection->disconnect();
        }
    }

    /**
     * @param callable(array<string, mixed>): void $handler
     */
    public function consume(callable $handler): void
    {
        $connection = $this->connection();
        $channel = new AMQPChannel($connection);
        $this->declareTopology($channel);
        $channel->qos(0, 1);
        $queue = new AMQPQueue($channel);
        $queue->setName(self::EVENTS_QUEUE);
        $queue->setFlags(AMQP_DURABLE);
        $queue->declareQueue();

        $queue->consume(function (AMQPEnvelope $envelope, AMQPQueue $queue) use ($handler, $channel): void {
            $tag = $envelope->getDeliveryTag();
            try {
                $event = json_decode($envelope->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($event) || !isset($event['eventId'], $event['eventType'], $event['payload']) || !is_array($event['payload'])) {
                    throw new RuntimeException('Invalid supplier event message.');
                }
                $handler($event);
                $queue->ack($tag);
            } catch (Throwable $exception) {
                $headers = $envelope->getHeaders();
                $retryCount = is_array($headers) ? (int) ($headers['x-retry-count'] ?? 0) : 0;
                $targetExchange = $retryCount < self::MAX_RETRIES ? self::RETRY_EXCHANGE : self::DEAD_EXCHANGE;
                $routingKey = $retryCount < self::MAX_RETRIES ? 'supplier.retry' : 'supplier.dead';
                $exchange = $this->exchange($channel, $targetExchange);
                $properties = [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                    'message_id' => $envelope->getMessageId(),
                    'headers' => new AMQPTable(['x-retry-count' => $retryCount + 1, 'x-last-error' => substr($exception->getMessage(), 0, 500)]),
                ];
                if (!$exchange->publish($envelope->getBody(), $routingKey, AMQP_NOPARAM, $properties)) {
                    $queue->nack($tag, AMQP_REQUEUE);
                    return;
                }
                $queue->ack($tag);
                error_log(sprintf('Supplier event %s failed (retry %d): %s', $envelope->getMessageId(), $retryCount + 1, $exception->getMessage()));
            }
        }, AMQP_NOPARAM, 'supplier-read-model-consumer');

        $connection->disconnect();
    }

    private function connection(): AMQPConnection
    {
        $connection = new AMQPConnection([
            'host' => $this->host,
            'port' => $this->port,
            'login' => $this->user,
            'password' => $this->password,
            'vhost' => $this->vhost,
            'connect_timeout' => 5,
            'read_timeout' => 60,
            'write_timeout' => 5,
            'heartbeat' => 30,
        ]);
        // AMQPConnection::connect() returns void; a successful connection is
        // exposed through isConnected(). Checking the return value makes every
        // successful connection look like a failure (because it is null).
        $connection->connect();
        if (!$connection->isConnected()) {
            throw new RuntimeException(sprintf(
                'Unable to connect to RabbitMQ at %s:%d (vhost: %s).',
                $this->host,
                $this->port,
                $this->vhost,
            ));
        }

        return $connection;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $this->exchange($channel, self::EVENTS_EXCHANGE);
        $this->exchange($channel, self::RETRY_EXCHANGE);
        $this->exchange($channel, self::DEAD_EXCHANGE);

        $events = new AMQPQueue($channel);
        $events->setName(self::EVENTS_QUEUE);
        $events->setFlags(AMQP_DURABLE);
        $events->declareQueue();
        $events->bind(self::EVENTS_EXCHANGE, self::ROUTING_KEY);

        $retry = new AMQPQueue($channel);
        $retry->setName(self::RETRY_QUEUE);
        $retry->setFlags(AMQP_DURABLE);
        $retry->setArguments(new AMQPTable([
            'x-message-ttl' => 5000,
            'x-dead-letter-exchange' => self::EVENTS_EXCHANGE,
            'x-dead-letter-routing-key' => self::ROUTING_KEY,
        ]));
        $retry->declareQueue();
        $retry->bind(self::RETRY_EXCHANGE, 'supplier.retry');

        $dead = new AMQPQueue($channel);
        $dead->setName(self::DEAD_QUEUE);
        $dead->setFlags(AMQP_DURABLE);
        $dead->declareQueue();
        $dead->bind(self::DEAD_EXCHANGE, 'supplier.dead');
    }

    private function exchange(AMQPChannel $channel, string $name): AMQPExchange
    {
        $exchange = new AMQPExchange($channel);
        $exchange->setName($name);
        $exchange->setType(AMQP_EX_TYPE_DIRECT);
        $exchange->setFlags(AMQP_DURABLE);
        $exchange->declareExchange();

        return $exchange;
    }
}
