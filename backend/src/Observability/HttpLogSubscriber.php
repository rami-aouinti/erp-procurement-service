<?php

declare(strict_types=1);

namespace App\Observability;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpLogSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 2048],
            KernelEvents::EXCEPTION => ['onException', -2048],
            KernelEvents::RESPONSE => ['onResponse', -2048],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->getPathInfo() === '/metrics') {
            return;
        }
        $request->attributes->set('_erp_request_id', bin2hex(random_bytes(12)));
        $request->attributes->set('_erp_log_started_at', microtime(true));
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set('_erp_exception_class', $event->getThrowable()::class);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $requestId = $request->attributes->get('_erp_request_id');
        if (!is_string($requestId)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Request-ID', $requestId);
        $startedAt = $request->attributes->get('_erp_log_started_at');
        $durationMs = is_numeric($startedAt) ? round((microtime(true) - (float) $startedAt) * 1000, 2) : null;
        $status = $response->getStatusCode();
        $level = $status >= 500 ? LogLevel::ERROR : ($status >= 400 ? LogLevel::WARNING : LogLevel::INFO);
        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'route' => (string) $request->attributes->get('_route', 'unmatched'),
            'status_code' => $status,
            'duration_ms' => $durationMs,
        ];
        $exceptionClass = $request->attributes->get('_erp_exception_class');
        if (is_string($exceptionClass)) {
            $context['exception_class'] = $exceptionClass;
        }
        $this->logger->log($level, 'HTTP request completed', $context);
    }
}
