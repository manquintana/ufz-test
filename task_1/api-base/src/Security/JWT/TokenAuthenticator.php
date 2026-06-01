<?php

namespace Ufz\ApiBase\Security\JWT;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TokenAuthenticator extends JWTAuthenticator
{
    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        TokenExtractorInterface $tokenExtractor,
        UserProviderInterface $userProvider,
        private readonly OidcHttpClient $keyCloakClient
    ) {
        parent::__construct($jwtManager, $dispatcher, $tokenExtractor, $userProvider);
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function doAuthenticate(Request $request): Passport|SelfValidatingPassport
    {
        if (!$request->headers->has('Authorization') && !$request->cookies->has('BEARER')) {
            $content = $this->keyCloakClient->obtainToken();
            $request->headers->add(['Authorization' => 'Bearer ' . $content->access_token]);
        }
        return parent::doAuthenticate($request);
    }
}
