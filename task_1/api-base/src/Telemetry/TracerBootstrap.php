<?php

declare(strict_types=1);

namespace Ufz\ApiBase\Telemetry;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class TracerBootstrap implements EventSubscriberInterface
{
    public function __construct(private readonly TracerFactory $tracerFactory)
    {
        Tracer::setFactory($this->tracerFactory);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }

    public function onKernelRequest(): void
    {
        Tracer::setFactory($this->tracerFactory);
    }
}
