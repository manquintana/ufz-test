<?php

namespace Ufz\ApiBase\Aws\Middleware;

use Aws\CommandInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Middleware to add Content-MD5 header for DeleteObjects requests.
 * 
 * ActiveScale and other older S3-compatible services require the legacy
 * Content-MD5 header for DeleteObjects operations, while modern AWS SDK
 * uses x-amz-checksum-* headers instead.
 */
class AddContentMd5Middleware
{
    /**
     * Wrap handler to add Content-MD5 for DeleteObjects
     */
    public static function wrap(callable $handler): callable
    {
        return function (CommandInterface $command, RequestInterface $request) use ($handler) {
            // Only add Content-MD5 for DeleteObjects operations
            if ($command->getName() === 'DeleteObjects') {
                // Remove any x-amz-checksum-* headers that AWS SDK might have added
                foreach (array_keys($request->getHeaders()) as $header) {
                    if (stripos($header, 'x-amz-checksum-') === 0) {
                        $request = $request->withoutHeader($header);
                    }
                }
                
                $body = $request->getBody();
                
                // Check if body is readable
                if ($body->isSeekable()) {
                    $body->rewind();
                    $bodyContent = $body->getContents();
                    $body->rewind();
                    
                    // Calculate MD5 hash
                    $md5 = base64_encode(md5($bodyContent, true));
                    
                    // Add Content-MD5 header
                    $request = $request->withHeader('Content-MD5', $md5);
                }
            }

            return $handler($command, $request);
        };
    }
}
