<?php

declare(strict_types=1);

namespace App\\Supplier\\Infrastructure\\Messaging;

use RuntimeException;

final class SupplierEventEnvelopeValidator
{
    /** @param mixed $event
     *  @return array<string, mixed>
     */
    public function validate(mixed $event, ?string $messageId = null): array
    {
        if (
            !is_array($event)
            || !isset($event['eventId'], $event['eventType'], $event['aggregateId'], $event['payload'])
            || !is_string($event['eventId'])
            || trim($event['eventId']) === ''
            || !is_string($event['eventType'])
            || trim($event['eventType']) === ''
            || !is_numeric($event['aggregateId'])
            || (int) $event['aggregateId'] < 1
            || !is_array($event['payload'])
        ) {
            throw new RuntimeException('Invalid supplier event envelope.');
        }

        if ($messageId !== null && $messageId !== '' && $messageId !== $event['eventId']) {
            throw new RuntimeException('Supplier event identity does not match its message ID.');
        }

        return $event;
    }
}
