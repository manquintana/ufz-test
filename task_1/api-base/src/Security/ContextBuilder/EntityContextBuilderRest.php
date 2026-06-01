<?php namespace Ufz\ApiBase\Security\ContextBuilder;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Ufz\ApiBase\Security\Utils\EntityUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;

class EntityContextBuilderRest implements SerializerContextBuilderInterface
{
    /**
     * @var SerializerContextBuilderInterface
     */
    private SerializerContextBuilderInterface $decorated;
    /**
     * @var Security
     */
    private Security $security;
    /**
     * @var PermissionProviderInterface
     */
    private PermissionProviderInterface $permissionProvider;

    public function __construct(
        SerializerContextBuilderInterface $decorated,
        Security $security,
        PermissionProviderInterface $permissionProvider
    ) {
        $this->decorated = $decorated;
        $this->security = $security;
        $this->permissionProvider = $permissionProvider;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        $resourceClass = $context['resource_class'] ?? null;
        $user = $this->security->getUser();


        if ($normalization === true) {
            $readableFieldNames = $this->permissionProvider->getReadableFieldNamesOfEntity($resourceClass, $user);
            $entityName = EntityUtils::getEntityNameFromFQCN($resourceClass);
            foreach ($readableFieldNames as $readableField) {
                $context['groups'][] = $entityName.'.'.$readableField.':READ';
            }

            if (isset($context['output']) && isset($context['output']['class'])) {
                $dto = $context['output']['class'];
                $readableFieldNames = $this->permissionProvider->getReadableFieldNamesOfEntity($dto, $user);
                $entityName = EntityUtils::getEntityNameFromFQCN($dto);
                foreach ($readableFieldNames as $readableField) {
                    $context['groups'][] = $entityName.'.'.$readableField.':READ';
                }
            }
        }

//        $createableFieldNames = $this->permissionProvider->getCreatableFieldNamesOfEntity($resourceClass, $user);
//        $updateableFieldNames = $this->permissionProvider->getUpdateableFieldNamesOfEntity($resourceClass, $user);

        {
            return $context;
        }
    }

}
