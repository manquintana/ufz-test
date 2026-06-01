<?php

namespace Ufz\ApiBase\Telemetry\Http;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class TracingResponseStream implements ResponseStreamInterface
{
    private ResponseStreamInterface $stream;
    private \SplObjectStorage $responseMap;

    public function __construct(ResponseStreamInterface $stream, \SplObjectStorage $responseMap)
    {
        $this->stream = $stream;
        $this->responseMap = $responseMap;
    }

    public function current(): ChunkInterface
    {
        $chunk = $this->stream->current();
        $this->handleChunk($chunk);

        return $chunk;
    }

    public function key(): ResponseInterface
    {
        $response = $this->stream->key();
        if ($this->responseMap->contains($response)) {
            return $this->responseMap[$response];
        }

        return $response;
    }

    public function next(): void
    {
        $this->stream->next();
    }

    public function rewind(): void
    {
        $this->stream->rewind();
    }

    public function valid(): bool
    {
        return $this->stream->valid();
    }

    private function handleChunk(ChunkInterface $chunk): void
    {
        $response = $this->stream->key();
        if (!$this->responseMap->contains($response)) {
            return;
        }

        $wrapper = $this->responseMap[$response];
        if (!$wrapper instanceof TracingResponse) {
            return;
        }

        $error = null;
        try {
            $error = $chunk->getError();
            if ($chunk->isLast()) {
                $wrapper->finishFromStream($error);
            }
        } catch (\Throwable $exception) {
            $wrapper->finishFromStream($exception->getMessage());
        }
    }
}
