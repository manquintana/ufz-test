<?php declare(strict_types=1);

namespace Ufz\ApiBase\Security\JWT;

use ApiPlatform\Exception\InvalidArgumentException;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcHttpClient
{
    public const URI_WELL_KNOWN_OPENID_CONFIGURATION = '/.well-known/openid-configuration';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientSecret,
        private readonly string $clientId,
        private readonly string $oidcProviderBaseUri
    ) {}

    public function getTokenUrl(): string
    {
        $response = $this->doRequest(
            'GET',
            $this->oidcProviderBaseUri . self::URI_WELL_KNOWN_OPENID_CONFIGURATION
        );
        if (!property_exists($response, 'token_endpoint') || empty($response->token_endpoint)) {
            throw new InvalidArgumentException('Token endpoint not found.');
        }
        return $response->token_endpoint;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function obtainToken(): stdClass
    {
        $response = $this->doRequest(
            'POST',
            $this->getTokenUrl(),
            [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ]
            ]
        );
        if (!property_exists($response, 'access_token') || empty($response->access_token)) {
            throw new InvalidArgumentException('No access token found in response.');
        }
        return $response;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function doRequest(string $method, string $url, array $options = []): stdClass
    {
        $response = $this->httpClient->request($method, $url, $options);
        try {
            $content = json_decode($response->getContent());
        } catch (HttpException $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }
        return $content;
    }
}
