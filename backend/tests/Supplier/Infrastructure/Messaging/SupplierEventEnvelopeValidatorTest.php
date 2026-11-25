<?php

declare(strict_types=1);

namespace App\Tests\Supplier\Infrastructure\Messaging;

use App\Supplier\Infrastructure\Messaging\SupplierEventEnvelopeValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SupplierEventEnvelopeValidatorTest extends TestCase
{
    public function testAcceptsAValidEventAndReturnsTheOriginalPayload(): void
    {
        $event = [
            'eventId' => 'ABC123',
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 42,
            'payload' => ['name' => 'Example Supplier'],
        ];

        self::assertSame($event, (new SupplierEventEnvelopeValidator())->validate($event, 'ABC123'));
    }

    public function testRejectsAMismatchedRabbitMqMessageId(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identity does not match');

        (new SupplierEventEnvelopeValidator())->validate($this->validEvent(), 'OTHER-ID');
    }

    /** @dataProvider invalidEvents */
    public function testRejectsInvalidEventEnvelopes(mixed $event): void
    {
        $this->expectException(RuntimeException::class);

        (new SupplierEventEnvelopeValidator())->validate($event);
    }

    public static function invalidEvents(): iterable
    {
        yield 'not an array' => ['not-an-event'];
        yield 'missing event id' => [[
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 42,
            'payload' => [],
        ]];
        yield 'empty event id' => [[
            'eventId' => ' ',
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 42,
            'payload' => [],
        ]];
        yield 'empty event type' => [[
            'eventId' => 'ABC123',
            'eventType' => '',
            'aggregateId' => 42,
            'payload' => [],
        ]];
        yield 'invalid aggregate id' => [[
            'eventId' => 'ABC123',
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 0,
            'payload' => [],
        ]];
        yield 'invalid payload' => [[
            'eventId' => 'ABC123',
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 42,
            'payload' => 'not-an-array',
        ]];
    }

    /** @return array<string, mixed> */
    private function validEvent(): array
    {
        return [
            'eventId' => 'ABC123',
            'eventType' => 'SupplierUpdated',
            'aggregateId' => 42,
            'payload' => [],
        ];
    }
}
