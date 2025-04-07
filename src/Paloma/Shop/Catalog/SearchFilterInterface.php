<?php

namespace Paloma\Shop\Catalog;

interface SearchFilterInterface
{
    /**
     * @see FilterAggregateInterface
     * @return string Filter name
     */
    function getName(): string;

    /**
     * @return array One or several filter values, will be applied using OR
     */
    function getValues(): array;

    /**
     * @return float|null Greater-than or equal to. Only applicable for numeric filter values.
     */
    function getGreaterThan(): ?float;

    /**
     * @return float|null Less-than or equal to. Only applicable for numeric filter values.
     */
    function getLessThan(): ?float;

    /**
     * @return string|null one of 'any', 'all', 'none' (default: 'any')
     */
    function getMatch(): ?string;

    function getOr(): ?SearchFilter;
}