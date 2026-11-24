<?php

declare(strict_types=1);

namespace App\Observability;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MetricsRegistry $metrics)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1000],
            KernelEvents::RESPONSE => ['onResponse', -1000],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getRequest()->getPathInfo() === '/metrics') {
            return;
        }

        $event->getRequest()->attributes->set('_erp_metrics_started_at', microtime(true));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $startedAt = $request->attributes->get('_erp_metrics_started_at');
        if (!is_float($startedAt) && !is_int($startedAt)) {
            return;
        }

        $route = (string) $request->attributes->get('_route', 'unmatched');
        $this->metrics->observeRequest(
            $request->getMethod(),
            $route,
            $event->getResponse()->getStatusCode(),
            microtime(true) - (float) $startedAt,
        );
    }
}
