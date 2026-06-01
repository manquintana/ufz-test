<?php


namespace Ufz\ApiBase\Security\Voter;


use Ufz\ApiBase\Security\Interfaces\AuthorizationRequiredEntityInterface;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DeletePermissionForCurrentEntityVoter extends Voter
{

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
        return $attribute == 'DELETE_PERMISSION_FOR_CURRENT_DATASET' && $subject instanceof AuthorizationRequiredEntityInterface;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return $this->permissionProvider->isDatasetDeletable($subject, $token->getUser());
    }

}
