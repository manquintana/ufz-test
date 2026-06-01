<?php

namespace Ufz\ApiBase\Aws;

use Aws\S3\S3Client;
use Ufz\ApiBase\Aws\Middleware\AddContentMd5Middleware;

/**
 * Factory for creating S3Client with Content-MD5 middleware for DeleteObjects.
 * 
 * This is needed for S3-compatible services (like ActiveScale) that require
 * the legacy Content-MD5 header instead of modern x-amz-checksum-* headers.
 */
class S3ClientFactory
{
    /**
     * Create an S3Client with middleware that adds Content-MD5 for DeleteObjects
     *
     * @param array $config S3Client configuration
     * @return S3Client
     */
    public static function create(array $config): S3Client
    {
        $client = new S3Client($config);
        
        // Add middleware to add Content-MD5 header for DeleteObjects
        // Use appendBuild to run before signing
        $client->getHandlerList()->appendBuild(
            AddContentMd5Middleware::wrap(...),
            'add-content-md5'
        );
        
        return $client;
    }
}
