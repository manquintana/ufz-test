<?php

namespace Ufz\ApiBase\EventListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Ufz\ApiBase\Telemetry\TracerFactory;

class TracingSubscriber implements EventSubscriberInterface
{
    private const SPAN_ATTRIBUTE = '_otel_span';
    private const SCOPE_ATTRIBUTE = '_otel_scope';
    private const CONTROLLER_SPAN_ATTRIBUTE = '_otel_controller_span';
    private const CONTROLLER_SCOPE_ATTRIBUTE = '_otel_controller_scope';
    private const SERIALIZE_SPAN_ATTRIBUTE = '_otel_serialize_span';
    private const SERIALIZE_SCOPE_ATTRIBUTE = '_otel_serialize_scope';
    private TracerFactory $tracerFactory;
    private ?object $propagationGetter;
    private ?LoggerInterface $logger;
    private static bool $logOnce = false;

    public function __construct(TracerFactory $tracerFactory, ?LoggerInterface $logger = null)
    {
        $this->tracerFactory = $tracerFactory;
        $this->propagationGetter = $this->buildPropagationGetter();
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::CONTROLLER => ['onKernelController', 0],
            KernelEvents::VIEW => ['onKernelView', 0],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->has(self::SPAN_ATTRIBUTE)) {
            return;
        }

