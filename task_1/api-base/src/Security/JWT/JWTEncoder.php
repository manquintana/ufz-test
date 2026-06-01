<?php declare(strict_types=1);

namespace Ufz\ApiBase\Security\JWT;

use CoderCat\JWKToPEM\Exception\Base64DecodeException;
use CoderCat\JWKToPEM\Exception\JWKConverterException;
use CoderCat\JWKToPEM\JWKConverter;
use Exception;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;

class JWTEncoder implements JWTEncoderInterface
{
    private array $requiredClaims = ['sub', 'aud', 'exp', 'iss', 'iat'];

    public function __construct(
        private OidcProvider $oidcProvider,
        private PermissionProviderInterface $permissionProvider,
        private string $clientIdsString
    ) {}

    public function encode(array $data): string
    {
        throw new JWTEncodeFailureException('encode_token', 'The service should never encode a token.');
    }

    /**
     * @throws JWTDecodeFailureException
     */
    protected function parseToken(string $tokenString): ?Token
    {
        try {
            $token = (new Parser(new JoseEncoder()))->parse((string)$tokenString);
        } catch (Exception $e) {
            throw new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token', $e);
        }

        return $token;
    }

    /**
     * @throws JWKConverterException
     * @throws JWTDecodeFailureException
     * @throws Base64DecodeException
     */
    public function decode($token): array
    {
        $parsedToken = $this->parseToken($token);

        $this->validateRequiredClaims($parsedToken);
        $this->validateTokenExpiration($parsedToken);
        $this->validateIssuer($parsedToken);
        $this->validatePublicKeys($parsedToken);

        return $this->extractPayload($parsedToken);
    }

    /**
     * @throws JWTDecodeFailureException
     */
    private function validateIssuer(Token $token): void
    {
        $iss = $token->claims()->get('iss', '');

        if ($iss !== $this->oidcProvider->getBaseUri()) {
            throw new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token (invalid issuer)');
        }
    }

    /**
     * @throws JWTDecodeFailureException
     */
    private function validateRequiredClaims(?Token $token): void
    {
        foreach ($this->requiredClaims as $requiredClaim) {
            if ($token->claims()->has($requiredClaim) === false) {
                throw new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token (claim ' . $requiredClaim . ' is missing)');
            }
        }
    }

    /**
     * @throws JWTDecodeFailureException
     */
    private function validateTokenExpiration(?Token $token): void
    {
        if ($token->isExpired(new DateTimeImmutable())) {
            throw new JWTDecodeFailureException(JWTDecodeFailureException::EXPIRED_TOKEN, 'Expired JWT Token');
        }
    }

    /**
     * @throws JWTDecodeFailureException
     */
    private function getJwtSigner(?Token $token): Signer\Rsa\Sha384|Signer\Rsa\Sha512|Signer\Hmac\Sha256|Signer\Hmac\Sha384|Signer\Hmac\Sha512|Signer\Rsa\Sha256
    {
        try {
            $jwtAlgorithm = $token->headers()->get('alg');
            $jwtSigner = LcobucciSignerFactory::getSignerByJwtAlgorithm($jwtAlgorithm);
        } catch (Exception $e) {
            throw new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token');
        }
        return $jwtSigner;
    }

    /**
     * @throws JWTDecodeFailureException
     * @throws Base64DecodeException
     * @throws JWKConverterException
     * @throws Exception
     */
    protected function validatePublicKeys(?Token $token): void
    {
        $isValid = false;
        $jwtSigner = $this->getJwtSigner($token);
        $publicKeys = $this->oidcProvider->getPublicKeys();

        foreach ($publicKeys as $publicKey) {
            $keyAsPEM = (new JWKConverter())->toPEM($publicKey);
            $inMemoryKey = InMemory::plainText($keyAsPEM);

            try {
                $signedWith = new SignedWith($jwtSigner, $inMemoryKey);
                $validator = new Validator();

                if ($validator->validate($token, $signedWith)) {
                    $isValid = true;
                    break;
                }
            } catch (Exception $e) {
            }
        }

        if (!$isValid) {
            throw new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'Invalid JWT Token');
        }
    }

    protected function extractPayload(?Token $token): array
    {
        $payload = [];
        foreach ($token->claims()->all() as $key => $claim) {
            $payload[$key] = ($key === 'aud') ? $claim[0] : $claim;
        }
        $payload['roles'] = $this->permissionProvider->getDefaultRoles($payload);

        return $payload;
    }
}
