<?php

declare(strict_types=1);

namespace App\Supplier\Infrastructure\Messaging;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
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
            $this->publishConfirmed(
                $channel,
                self::EVENTS_EXCHANGE,
                json_encode($event, JSON_THROW_ON_ERROR),
                self::ROUTING_KEY,
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => 2,
                    'message_id' => (string) ($event['eventId'] ?? ''),
                ],
            );
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

        try {
            $channel = new AMQPChannel($connection);
            $this->declareTopology($channel);
            $channel->qos(0, 1);

            $queue = new AMQPQueue($channel);
            $queue->setName(self::EVENTS_QUEUE);
            $queue->setFlags(AMQP_DURABLE);
            $queue->declareQueue();

            // Register and start consuming in the same call. consume(null)
            // still blocks and can time out before the processing loop starts.
            $consumerTag = 'supplier-read-model-consumer';

            $callback = function (AMQPEnvelope $envelope, AMQPQueue $queue) use ($handler, $connection): void {
                $deliveryTag = $envelope->getDeliveryTag();

                try {
                    $event = json_decode($envelope->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    if (
                        !is_array($event)
                        || !isset($event['eventId'], $event['eventType'], $event['payload'])
                        || !is_array($event['payload'])
                    ) {
                        throw new RuntimeException('Invalid supplier event message.');
                    }

                    $handler($event);
                    $queue->ack($deliveryTag);
                } catch (Throwable $exception) {
                    $headers = $envelope->getHeaders();
                    $retryCount = is_array($headers) ? (int) ($headers['x-retry-count'] ?? 0) : 0;
                    $isRetry = $retryCount < self::MAX_RETRIES;
                    $targetExchange = $isRetry ? self::RETRY_EXCHANGE : self::DEAD_EXCHANGE;
                    $routingKey = $isRetry ? 'supplier.retry' : 'supplier.dead';

                    try {
                        // Use a dedicated publisher channel: publisher confirms and
                        // consumer acknowledgements must not share channel state.
                        $publisherChannel = new AMQPChannel($connection);
                        $this->publishConfirmed(
                            $publisherChannel,
                            $targetExchange,
                            $envelope->getBody(),
                            $routingKey,
                            [
                                'content_type' => 'application/json',
                                'delivery_mode' => 2,
                                'message_id' => $envelope->getMessageId(),
                                'headers' => [
                                    'x-retry-count' => $retryCount + 1,
                                    'x-last-error' => substr($exception->getMessage(), 0, 500),
                                ],
                            ],
                        );
                        $publisherChannel->close();
                    } catch (Throwable $publishException) {
                        // Never acknowledge the original until its retry/dead-letter
                        // copy has been confirmed by RabbitMQ.
                        $queue->nack($deliveryTag, AMQP_REQUEUE);
                        error_log(sprintf(
                            'Unable to republish supplier event %s: %s',
                            $envelope->getMessageId(),
                            $publishException->getMessage(),
                        ));

                        return;
                    }

                    $queue->ack($deliveryTag);
                    error_log(sprintf(
                        'Supplier event %s failed (retry %d): %s',
                        $envelope->getMessageId(),
                        $retryCount + 1,
                        $exception->getMessage(),
                    ));
                }
            };

            $consumerRegistered = false;

            while ($connection->isConnected()) {
                try {
                    if (!$consumerRegistered) {
                        // The first call registers the broker consumer and starts
                        // dispatching deliveries to the callback.
                        $consumerRegistered = true;
                        $queue->consume($callback, AMQP_NOPARAM, $consumerTag);

                        continue;
                    }

                    // Reuse the registered consumer after an expected idle timeout.
                    $queue->consume($callback, AMQP_JUST_CONSUME);
                } catch (\AMQPQueueException $exception) {
                    if (!str_contains($exception->getMessage(), 'Consumer timeout exceed')) {
                        throw $exception;
                    }
                }
            }

            throw new RuntimeException('RabbitMQ connection was closed while consuming supplier events.');
        } finally {
            if ($connection->isConnected()) {
                $connection->disconnect();
            }
        }
    }

    private function publishConfirmed(
        AMQPChannel $channel,
        string $exchangeName,
        string $body,
        string $routingKey,
        array $properties,
    ): void {
        $channel->confirmSelect();

        // ext-amqp 2.2 dispatches basic.ack/basic.nack through these callbacks.
        // Registering them before publish prevents the unhandled basic.ack warning.
        $channel->setConfirmCallback(
            static fn (): bool => false,
            static fn (): bool => throw new RuntimeException('RabbitMQ negatively acknowledged a supplier event.'),
        );

        $exchange = $this->exchange($channel, $exchangeName);
        // ext-amqp 2.x declares AMQPExchange::publish() as void. The broker
        // acknowledgement is asynchronous, so its result must be checked via
        // waitForConfirm(), not via the return value of publish().
        $exchange->publish($body, $routingKey, AMQP_NOPARAM, $properties);

        // A timeout or nack is propagated to the outbox publisher, which leaves
        // the event pending for a later retry instead of marking it published.
        $channel->waitForConfirm(5.0);
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
            // A bounded read lets the consumer periodically process idle timeouts
            // and lets librabbitmq check heartbeats while waiting for deliveries.
            'read_timeout' => 5,
            'write_timeout' => 5,
            'heartbeat' => 30,
        ]);

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
        $retry->setArguments([
            'x-message-ttl' => 5000,
            'x-dead-letter-exchange' => self::EVENTS_EXCHANGE,
            'x-dead-letter-routing-key' => self::ROUTING_KEY,
        ]);
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
