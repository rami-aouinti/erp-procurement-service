<?php

declare(strict_types=1);

namespace App\Observability;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController
{
    #[Route('/metrics', name: 'app_metrics', methods: ['GET'])]
    public function __invoke(MetricsRegistry $metrics): Response
    {
        return new Response($metrics->render(), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
