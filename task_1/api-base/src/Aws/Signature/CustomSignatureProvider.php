<?php
namespace Ufz\ApiBase\Aws\Signature;

use Aws\Signature\SignatureInterface;
use Aws\Signature\SignatureProvider as AwsSignatureProvider;

/**
 * Custom signature provider that returns our CustomS3SignatureV4 for S3
 * and delegates to the SDK's default provider for all other services.
 */
class CustomSignatureProvider
{
    private bool $enabled;
    private $default;

    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
        $this->default = AwsSignatureProvider::defaultProvider();
    }

    /**
     * The AWS SDK expects a callable with signature ($version, $service, $region): SignatureInterface
     * We implement __invoke to make the service itself callable.
     */
    public function __invoke($version, $service, $region): SignatureInterface
    {
        if ($service === 's3' && $this->enabled) {
            return new CustomS3SignatureV4('s3', $region);
        }

        return ($this->default)($version, $service, $region);
    }
}
