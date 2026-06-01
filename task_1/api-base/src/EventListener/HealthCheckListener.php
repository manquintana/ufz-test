<?php

declare(strict_types=1);

namespace Ufz\ApiBase\EventListener;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HealthCheckListener implements EventSubscriberInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if ($path === '/health') {
            $event->setResponse(new JsonResponse(['status' => 'ok']));
            return;
        }

        if ($path === '/health/ready') {
            try {
                $this->connection->executeQuery('SELECT 1');
                $event->setResponse(new JsonResponse([
                    'status' => 'ok',
                    'checks' => ['database' => 'ok'],
                ]));
            } catch (\Throwable $e) {
                $event->setResponse(new JsonResponse([
                    'status' => 'error',
                    'checks' => ['database' => 'error'],
                ], 503));
            }
        }
    }
}
