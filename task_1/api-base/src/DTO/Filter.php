<?php

namespace Ufz\ApiBase\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use Ufz\ApiBase\Resolver\FilterApplyResolver;
use Ufz\ApiBase\State\Provider\FilterApplyProvider;

#[ApiResource(
    paginationEnabled: false,
    operations: [
        new Get(security: "is_granted('ROLE_FilterHistory_READ')"),
        new GetCollection(security: "is_granted('ROLE_FilterHistory_READ')"),
    ],
    graphQlOperations: [
        new Query(security: "is_granted('ROLE_FilterHistory_READ')"),
        new QueryCollection(security: "is_granted('ROLE_FilterHistory_READ')"),
        new QueryCollection(name: 'apply', provider: FilterApplyProvider::class, resolver: FilterApplyResolver::class, args: [
                'filters' => ['type' => 'String!'],
                'packed' => ['type' => 'Boolean']
            ]),
    ]
)]
class Filter
{
    private ?int $id = null;

    private ?string $filters = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Filter
    {
        $this->id = $id;
        return $this;
    }

    public function getFilters(): ?string
    {
        return $this->filters;
    }

    public function setFilters(?string $filters): Filter
    {
        $this->filters = $filters;
        return $this;
    }
}
