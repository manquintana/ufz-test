<?php namespace Ufz\ApiBase\Security\ContextBuilder;

use ApiPlatform\GraphQl\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Metadata\GraphQl\Operation;
use Ufz\ApiBase\Security\Interfaces\PermissionProviderInterface;
use Ufz\ApiBase\Security\User\AbstractUser;
use Ufz\ApiBase\Security\Utils\SerialisationGroupsHelper;
use Doctrine\ORM\EntityManagerInterface;
use GraphQL\Error\Error;
use Symfony\Bundle\SecurityBundle\Security;

class EntityContextBuilderGraphQl implements SerializerContextBuilderInterface
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
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    /**
     * @var SerialisationGroupsHelper
     */
    private SerialisationGroupsHelper $serialisationGroupsHelper;

    public function __construct(
        SerializerContextBuilderInterface $decorated,
        Security $security,
        PermissionProviderInterface $permissionProvider,
        EntityManagerInterface $entityManager,
        SerialisationGroupsHelper $serialisationGroupsHelper
    ) {

        $this->decorated = $decorated;
        $this->security = $security;
        $this->permissionProvider = $permissionProvider;
        $this->entityManager = $entityManager;
        $this->serialisationGroupsHelper = $serialisationGroupsHelper;
    }

    public function create(
        ?string $resourceClass,
        Operation $operation,
        array $resolverContext,
        bool $normalization
    ): array {
        $context = $this->decorated->create($resourceClass, $operation, $resolverContext, $normalization);
        /** @var AbstractUser $user */
        $user = $this->security->getUser();

        $operationName = $operation->getName() ?? '';

        // $normalization = true --> read - process
        // create,update,delete startet auch hier und wird dann nochmal mit $normalization=false aufgerufen
        //$normalization = false --> write - process
        //hier gibt es kein $context['attributes'] mehr

        // $operationName = [create,update,delete,item_query,collection_query]

        if (in_array($operationName, ['item_query', "collection_query", 'update', 'customUpdate','create']) && $normalization) {

            $this->serialisationGroupsHelper
                ->setContext($context)
                ->setResourceClass($resourceClass);

            $requestedEntities = $this->serialisationGroupsHelper->getRequestedEntities();

            $allowedFields = [];
            foreach ($requestedEntities as $entityFQCN){
                $allowedFields[$entityFQCN]= $this->permissionProvider->getReadableFieldNamesOfEntity($entityFQCN,$user);
            }

            $this->serialisationGroupsHelper->setAllowedFields($allowedFields);

            $context = $this->serialisationGroupsHelper->addReadGroupsToContext();

            // AP 3.x: When deserialize=false on a mutation, the denormalization phase is
            // skipped entirely, so field-level write authorization must also be validated
            // during the normalization phase (which is always called).
            if (in_array($operationName, ['update', 'customUpdate', 'create'])) {
                $this->validateFieldWritePermissions($operationName, $resourceClass, $resolverContext, $user);
            }
        }

        if ($operationName === 'create' && !$normalization) {

            $affectedAttributes = array_keys($resolverContext['args']['input'] ?? []);
            $createableFieldNames = $this->permissionProvider->getCreatableFieldNamesOfEntity($resourceClass, $user);

            $this->serialisationGroupsHelper
                ->setAffectedAttributes($affectedAttributes)
                ->setContext($context)
                ->setResourceClass($resourceClass)
                ->setAllowedFields($createableFieldNames);
            $context = $this->serialisationGroupsHelper->addWriteGroupsToContext();
        }

        if ($operationName === 'update' && !$normalization) {

            $affectedAttributes = array_keys($resolverContext['args']['input'] ?? []);
            $updateableFields = $this->permissionProvider->getUpdateableFieldNamesOfEntity($resourceClass, $user);

            $this->serialisationGroupsHelper
                ->setAffectedAttributes($affectedAttributes)
                ->setContext($context)
                ->setResourceClass($resourceClass)
                ->setAllowedFields($updateableFields);
            $context = $this->serialisationGroupsHelper->addWriteGroupsToContext();
        }

        if ($operationName === 'customUpdate' && !$normalization) {

            $affectedAttributes = array_keys($resolverContext['args']['input'] ?? []);
            $affectedAttributes = $this->replaceInputTagsAttributeIfAffected($affectedAttributes);

            $updateableFields = $this->permissionProvider->getUpdateableFieldNamesOfEntity($resourceClass, $user);

            $this->serialisationGroupsHelper
                ->setAffectedAttributes($affectedAttributes)
                ->setContext($context)
                ->setResourceClass($resourceClass)
                ->setAllowedFields($updateableFields);
            $context = $this->serialisationGroupsHelper->addWriteGroupsToContext();

        }

        return $context;
    }

    /**
     * Validate that the user has permission to write the affected fields.
     * Called during the normalization phase to ensure enforcement even when
     * deserialize=false is set on the mutation (which skips the denormalization phase).
     */
    private function validateFieldWritePermissions(
        string $operationName,
        string $resourceClass,
        array $resolverContext,
        AbstractUser $user
    ): void {
        $affectedAttributes = array_keys($resolverContext['args']['input'] ?? []);
        if (empty($affectedAttributes)) {
            return;
        }

        if ($operationName === 'customUpdate') {
            $affectedAttributes = $this->replaceInputTagsAttributeIfAffected($affectedAttributes);
        }

        $allowedFields = $operationName === 'create'
            ? $this->permissionProvider->getCreatableFieldNamesOfEntity($resourceClass, $user)
            : $this->permissionProvider->getUpdateableFieldNamesOfEntity($resourceClass, $user);

        // If allowedFields is empty, the user has no write permission at all.
        // Skip field-level validation; the SecurityStage will deny with "Access Denied."
        if (empty($allowedFields)) {
            return;
        }

        $entityName = (new \ReflectionClass($resourceClass))->getShortName();
        foreach ($affectedAttributes as $attribute) {
            if (!in_array($attribute, $allowedFields)) {
                throw Error::createLocatedError(
                    'Access denied to modify property ' . $attribute . ' of ' . $entityName
                );
            }
        }
    }

    /**
     * @param array $affectedAttributes
     * @return array|string[]
     */
    private function replaceInputTagsAttributeIfAffected(array $affectedAttributes)
    {
        $inputTags = 'inputTags';
        $replacement = 'tags';
        if (in_array($inputTags, $affectedAttributes)) {
            $affectedAttributes = array_map(
                function ($element) use ($inputTags, $replacement) {
                    return $element == $inputTags ? $replacement : $element;
                },
                $affectedAttributes
            );
        }

        return $affectedAttributes;
    }


}
