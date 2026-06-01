<?php declare(strict_types=1);

namespace Ufz\ApiBase\Tests\Security\JWT;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use PHPUnit\Framework\MockObject\MockObject;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Ufz\ApiBase\Security\JWT\JWTEncoder;
use Ufz\ApiBase\Security\JWT\OidcProvider;
use Lcobucci\JWT\Token;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Ufz\ApiBase\Tests\BaseUnitTestCase;

class JWTEncoderTest extends BaseUnitTestCase
{
    public const MOCK_PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1uyfzKEaYUJ1SOBOWfQD
Xa02XpWE8OF1ML2hqLeWz/59scq6hRve+CHKZwLSjGqzfE1qe/YwGsA3RBHTbACZ
f+PoXM3x9duTVu/WNct/cTE7/LMYPrABecSrxbi8OE2N4nx3ZlcFYfJM9aEpexP2
3kMmepzt4q9W1DamSMUMqwn9syj01nhiF1WpeAr3SN7KVZLalnnMNvXiUP1D5giH
qQQkfuH4rWEUtIVrKsePVTKJGC7CnTbJSva4SsRnIPvJ72TWXp+TiEHjgnL113H3
+E6gjavMCKYByBjBEh1r/8nd1511f90Jnu9MXv3HmWW0HiMDfuRpFVGlfwCNmsqk
IQIDAQAB
-----END PUBLIC KEY-----';

    public const MOCK_PRIVATE_KEY = '-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA1uyfzKEaYUJ1SOBOWfQDXa02XpWE8OF1ML2hqLeWz/59scq6
hRve+CHKZwLSjGqzfE1qe/YwGsA3RBHTbACZf+PoXM3x9duTVu/WNct/cTE7/LMY
PrABecSrxbi8OE2N4nx3ZlcFYfJM9aEpexP23kMmepzt4q9W1DamSMUMqwn9syj0
1nhiF1WpeAr3SN7KVZLalnnMNvXiUP1D5giHqQQkfuH4rWEUtIVrKsePVTKJGC7C
nTbJSva4SsRnIPvJ72TWXp+TiEHjgnL113H3+E6gjavMCKYByBjBEh1r/8nd1511
f90Jnu9MXv3HmWW0HiMDfuRpFVGlfwCNmsqkIQIDAQABAoIBAC/0hkdjXv56lK7Z
FcJudt3NC0eZdxtEQyDH/y0lIapxL1yfTnTq3hphd8b6Uz5vhHLk1zCnot4lK2+t
xo3fqGBn2u3yKd3gy4RnaVWBfYMlKCxfTbaXEQ05e9ZXPPAXJeR2PzH/krzjEpbw
CdBjiP7Y3toW7+FXnDknpHyyMvxqipdrUNe6n6Ff1DBZ0FDn3rTn+Bf46wJFdl6n
6vhWOdiDfGX2rT/Bv24jfvuGvjYfD4pgw0iAZiL0XmJ/7bUlhsDAve6UtTdMkBOS
cQqiDL9aYpEdBcNrfSAuSlKYPXfCiB2DMu5OVdpLbykf9PY1EqXuuGjE45E1nfF/
xYDM4UECgYEA7l2BtocZkSMaQIHs9k/rEWmr2snJM8wR1QRPdcN7ySglU30Dkoww
1YogQikH7wXvtWmQZtmdIOciotGdRjEC0VPKCw8ScW2opwDJ0lxvZh7/8+zWUzxe
lUuK/LtxSDHl/bWjvDfTtp/zjICPJQ7Whfz3HVBy+U7i5Cma0q55ZskCgYEA5tMp
BAeRPyQa0fsSUK9ruEcVLoscEbNOwulgRlTO2JxDM4JrPII6XMD9IilNCDtH+tG/
yNxpqSvrur0OQ/wDVjqFgvexD3oIAxCTeNv12myom4P4+kCE096DiMPwbqmrOeN9
GoEsbBtowWrMbyOILhgvIcP61y9vNSxU7Hl3hpkCgYEA2A8R4G0dE52J0ibyr2FJ
ZYMvLyXwpm63SyqZC9mhfnhRPRf4AQHp0eVd0Bp5AoOTABErvs5JyuU3U/ZEZLdQ
IoWcgeGrif0n/hiM14zJvPskbemja9cwtIrA9MzCpfn0yr+2JolD8imSDS0Kk0Cb
2t+s7nlZffmvV7kOiSF1EWECgYBIFRUv7vUK1MmTXWagz8dB6uDQghyn4mjsMVkh
XYai2lmaElZOtRRottPWATPPKEQYLbxIi5xreg3JaRS1YlPgb7IV7ifa/27VFi4X
hglGxrv4pMPx0ogoacqFwTqqNE4Ga+Y7iq9Gq2CRVjq1UlFKo77EOqFw5Z1C668x
kdUjsQKBgFXwC3YvQDQn59Wo1qGHHiKrzJutbxrzFc0yftJk2qCIjEOgRrurViW9
eauIvHQ9LUfVPvOXMyy+ntICVAHa+5nsSEfh/+6dne3boW8hd2eNBspyv8m7y0OU
bfi3uZNIO8Kwu/UOcRAgpdoKyZiojvy4rGBkRcGUIEIgSumVZN27
-----END RSA PRIVATE KEY-----';

