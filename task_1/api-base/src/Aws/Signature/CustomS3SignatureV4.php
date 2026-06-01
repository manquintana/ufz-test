<?php
namespace Ufz\ApiBase\Aws\Signature;

use Aws\Credentials\CredentialsInterface;
use Aws\Signature\S3SignatureV4;
use Aws\Signature\SignatureV4;
use Psr\Http\Message\RequestInterface;

/**
 * Custom S3 Signer that avoids adding X-Amz-Content-Sha256 during presign
 * and removes checksum headers that aren't supported by ActiveScale.
 *
 * This prevents headers from being added that cause issues with some
 * S3-compatible storages (e.g., ActiveScale).
 */
class CustomS3SignatureV4 extends S3SignatureV4
{
    /**
     * Presign without injecting X-Amz-Content-Sha256.
     *
     * Note: We keep the special handling for the global access point, but
     * deliberately call SignatureV4::presign() to skip S3SignatureV4's
     * behavior of adding the content SHA256 header.
     */
    public function presign(
        RequestInterface $request,
        CredentialsInterface $credentials,
        $expires,
        array $options = []
    ) {
        if (strpos($request->getUri()->getHost(), 'accesspoint.s3-global') !== false) {
            $request = $request->withHeader('x-amz-region-set', '*');
        }

        return SignatureV4::presign($request, $credentials, $expires, $options);
    }

    /**
     * Override signRequest to remove checksum headers before signing
     * 
     * ActiveScale (and similar S3-compatible services) don't support
     * x-amz-checksum-* headers and return 501 Not Implemented
     * @param RequestInterface $request
     * @param CredentialsInterface $credentials
     * @param null $signingService
     */
    public function signRequest(
        RequestInterface $request,
        CredentialsInterface $credentials,
        $signingService = null
    ): RequestInterface {
        // Remove all x-amz-checksum-* headers
        foreach (array_keys($request->getHeaders()) as $header) {
            if (stripos($header, 'x-amz-checksum-') === 0) {
                $request = $request->withoutHeader($header);
            }
        }

        return parent::signRequest($request, $credentials);
    }
}
