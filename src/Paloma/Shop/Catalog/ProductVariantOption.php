<?php

namespace Paloma\Shop\Catalog;

use Paloma\Shop\Common\SelfNormalizing;

class ProductVariantOption implements ProductVariantOptionInterface, SelfNormalizing
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    function getOption(): string
    {
        return $this->data['option'];
    }

    function getLabel(): string
    {
        return $this->data['label'];
    }

    function getValue(): string
    {
        return $this->data['value'];
    }

    public function _normalize(): array
    {
        return $this->data;
    }
}