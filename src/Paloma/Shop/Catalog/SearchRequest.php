<?php

namespace Paloma\Shop\Catalog;

class SearchRequest implements SearchRequestInterface
{
    private $category;

    private $query;

    private $filters;

    private $includeFilterAggregates;

    private $page;

    private $size;

    private $sort;

    private $orderDesc;

    /**
     * @param string $category
     * @param string $query
     * @param SearchFilterInterface[] $filters
     * @param bool $includeFilterAggregates
     * @param $page
     * @param int $size
     * @param string $sort
     * @param bool $orderDesc
     */
    public function __construct(string $category = null,
                                string $query = null,
                                array $filters = [],
                                bool $includeFilterAggregates = false,
                                int $page = 0,
                                int $size = 20,
                                string $sort = null,
                                bool $orderDesc = false)
    {
        $this->category = $category;
        $this->query = $query;
        $this->filters = $filters;
        $this->includeFilterAggregates = $includeFilterAggregates;
        $this->page = $page;
        $this->size = $size;
        $this->sort = $sort ?? ($category ? 'position' : 'relevance');
        $this->orderDesc = $orderDesc;
    }

    function getCategory(): ?string
    {
        return $this->category;
    }

    function getQuery(): ?string
    {
        return $this->query;
    }

    function getFilters(): array
    {
        return $this->filters;
    }

    function includeFilterAggregates(): bool
    {
        return $this->includeFilterAggregates;
    }

    function getPage(): int
    {
        return $this->page;
    }

    function getSize(): int
    {
        return $this->size;
    }

    function getSort(): string
    {
        return $this->sort;
    }

    function isOrderDesc(): bool
    {
        return $this->orderDesc;
    }
}