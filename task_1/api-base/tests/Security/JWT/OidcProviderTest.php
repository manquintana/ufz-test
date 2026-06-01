<?php declare(strict_types=1);

namespace Ufz\ApiBase\Tests\Security\JWT;

use Ufz\ApiBase\Security\JWT\OidcProvider;
use Ufz\ApiBase\Tests\BaseUnitTestCase;

class OidcProviderTest extends BaseUnitTestCase
{
    /**
     * @var OidcProvider
     */
    private OidcProvider $oidcProvider;

    /**
     * @var string
     */
    private string $baseUri;

    protected function setUp(): void
    {
        $this->baseUri = 'http://baseUri';

        $this->oidcProvider = $this->getMockBuilder(OidcProvider::class)
            ->setConstructorArgs([$this->baseUri])
            ->onlyMethods([
                'readFromOidcProviderBasedCache',
                'getDataFromJsonBasedUri',
                'writeToOidcProviderBasedCache',
            ])->getMock();
    }

    public function testGetWellKnownUri(): void
    {
        $this->assertEquals(
            $this->baseUri . '/.well-known/openid-configuration',
            $this->oidcProvider->getWellKnownUri()
        );
    }

    public function testGetWellKnownUriStripSlash(): void
    {
        $baseUri = 'http://baseUri/';
        $oidcProvider = new OidcProvider($baseUri);

        $this->assertEquals(
            'http://baseUri/.well-known/openid-configuration',
            $oidcProvider->getWellKnownUri()
        );
    }

    public function testRemoveTrailingSlash(): void
    {
        $baseUri = 'http://baseUri/';
        $oidcProvider = new OidcProvider($baseUri);
        $method = $this->getNonPublicMethod(OidcProvider::class, 'removeTrailingSlash');

        $this->assertEquals(
            'http://baseUri',
            $method->invoke($oidcProvider, $baseUri)
        );
    }

    public function testGetJwksUri(): void
    {
        $this->oidcProvider->expects($this->once())
            ->method('readFromOidcProviderBasedCache')
            ->willReturn(null);

        $this->oidcProvider->expects($this->once())
            ->method('getDataFromJsonBasedUri')
            ->willReturn($this->getResponseMockWellKnownConfig());

        $this->oidcProvider->expects($this->once())
            ->method('writeToOidcProviderBasedCache')
            ->with('jwksUri', 'http://jwks_uri/mock/url');

        $jwksUri = $this->oidcProvider->getJwksUri();

        $this->assertEquals('http://jwks_uri/mock/url', $jwksUri);
    }

    public function testGetJwksUriReadCache(): void
    {
        $this->oidcProvider->expects($this->once())
            ->method('readFromOidcProviderBasedCache')
            ->willReturn('cached response');

        $this->oidcProvider->expects($this->never())->method('getDataFromJsonBasedUri');

        $jwksUri = $this->oidcProvider->getJwksUri();

        $this->assertEquals('cached response', $jwksUri);
    }

    public function testGetJwksUriEmptyResponse(): void
    {
        $this->checkGetJwksUriForException(
            [],
            'JSON from http://baseUri/.well-known/openid-configuration does not contains jwks_uri attribute'
        );
    }

    public function testGetJwksUriInvalidJson(): void
    {
        $this->checkGetJwksUriForException(
            'invalid response format',
            'JSON from http://baseUri/.well-known/openid-configuration is in an unexpected format'
        );
    }

    public function testGetPublicKeys(): void
    {
        $this->oidcProvider->expects($this->exactly(2))
            ->method('readFromOidcProviderBasedCache')
            ->willReturn(null, 'jwkUriMock');

        $this->oidcProvider->expects($this->once())
            ->method('getDataFromJsonBasedUri')
            ->with('jwkUriMock')
            ->willReturn($this->getResponseMockValid());

        $keys = $this->oidcProvider->getPublicKeys();

        $this->assertEquals('eTest', $keys['e']);
        $this->assertEquals('nTest', $keys['n']);
        $this->assertEquals('RS256', $keys['alg']);
    }

    public function testGetPublicKeysEmptyResponse(): void
    {
        $this->checkGetPublicKeysForException(
            [],
            'JSON from jwkUriMock is in an unexpected format'
        );
    }

    public function testGetPublicKeysEmptyKeys(): void
    {
        $this->checkGetPublicKeysForException(
            ['invalidKey' => []],
            'JSON from jwkUriMock is in an unexpected format'
        );
    }

    public function testGetPublicKeysNoKey(): void
    {
        $this->checkGetPublicKeysForException(
            ['invalidKey' => 'someValue'],
            'JSON from jwkUriMock is in an unexpected format'
        );
    }

    public function testGetPublicKeysReadCache(): void
    {
        $this->oidcProvider->expects($this->once())
            ->method('readFromOidcProviderBasedCache')
            ->willReturn(['keys' => 'cached response']);

        $this->oidcProvider->expects($this->never())->method('getDataFromJsonBasedUri');

        $keys = $this->oidcProvider->getPublicKeys();

        $this->assertEquals('cached response', $keys['keys']);
    }

    public function testReadFromOidcProviderBasedCache(): void
    {
        copy(
            dirname(__FILE__) . '/Resources/cache-file-mock.json',
            '/tmp/oidc-provider-' . md5($this->baseUri) . '-' . md5('testId') . '.json'
        );

        $method = $this->getNonPublicMethod(OidcProvider::class, 'readFromOidcProviderBasedCache');
        $result = $method->invoke($this->oidcProvider, 'testId', 3600);

        $this->assertArrayHasKey('keys', $result);

        $record = reset($result['keys']);
        $this->assertEquals('kidKey', $record['kid']);

        unlink('/tmp/oidc-provider-' . md5($this->baseUri) . '-' . md5('testId') . '.json');
    }

    public function testReadFromOidcProviderBasedCacheNoFile(): void
    {
        $method = $this->getNonPublicMethod(OidcProvider::class, 'readFromOidcProviderBasedCache');
        $this->assertNull($method->invoke($this->oidcProvider, 'testId', 3600));
    }

    protected function checkGetJwksUriForException($responseMock, string $exceptionMessage): void
    {
        $this->oidcProvider->expects($this->once())
            ->method('readFromOidcProviderBasedCache')
            ->willReturn(null);

        $this->oidcProvider->expects($this->once())
            ->method('getDataFromJsonBasedUri')
            ->willReturn($responseMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->oidcProvider->getJwksUri();
    }

    protected function checkGetPublicKeysForException($responseMock, string $exceptionMessage): void
    {
        $this->oidcProvider->expects($this->exactly(2))
            ->method('readFromOidcProviderBasedCache')
            ->willReturn(null, 'jwkUriMock');

        $this->oidcProvider->expects($this->once())
            ->method('getDataFromJsonBasedUri')
            ->with('jwkUriMock')
            ->willReturn($responseMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->oidcProvider->getPublicKeys();
    }

    protected function getResponseMockWellKnownConfig(): array
    {
        return ['jwks_uri' => 'http://jwks_uri/mock/url'];
    }

    protected function getResponseMockValid(): array
    {
        return [
            'keys' => [
                "kid" => "kidKey",
                "kty" => "RSA",
                "alg" => "RS256",
                "use" =>  "sig",
                "n" => "nTest",
                "e" => "eTest",
                "x5c" => ["x5cTest"],
                "x5t" => "x5tTest",
                "x5t#S256" => "x5t#S256Test",
            ]
        ];
    }
}
