<?php


namespace Ufz\ApiBase\Security\JWT;


class OidcProvider
{

    const CACHE_OIDC_PROVIDER_RELATED_DATA_FOR_X_SECONDS = 3600;

    /** @var string */
    private $baseUri;





    public function __construct($baseUri)
    {
        $this->baseUri = $this->removeTrailingSlash($baseUri);
    }





    public function getWellKnownUri()
    {
        return $this->baseUri.'/.well-known/openid-configuration';
    }





    public function getJwksUri()
    {

        $jwksUri = $this->readFromOidcProviderBasedCache(
            'jwksUri',
            self::CACHE_OIDC_PROVIDER_RELATED_DATA_FOR_X_SECONDS
        );
        if ($jwksUri != null) {
            return $jwksUri;
        }

        $wellKnownUri = $this->getWellKnownUri();

        $data = $this->getDataFromJsonBasedUri($wellKnownUri);
        if (!is_array($data)) {
            throw new \Exception('JSON from '.$wellKnownUri.' is in an unexpected format');
        }
        if (!isset($data['jwks_uri'])) {
            throw new \Exception('JSON from '.$wellKnownUri.' does not contains jwks_uri attribute');
        }

        $jwksUri = $data['jwks_uri'];
        $this->writeToOidcProviderBasedCache('jwksUri', $jwksUri);

        return $jwksUri;
    }





    /**
     * Get public keys in JsonWebKey format (array)
     *
     * @throws \Exception
     */
    public function getPublicKeys()
    {

        $keys = $this->readFromOidcProviderBasedCache(
            'public-keys',
            self::CACHE_OIDC_PROVIDER_RELATED_DATA_FOR_X_SECONDS
        );
        if (is_array($keys)) {
            return $keys;
        }

        $jwksUri = $this->getJwksUri();
        $data = $this->getDataFromJsonBasedUri($jwksUri);
        if (!is_array($data) || !isset($data['keys']) || !is_array($data['keys'])) {
            throw new \Exception('JSON from '.$jwksUri.' is in an unexpected format');
        }
        if (count($data['keys']) == 0) {
            throw new \Exception('JSON from '.$jwksUri.' does not provide any public keys');
        }
        $keys = $data['keys'];
        $this->writeToOidcProviderBasedCache('public-keys', $keys);


        return $keys;
    }





    /**
     * @return mixed
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }





    /**
     * @param $str
     *
     * @return string
     */
    private function removeTrailingSlash($str): string
    {
        if (mb_substr($str, -1) == '/') {
            $str = mb_substr($str, 0, -1);
        }

        return $str;
    }





    /**
     * @param string $uri
     *
     * @return mixed
     * @throws \Exception
     */
    public function getDataFromJsonBasedUri(string $uri)
    {
        $json = file_get_contents($uri);
        if ($json === false) {
            throw new \Exception('Error while fetching data from '.$uri);
        }

        $data = json_decode($json, true);
        if ($data === null) {
            throw new \Exception('Error while parsing JSON from '.$uri);
        }

        return $data;
    }





    /**
     * Reads data from file based cache.
     *
     * The cache is related to the current oidc provider and the provided $id.
     * If $ttlInSeconds is expired or cache does not exists, null will be returned.
     *
     * @param string $id
     * @param int    $ttlInSeconds (0 for no expiration)
     *
     * @return mixed|null
     */
    protected function readFromOidcProviderBasedCache(string $id, $ttlInSeconds = 0)
    {
        $cacheFilePath = '/tmp/oidc-provider-'.md5($this->baseUri).'-'.md5($id).'.json';
        if (!file_exists($cacheFilePath)) {
            return null;
        }

        if ($ttlInSeconds > 0) {
            clearstatcache(true, $cacheFilePath);
            $lastModified = filemtime($cacheFilePath);
            if ($lastModified === false || (time() - $lastModified) > $ttlInSeconds) {
                return null;
            }
        }

        $json = file_get_contents($cacheFilePath);

        return json_decode($json, true);
    }





    /**
     * Writes data tofile based cache.
     *
     * The cache is related to the current oidc provider and the provided $id.
     *
     * @param string $id
     * @param        $data
     */
    protected function writeToOidcProviderBasedCache(string $id, $data)
    {
        $cacheFilePath = '/tmp/oidc-provider-'.md5($this->baseUri).'-'.md5($id).'.json';
        file_put_contents($cacheFilePath, json_encode($data, JSON_PRETTY_PRINT));
    }


}
