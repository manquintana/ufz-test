<?php


namespace Ufz\ApiBase\Security\JWT;



class LcobucciSignerFactory
{

    public static function getSignerByJwtAlgorithm($alg)
    {
        $alg = mb_strtoupper($alg);
        switch ($alg) {
            case 'HS256':
                return new \Lcobucci\JWT\Signer\Hmac\Sha256();
            case 'HS384':
                return new \Lcobucci\JWT\Signer\Hmac\Sha384();
            case 'HS512':
                return new \Lcobucci\JWT\Signer\Hmac\Sha512();
            case 'RS256':
                return new \Lcobucci\JWT\Signer\Rsa\Sha256();
            case 'RS384':
                return new \Lcobucci\JWT\Signer\Rsa\Sha384();
            case 'RS512':
                return new \Lcobucci\JWT\Signer\Rsa\Sha512();
        }

        throw new \InvalidArgumentException('Algorithm "'.$alg.'" is not supported');
    }

}
