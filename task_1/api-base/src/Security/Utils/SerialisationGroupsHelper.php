<?php namespace Ufz\ApiBase\Security\Utils;

use Ufz\ApiBase\Security\Interfaces\HasVirtualPropertiesInterface;
use Doctrine\ORM\EntityManagerInterface;
use GraphQL\Error\Error;
use ReflectionClass;

class SerialisationGroupsHelper
{
    const BASE_ENTITY_ANNOTATION = 'BaseEntity';

    private array $context;

    private string $resourceClass;

    private array $affectedAttributes;

    private array $allowedFields;
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;


    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array $context
     * @return SerialisationGroupsHelper
     */
    public function setContext(array $context): SerialisationGroupsHelper
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @param string $resourceClass
     * @return SerialisationGroupsHelper
     */
    public function setResourceClass(string $resourceClass): SerialisationGroupsHelper
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @param array $affectedAttributes
     * @return SerialisationGroupsHelper
     */
    public function setAffectedAttributes(array $affectedAttributes): SerialisationGroupsHelper
    {
        $this->affectedAttributes = $affectedAttributes;

        return $this;
    }

    /**
     * @param array $allowedFields
     * @return SerialisationGroupsHelper
     */
    public function setAllowedFields(array $allowedFields): SerialisationGroupsHelper
    {
        $this->allowedFields = $allowedFields;

        return $this;
    }

    private function getWriteSerialisationGroups(): array
    {
        $groups = [];

        $entityName = (new \ReflectionClass($this->resourceClass))->getShortName();

        foreach ($this->affectedAttributes as $affectedAttribute) {
            if (in_array($affectedAttribute, $this->allowedFields)) {
                $groups[] = $entityName . '.' . $affectedAttribute . ':WRITE';
            } else {
                throw Error::createLocatedError(
                    'Access denied to modify property ' . $affectedAttribute . ' of ' . $entityName
                );
            }
        }

        return $groups;
    }

    public function addWriteGroupsToContext(): array
    {
        $writeGroups = $this->getWriteSerialisationGroups();
        $existingGroups = $this->context['groups'] ?? [];
        $this->context['groups'] = array_merge($existingGroups, $writeGroups);
        return $this->context;
    }

    private function getReadSerialisationGroups()
    {
        $groups = [];

        $requestedEntitiesAndAttributes = $this->getRequestedEntitiesAndAttributesOfGraphQlQuery(
            ($this->context['attributes'] ?? []),
            $this->resourceClass
        );

        foreach ($requestedEntitiesAndAttributes as $requestedEntity => $requestedEntityAttributes) {

            $readableFields = $this->allowedFields[$requestedEntity] ?? [];

            foreach ($requestedEntityAttributes as $requestedAttribute) {
                if (in_array($requestedAttribute, $readableFields)) {
                    $groups[] = $this->getReadGroupForEntityFqcnAndAttribute(
                        $requestedEntity,
                        $requestedAttribute
                    );
                } else {
                    throw Error::createLocatedError(
                        'Access Denied to request property ' . $requestedAttribute . ' of ' . $requestedEntity
                    );
                }
            }
        }

        return $groups;
    }

    private function getReadGroupForEntityFqcnAndAttribute($entityFqcn, $attribute)
    {
        $entityName = in_array($attribute, ['createdAt', 'modifiedAt', 'createdBy', 'lastModifiedBy'])
            ? self::BASE_ENTITY_ANNOTATION
            : EntityUtils::getEntityNameFromFQCN($entityFqcn);

        return sprintf('%s.%s:READ', $entityName, $attribute);
    }

