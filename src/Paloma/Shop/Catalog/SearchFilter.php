<?php

namespace Paloma\Shop\Catalog;

class SearchFilter implements SearchFilterInterface
{
    private string $name;

    private array $values;

    private ?float $greaterThan;

    private ?float $lessThan;

    /**
     * @var string  one of 'any', 'all', 'none' (default: 'any')
     */
    private string $match = 'any';

    private ?SearchFilter $or;

    /**
     * @param string $name
     * @param string[] $values
     * @param float|null $greaterThan
     * @param float|null $lessThan
     * @param string $match
     * @param SearchFilter|null $or
     */
    public function __construct(string $name,
                                array $values = [],
                                float $greaterThan = null,
                                float $lessThan = null,
                                string $match = 'any',
                                SearchFilter $or = null)
    {
        $this->name = $name;
        $this->values = array_values($values);
        $this->greaterThan = $greaterThan;
        $this->lessThan = $lessThan;
        $this->match = $match;
        $this->or = $or;
    }

    function getName(): string
    {
        return $this->name;
    }

    function getValues(): array
    {
        return $this->values;
    }

    function getGreaterThan(): ?float
    {
        return $this->greaterThan;
    }

    function getLessThan(): ?float
    {
        return $this->lessThan;
    }

    function getMatch(): string
    {
        return $this->match;
    }

    function getOr(): ?SearchFilter
    {
        return $this->or;
    }
}