        $tracer = $this->tracerFactory->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            $this->logOnce('OpenTelemetry tracer unavailable; request spans are not created.');
            return;
        }

        $spanName = sprintf('%s %s', $request->getMethod(), $request->getPathInfo());

        $spanBuilder = $tracer->spanBuilder($spanName);
        if (class_exists(\OpenTelemetry\API\Trace\SpanKind::class) && method_exists($spanBuilder, 'setSpanKind')) {
            $spanBuilder->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_SERVER);
        }

        if ($this->propagationGetter !== null && class_exists(\OpenTelemetry\API\Trace\Propagation\TraceContextPropagator::class)) {
            $context = \OpenTelemetry\API\Trace\Propagation\TraceContextPropagator::getInstance()
                ->extract($request, $this->propagationGetter);
            $spanBuilder->setParent($context);
        }

        $span = $spanBuilder->startSpan();
        $scope = method_exists($span, 'activate') ? $span->activate() : null;

        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('http.method', $request->getMethod());
            $span->setAttribute('http.scheme', $request->getScheme());
            $span->setAttribute('http.target', $request->getRequestUri());
            $span->setAttribute('http.url', $request->getUri());
            $span->setAttribute('http.host', $request->getHost());
            $span->setAttribute('net.host.port', $request->getPort());
        }

        $userAgent = $request->headers->get('user-agent');
        if (is_string($userAgent) && $userAgent !== '' && method_exists($span, 'setAttribute')) {
            $span->setAttribute('user_agent.original', $userAgent);
        }

        $clientIp = $request->getClientIp();
        if (is_string($clientIp) && $clientIp !== '' && method_exists($span, 'setAttribute')) {
            $span->setAttribute('client.address', $clientIp);
        }

        $this->addGraphqlAttributes($span, $request);

        $request->attributes->set(self::SPAN_ATTRIBUTE, $span);
        $request->attributes->set(self::SCOPE_ATTRIBUTE, $scope);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tracer = $this->tracerFactory->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            return;
        }

        $controller = $event->getController();
        $controllerName = $this->resolveControllerName($controller);

        $span = $tracer->spanBuilder('controller ' . $controllerName)->startSpan();
        $scope = method_exists($span, 'activate') ? $span->activate() : null;

        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('symfony.controller', $controllerName);
        }

        $request->attributes->set(self::CONTROLLER_SPAN_ATTRIBUTE, $span);
        $request->attributes->set(self::CONTROLLER_SCOPE_ATTRIBUTE, $scope);
    }

    public function onKernelView(ViewEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // End the controller span — controller work is done, serialization starts
        $this->endChildSpan($request, self::CONTROLLER_SPAN_ATTRIBUTE, self::CONTROLLER_SCOPE_ATTRIBUTE);

        // Start a serialization span
        $tracer = $this->tracerFactory->getTracer();
        if (!is_object($tracer) || !method_exists($tracer, 'spanBuilder')) {
            return;
        }

        $span = $tracer->spanBuilder('serialize')->startSpan();
        $scope = method_exists($span, 'activate') ? $span->activate() : null;

        $request->attributes->set(self::SERIALIZE_SPAN_ATTRIBUTE, $span);
        $request->attributes->set(self::SERIALIZE_SCOPE_ATTRIBUTE, $scope);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $span = $this->getSpan($event->getRequest());
        if ($span === null || !method_exists($span, 'recordException')) {
            return;
        }

        $throwable = $event->getThrowable();
        $span->recordException($throwable);
        if (method_exists($span, 'setStatus') && class_exists(\OpenTelemetry\API\Trace\StatusCode::class)) {
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $throwable->getMessage());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // End any open child spans (controller or serialize)
        $this->endChildSpan($request, self::SERIALIZE_SPAN_ATTRIBUTE, self::SERIALIZE_SCOPE_ATTRIBUTE);
        $this->endChildSpan($request, self::CONTROLLER_SPAN_ATTRIBUTE, self::CONTROLLER_SCOPE_ATTRIBUTE);

        $span = $this->getSpan($request);
        if ($span === null) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();
        if (method_exists($span, 'setAttribute')) {
            $span->setAttribute('http.status_code', $statusCode);
        }

        $route = $request->attributes->get('_route');
        if (is_string($route) && $route !== '' && method_exists($span, 'setAttribute')) {
            $span->setAttribute('http.route', $route);
        }

        if ($statusCode >= 500 && method_exists($span, 'setStatus') && class_exists(\OpenTelemetry\API\Trace\StatusCode::class)) {
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        }

        if (method_exists($span, 'end')) {
            $span->end();
        }

        $scope = $this->getScope($request);
        if ($scope !== null && method_exists($scope, 'detach')) {
            $scope->detach();
        }
    }

    private function endChildSpan(Request $request, string $spanAttr, string $scopeAttr): void
    {
        $span = $request->attributes->get($spanAttr);
        if (is_object($span) && method_exists($span, 'end')) {
            $span->end();
        }

        $scope = $request->attributes->get($scopeAttr);
        if (is_object($scope) && method_exists($scope, 'detach')) {
            $scope->detach();
        }

        $request->attributes->remove($spanAttr);
        $request->attributes->remove($scopeAttr);
    }

    private function resolveControllerName(mixed $controller): string
    {
        if (is_array($controller) && count($controller) === 2) {
            $class = is_object($controller[0]) ? get_class($controller[0]) : (string) $controller[0];
            return $class . '::' . $controller[1];
        }

        if (is_object($controller) && !$controller instanceof \Closure) {
            return get_class($controller) . '::__invoke';
        }

        if (is_string($controller)) {
            return $controller;
        }

        return 'Closure';
    }

    private function getSpan(Request $request): ?object
    {
        $span = $request->attributes->get(self::SPAN_ATTRIBUTE);
        return is_object($span) ? $span : null;
    }

    private function getScope(Request $request): ?object
    {
        $scope = $request->attributes->get(self::SCOPE_ATTRIBUTE);
        return is_object($scope) ? $scope : null;
    }

    private function buildPropagationGetter(): ?object
    {
        $interface = 'OpenTelemetry\\Context\\Propagation\\PropagationGetterInterface';
        if (!interface_exists($interface)) {
            return null;
        }

        return new class implements \OpenTelemetry\Context\Propagation\PropagationGetterInterface {
            public function keys($carrier): array
            {
                if ($carrier instanceof Request) {
                    return array_keys($carrier->headers->all());
                }

                return [];
            }

            public function get($carrier, string $key): ?string
            {
                if ($carrier instanceof Request) {
                    $value = $carrier->headers->get($key);
                    return $value !== null ? (string) $value : null;
                }

                return null;
            }
        };
    }

    private function logOnce(string $message): void
    {
        if (self::$logOnce) {
            return;
        }
        self::$logOnce = true;

        if ($this->logger) {
            $this->logger->warning($message);
            return;
        }

        error_log($message);
    }

    private function addGraphqlAttributes(object $span, Request $request): void
    {
        if (!method_exists($span, 'setAttribute')) {
            return;
        }

        $query = $this->extractGraphqlQuery($request);
        if (!is_string($query) || $query === '') {
            return;
        }

        $span->setAttribute('graphql.document', $query);

        $operationName = $this->extractGraphqlOperationName($request, $query);
        if ($operationName !== null) {
            $span->setAttribute('graphql.operation.name', $operationName);
        }

        $operationType = $this->extractGraphqlOperationType($query);
        if ($operationType !== null) {
            $span->setAttribute('graphql.operation.type', $operationType);
        }
    }

    private function extractGraphqlQuery(Request $request): ?string
    {
        $queryParam = $request->query->get('query');
        if (is_string($queryParam) && $queryParam !== '') {
            return $queryParam;
        }

        $queryBody = $request->request->get('query');
        if (is_string($queryBody) && $queryBody !== '') {
            return $queryBody;
        }

        $contentType = (string) $request->headers->get('content-type', '');
        $raw = $request->getContent();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        if (stripos($contentType, 'application/graphql') !== false) {
            return $raw;
        }

        if (stripos($contentType, 'application/json') === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $query = $data['query'] ?? null;
        if (is_string($query) && $query !== '') {
            return $query;
        }

        if (isset($data[0]) && is_array($data[0])) {
            $batchQuery = $data[0]['query'] ?? null;
            if (is_string($batchQuery) && $batchQuery !== '') {
                return $batchQuery;
            }
        }

        return null;
    }

    private function extractGraphqlOperationName(Request $request, string $query): ?string
    {
        $nameParam = $request->query->get('operationName');
        if (is_string($nameParam) && $nameParam !== '') {
            return $nameParam;
        }

        $nameBody = $request->request->get('operationName');
        if (is_string($nameBody) && $nameBody !== '') {
            return $nameBody;
        }

        if (preg_match('/^\s*(query|mutation|subscription)\s+([_A-Za-z][_0-9A-Za-z]*)/i', $query, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    private function extractGraphqlOperationType(string $query): ?string
    {
        if (preg_match('/^\s*(query|mutation|subscription)\b/i', $query, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