    /**
     * @var JWTEncoder
     */
    private JWTEncoder $jwtEncoder;

    /**
     * @var OidcProvider|MockObject
     */
    private $oidcProvider;

    /**
     * @var PermissionProviderInterface|MockObject
     */
    private $permissionProvider;

    /**
     * @var string
     */
    private string $tokenString;

    protected function setUp(): void
    {
        $this->tokenString = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjMiLCJpc3MiOiJodHRwOi8va2V5Y2xvYWs6OTA5MC9tb2NrL3VybCIsImlhdCI6MTY2ODE3NTUxNS4xNDMzNjQsImV4cCI6MTY2ODE3OTExNS4xNDMzOTksImF1ZCI6ImFjY291bnQifQ.LR6tZZMdr9HXxkvVwMT3oHou94LFOWBH1CLX3WV38qSbK6xooIjh1IoOJAtvELTyapxXWC1F4F9UWUYNgC7yDM5cRxo6Jkvivb92Z_KTnGCeflrZ8lI30EbXlAcPuCstCIM9n44oWZeMmPctfMBHT0Q_5C-HcUkUtwBeO8WC6zDAPUUtBlGep7U8-HlvYlOTA-awYfeng_X0EFJJLOKDdc8ebrRh289HMNwhYc4rXlrj0fK4Egf5DZC_kW3iEB_nQKxAgYRJSSi9cau-1dpajrmtQCtxHWioh08Q-iNSSf6w18WuxJ02tBfK2bfYZGyNxNNZlGMnqDlSeU2X4KzoMfGXlOOMzaOTX8sbGlA4wcLAPH883R03ASgx0ksexsPWFnuZm1kpr5gbEQWHmCkrKQadiKFAnTQhPJoGLBrHpf8X3nE5QiFbCbc8mW3Sb4irlKKDAnim3qhBEqwfDEUl_BRMRfBLrH7rr7xLDBZYhbwP5YJ4aixZeoJ_28AuK4vPNAIBWFhVRSngFTAnfp_27Z2Jya60K1Z_0Vx4GGrKi0R6Vv6rkT2ddt2wZl_esoPxUuGbEfI_TTEvHQFrh_ep1bTgl79cqBNZagrpZbLuH5QBahWfRKueyPvq2B-RMDfAgJVy63biFtLj5IM23vW7sVxkbZzRtmfPDoS3g1KSmQ8';
        $this->oidcProvider = $this->createMock(OidcProvider::class);
        $this->permissionProvider = $this->createMock(PermissionProviderInterface::class);

        $this->jwtEncoder = $this->getMockBuilder(JWTEncoder::class)
            ->setConstructorArgs([$this->oidcProvider, $this->permissionProvider, '123'])
            ->onlyMethods(['parseToken'])
            ->getMock();
    }

    public function testEncode(): void
    {
        $this->expectException(JWTEncodeFailureException::class);
        $this->jwtEncoder->encode([]);
    }

    public function testDecodeHappyPath(): void
    {
        $token = $this->createMockToken();
        $this->declareTokenExpectations($token);

        $this->oidcProvider->expects($this->once())
            ->method('getPublicKeys')
            ->willReturn([$this->getPublicKeys()]);

        $this->oidcProvider->expects($this->once())->method('getBaseUri')->willReturn('http://keycloak:9090/mock/url');

        $payload = $this->jwtEncoder->decode($this->tokenString);

        $this->assertEquals('123', $payload['sub']);
        $this->assertEquals('account', $payload['aud']);
        $this->assertEquals('http://keycloak:9090/mock/url', $payload['iss']);
    }

    public function testDecodeExpiredToken(): void
    {
        $token = $this->createMockToken(true);
        $this->declareTokenExpectations($token);

        $this->expectException(JWTDecodeFailureException::class);
        $this->jwtEncoder->decode($this->tokenString);

        $this->expectExceptionMessage('Expired JWT Token');
    }

    public function testDecodeMissingClaim(): void
    {
        $token = $this->createMockToken(false, true);
        $this->declareTokenExpectations($token);

        $this->expectException(JWTDecodeFailureException::class);
        $this->jwtEncoder->decode($this->tokenString);

        $this->expectExceptionMessage('Invalid JWT Token (claim sub is missing)');
    }

    public function testDecodeMissingAlgorithm(): void
    {
        $token = $this->createMockToken(false, false, 'Non-existing alg');
        $this->declareTokenExpectations($token);

        $this->expectException(JWTDecodeFailureException::class);
        $this->jwtEncoder->decode($this->tokenString);

        $this->expectExceptionMessage('Invalid JWT Token');
    }