    private function getRequestedEntitiesAndAttributesOfGraphQlQuery(array $contextAttributes, string $resourceClass)
    {
        $requestedEntitiesAndAttributes = [];

        // Unwrap AP 3.x collection/edges wrappers in context attributes
        if (isset($contextAttributes['edges']) && isset($contextAttributes['edges']['node'])) {
            $contextAttributes = $contextAttributes['edges']['node'];
        } elseif (isset($contextAttributes['collection'])) {
            $contextAttributes = $contextAttributes['collection'];
        }

        // Guard: skip non-Doctrine resources (e.g., ApiResource DTOs)
        try {
            if (method_exists($this->entityManager, 'getMetadataFactory')
                && $this->entityManager->getMetadataFactory()->isTransient($resourceClass)) {
                return $requestedEntitiesAndAttributes; // not a Doctrine entity
            }
            $resourceClassMetadata = $this->entityManager->getClassMetadata($resourceClass);
        } catch (\Throwable $e) {
            // If metadata cannot be loaded, treat as non-entity and skip
            return $requestedEntitiesAndAttributes;
        }
        $referencingFieldNames = $resourceClassMetadata->getAssociationNames();
        foreach ($contextAttributes as $propertyName => $propertyContextValue) {
            if ($propertyContextValue !== true && !is_array($propertyContextValue)) {
                continue;
            }

            $isVirtualProperty = false;
            if ((new ReflectionClass($resourceClass))->implementsInterface(HasVirtualPropertiesInterface::class)) {
                $isVirtualProperty = in_array($propertyName, (new $resourceClass)->getVirtualProperties());
            }

            if (!$isVirtualProperty && !$resourceClassMetadata->hasField($propertyName) && !$resourceClassMetadata->hasAssociation(
                    $propertyName
                )) {
                continue;
            }
            $requestedEntitiesAndAttributes[$resourceClass][] = $propertyName;

            /*
             * handle references
             */
            if (is_array($propertyContextValue) && in_array($propertyName, $referencingFieldNames)) {
                $targetResourceClass = $resourceClassMetadata->getAssociationTargetClass($propertyName);
                $referencedEntitiesAndAttributes = $this->getRequestedEntitiesAndAttributesOfGraphQlQuery(
                    $propertyContextValue,
                    $targetResourceClass
                );
                foreach ($referencedEntitiesAndAttributes as $referencedEntity => $referencedEntityAttributes) {
                    $requestedEntitiesAndAttributes[$referencedEntity] = array_merge(
                        $requestedEntitiesAndAttributes[$referencedEntity] ?? [],
                        $referencedEntityAttributes
                    );
                }
            }
        }

        return $requestedEntitiesAndAttributes;
    }

    public function getRequestedEntities(): array
    {
        $result = [];
        $workList = [$this->resourceClass => $this->context['attributes']];

        do {
            end($workList);
            $entityFQCN = key($workList);
            $item = array_pop($workList);

            if (isset($item['edges']) && isset($item['edges']['node'])) {
                $workList[$entityFQCN] = $item['edges']['node'];
            } else if (isset($item['collection'])) {
                $workList[$entityFQCN] = $item['collection'];
            } else {
                // Guard: skip non-Doctrine resources (e.g., ApiResource DTOs)
                try {
                    if (method_exists($this->entityManager, 'getMetadataFactory')
                        && $this->entityManager->getMetadataFactory()->isTransient($entityFQCN)) {
                        // Not a Doctrine entity: do not attempt to resolve associations
                        $result[] = $entityFQCN;
                        continue;
                    }
                    $resourceClassMetadata = $this->entityManager->getClassMetadata($entityFQCN);
                } catch (\Throwable $e) {
                    // Not an entity or cannot load metadata; add and continue without associations
                    $result[] = $entityFQCN;
                    continue;
                }
                $referencingFieldNames = $resourceClassMetadata->getAssociationNames();

                $result[] = $entityFQCN;

                foreach ($item as $propertyName => $propertyContextValue) {
                    if (is_array($propertyContextValue) && in_array($propertyName, $referencingFieldNames)) {
                        $targetResourceClass = $resourceClassMetadata->getAssociationTargetClass($propertyName);
                        $workList[$targetResourceClass] = array_merge($workList[$targetResourceClass] ?? [], $propertyContextValue);
                    }
                }
            }
        } while (count($workList) > 0);

        return array_unique($result);
    }

    public function addReadGroupsToContext(): array
    {
        $readGroups = $this->getReadSerialisationGroups();
        $existingGroups = $this->context['groups'] ?? [];
        $this->context['groups'] = array_merge($existingGroups, $readGroups);

        return $this->context;
    }

}
