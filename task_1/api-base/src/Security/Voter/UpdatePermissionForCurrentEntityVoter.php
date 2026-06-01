<?php

namespace Ufz\ApiBase\Security\Voter;

use Ufz\ApiBase\Security\Interfaces\AuthorizationRequiredEntityInterface;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UpdatePermissionForCurrentEntityVoter extends Voter
{

    const string UPDATE = 'UPDATE_PERMISSION_FOR_CURRENT_DATASET';

    /**
     * @var PermissionProviderInterface
     */
    private PermissionProviderInterface $permissionProvider;

    public function __construct(
        PermissionProviderInterface $permissionProvider
    ) {

        $this->permissionProvider = $permissionProvider;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === self::UPDATE && $subject instanceof AuthorizationRequiredEntityInterface;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->permissionProvider->isDatasetUpdateable($subject, $token->getUser());
    }

}