    public function testParseToken(): void
    {
        $tokenString = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $method = $this->getNonPublicMethod(JWTEncoder::class, 'parseToken');
        $jwtEncoder = new JWTEncoder(
            $this->oidcProvider,
            $this->permissionProvider,
            '123'
        );

        $this->assertInstanceOf(Token::class, $method->invoke($jwtEncoder, $tokenString));
    }

    public function testParseTokenInvalid(): void
    {
        $tokenString = 'invalid_token_string';
        $method = $this->getNonPublicMethod(JWTEncoder::class, 'parseToken');
        $jwtEncoder = new JWTEncoder(
            $this->oidcProvider,
            $this->permissionProvider,
            '123'
        );

        $this->expectException(JWTDecodeFailureException::class);
        $this->expectExceptionMessage('Invalid JWT Token');
        $method->invoke($jwtEncoder, $tokenString);
    }

    /**
     * Here we use an invalid key to run the scenario of an invalidly signed token instead of generating an invalid token.
     */
    public function testExceptionOnTokenSignatureValidation(): void
    {
        $token = $this->createMockToken();
        $this->declareTokenExpectations($token);

        $this->oidcProvider->expects($this->once())
            ->method('getPublicKeys')
            ->willReturn([$this->getInvalidPublicKeys()]);

        $this->oidcProvider->expects($this->once())->method('getBaseUri')->willReturn('http://keycloak:9090/mock/url');

        $this->expectException(JWTDecodeFailureException::class);
        $this->expectExceptionMessage('Invalid JWT Token');

        $this->jwtEncoder->decode($this->tokenString);
    }

    public function testExceptionOnIssuerValidation(): void
    {
        $token = $this->createMockToken();
        $this->declareTokenExpectations($token);

        $this->oidcProvider->expects($this->once())->method('getBaseUri')->willReturn('http://invalid:9090/mock/url');

        $this->expectException(JWTDecodeFailureException::class);
        $this->expectExceptionMessage('Invalid JWT Token (invalid issuer)');

        $this->jwtEncoder->decode($this->tokenString);
    }

    private function declareTokenExpectations($token): void
    {
        $this->jwtEncoder->expects($this->once())
            ->method('parseToken')
            ->with($this->tokenString)
            ->willReturn($token);
    }

    private function createMockToken(bool $isExpired = false): Token\Plain
    {
        $userId = '123';

        if ($isExpired) {
            $iat = new \DateTimeImmutable('- 1 day');
            $exp = new \DateTimeImmutable('- 1 hour');
        } else {
            $iat = new \DateTimeImmutable();
            $exp = new \DateTimeImmutable('+ 1 hour');
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(self::MOCK_PRIVATE_KEY),
            InMemory::plainText(self::MOCK_PUBLIC_KEY)
        );

        return $configuration->builder()
            ->withHeader('typ','JWT')
            ->withHeader('alg','RS256')
            ->relatedTo($userId)
            ->issuedBy('http://keycloak:9090/mock/url')
            ->issuedAt($iat)
            ->expiresAt($exp)
            ->permittedFor('account')
            ->getToken(
                $configuration->signer(),
                $configuration->signingKey()
            );
    }

    public function getPublicKeys(): array
    {
        return
            [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'e' => 'AQAB',
            'use' => 'sig',
            'n' => "1uyfzKEaYUJ1SOBOWfQDXa02XpWE8OF1ML2hqLeWz_59scq6hRve-CHKZwLSjGqzfE1qe_YwGsA3RBHTbACZf-PoXM3x9duTVu_WNct_cTE7_LMYPrABecSrxbi8OE2N4nx3ZlcFYfJM9aEpexP23kMmepzt4q9W1DamSMUMqwn9syj01nhiF1WpeAr3SN7KVZLalnnMNvXiUP1D5giHqQQkfuH4rWEUtIVrKsePVTKJGC7CnTbJSva4SsRnIPvJ72TWXp-TiEHjgnL113H3-E6gjavMCKYByBjBEh1r_8nd1511f90Jnu9MXv3HmWW0HiMDfuRpFVGlfwCNmsqkIQ"
        ];
    }

    public function getInvalidPublicKeys(): array
    {
        return
            [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'e' => 'XXXX',    // <-- Invalid key related to token used in this test class.
                'use' => 'sig',
                'n' => "1uyfzKEaYUJ1SOBOWfQDXa02XpWE8OF1ML2hqLeWz_59scq6hRve-CHKZwLSjGqzfE1qe_YwGsA3RBHTbACZf-PoXM3x9duTVu_WNct_cTE7_LMYPrABecSrxbi8OE2N4nx3ZlcFYfJM9aEpexP23kMmepzt4q9W1DamSMUMqwn9syj01nhiF1WpeAr3SN7KVZLalnnMNvXiUP1D5giHqQQkfuH4rWEUtIVrKsePVTKJGC7CnTbJSva4SsRnIPvJ72TWXp-TiEHjgnL113H3-E6gjavMCKYByBjBEh1r_8nd1511f90Jnu9MXv3HmWW0HiMDfuRpFVGlfwCNmsqkIQ"
            ];
    }
}
