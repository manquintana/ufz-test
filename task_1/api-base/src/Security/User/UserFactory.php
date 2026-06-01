<?php namespace Ufz\ApiBase\Security\User;

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUser;

class UserFactory extends JWTUser
{
    public static function createFromPayload($username, array $payload)
    {
        if ($payload['handleAsHumanUser'] ?? false) { // sollten ausschließlich IDP-Token sein!
            $user = new HumanUser($payload);
        } elseif ($payload['handleAsMachineUser'] ?? false) { // auch IDP-Token können als MachineToken behandelt werden, indem deren die Client-ID verwendet wird (statt dem Username im sub-Attribut)
            $user = new MachineUser($payload);
        } else {
            throw new \InvalidArgumentException('$payload does not contain handleAsHumanUser or handleAsMachineUser');
        }
    }
}