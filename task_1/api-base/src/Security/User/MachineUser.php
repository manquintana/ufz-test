<?php declare(strict_types=1);

namespace Ufz\ApiBase\Security\User;

class MachineUser extends AbstractUser
{
    public function __construct(array $tokenPayload)
    {
        $this->checkTokenPayload($tokenPayload);

        $this
            ->setSub($tokenPayload['sub'])
            ->setAud($tokenPayload['aud'])
            ->setIss($tokenPayload['iss'])
            ->setUsername($tokenPayload['aud'])
            ->setClientID($tokenPayload['aud'])
            ->setRoles($tokenPayload['defaultRoles'] ?? []);
    }

    private function checkTokenPayload(array $tokenPayload)
    {
        $mandatoryAttributes = ['sub', 'aud', 'iss'];
        foreach ($mandatoryAttributes as $attribute) {
            if (!isset($tokenPayload[$attribute]) || !is_string($tokenPayload[$attribute]) || empty(
                trim(
                    $tokenPayload[$attribute]
                )
                )) {
                throw new \InvalidArgumentException('Invalid token payload (attribute '.$attribute.').');
            }
        }
    }
}
