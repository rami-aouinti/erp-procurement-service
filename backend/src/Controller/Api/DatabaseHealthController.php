<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DatabaseHealthController
{
    #[Route('/api/database/health', name: 'api_database_health', methods: ['GET'])]
    public function __invoke(Connection $connection, LoggerInterface $logger): JsonResponse
    {
        try {
            // DBAL opens the connection lazily when the query is executed.
            // Connection::connect() is protected and must not be called here.
            $connection->fetchOne('SELECT 1 FROM DUAL');

            return new JsonResponse([
                'status' => 'ok',
                'database' => 'oracle',
                'service' => 'FREEPDB1',
                'connected' => true,
            ]);
        } catch (Throwable $exception) {
            $detail = $exception::class . ': ' . $exception->getMessage();
            $logger->error('Oracle database health check failed: {detail}', [
                'detail' => $detail,
                'exception' => $exception,
            ]);

            return new JsonResponse([
                'status' => 'error',
                'database' => 'oracle',
                'connected' => false,
                'message' => 'Oracle connection failed.',
                'detail' => $detail,
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
