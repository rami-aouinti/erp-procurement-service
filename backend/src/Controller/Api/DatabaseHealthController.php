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
            $connection->connect();
            $connection->fetchOne('SELECT 1 FROM DUAL');

            return new JsonResponse([
                'status' => 'ok',
                'database' => 'oracle',
                'service' => 'FREEPDB1',
                'connected' => true,
            ]);
        } catch (Throwable $exception) {
            $logger->error('Oracle database health check failed.', [
                'exception' => $exception,
            ]);

            return new JsonResponse([
                'status' => 'error',
                'database' => 'oracle',
                'connected' => false,
                'message' => 'Oracle connection failed. Check the backend logs for details.',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
