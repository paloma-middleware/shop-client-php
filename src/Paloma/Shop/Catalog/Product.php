<?php

namespace Paloma\Shop\Catalog;

use DateTime;
use InvalidArgumentException;
use Paloma\Shop\Common\SelfNormalizing;
use Paloma\Shop\Utils\NormalizationUtils;

class Product implements ProductInterface, SelfNormalizing
{
    private $data;

    private $_master = null; // cache

    private $_variants = null; // cache

    private $_options = null; // cache

    public function __construct(array $data)
    {
        if (count($data['variants']) === 0) {
            throw new InvalidArgumentException('variants missing in product data');
        }

        $this->data = $data;
    }

    function getItemNumber(): string
    {
        return $this->data['itemNumber'];
    }

    function getStatus(): string
    {
        return $this->data['status'] ?? 'active';
    }

    function getName(): string
    {
        return $this->data['name'];
    }

    function getSlug(): string
    {
        return $this->data['slug'];
    }

    function getDescription(): ?string
    {
        return $this->data['description'];
    }

    function getShortDescription(): ?string
    {
        return $this->data['shortDescription'];
    }

    function getBasePrice(): string
    {
        return $this->getMasterVariant()->getPrice();
    }

    function getOriginalBasePrice(): ?string
    {
        return $this->getMasterVariant()->getOriginalPrice();
    }

    function getReductionPercent(): ?string
    {
        return $this->getMasterVariant()->getReductionPercent();
    }

    function getTaxRate(): string
    {
        return $this->getMasterVariant()->getTaxRate();
    }

    function isTaxIncluded(): bool
    {
        return $this->getMasterVariant()->isTaxIncluded();
    }

    private function getMasterVariant()
    {
        if ($this->_master !== null) {
            return $this->_master;
        }

        $this->_master = new ProductVariant($this->data['master']);

        return $this->_master;
    }

    /**
     * @return ProductVariantInterface[]
     */
    function getVariants(): array
    {
        if ($this->_variants !== null) {
            return $this->_variants;
        }

        $this->_variants = array_map(function($elem) {
            return new ProductVariant($elem);
        }, $this->data['variants']);

        return $this->_variants;
    }

    function getOptions(): array
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $options = [];
        $variants = $this->getVariants();

        foreach ($variants as $variant) {

            /**  @var ProductVariantOptionInterface $option */
            foreach ($variant->getOptions() as $type => $option) {

                if (!isset($options[$type])) {
                    $options[$type] = [
                        'type' => $type,
                        'label' => $option->getLabel(),
                        'values' => [],
                    ];
                }

                $value = $option->getValue();

                if (!isset($options[$type]['values'][$value])) {
                    $options[$type]['values'][$value] = [
                        'value' => $value,
                        'label' => $value,
                        'variants' => [],
                    ];
                }

                $options[$type]['values'][$value]['variants'][] = $variant->getSku();
            }
        }

        // Do we have variants but are not using options?
        if (count($variants) > 0 && count($options) === 0) {
            $options['_variant'] = [
                'type' => '_variant',
                'label' => 'Variants',
                'values' => [],
            ];
            foreach ($variants as $variant) {

                $options['_variant']['values'][$variant->getSku()] = [
                    'value' => $variant->getSku(),
                    'label' => $variant->getName(),
                    'variants' => [
                        $variant->getSku(),
                    ],
                ];
            }
        }

        $this->_options = array_map(function($elem) {
            return new ProductOption($elem);
        }, $options);

        return $this->_options;
    }

    function getAttributes(array $displays = ['overview', 'product']): array
    {
        $attributes = [];
        foreach (array_values($this->data['master']['attributes'] ?? []) as $attr) {
            if (in_array($attr['display'], $displays)) {
                $attributes[$attr['type']] = new ProductAttribute($attr);
            }
        }

        return $attributes;
    }

    function getAttribute(string $type): ?ProductAttributeInterface
    {
        if (isset($this->data['master']['attributes'][$type])) {
            return new ProductAttribute($this->data['master']['attributes'][$type]);
        }

        return null;
    }

    /**
     * @return ImageInterface[]
     */
    function getImages(): array
    {
        return array_map(function($elem) {
            return new Image($elem, 'product');
        }, $this->data['master']['images'] ?? []);
    }

    function getFirstImage(): ?ImageInterface
    {
        $productImages = $this->data['master']['images'] ?? [];
        if (count($productImages) > 0) {
            return new Image($productImages[0], 'product');
        }

        if (count($this->data['variants'] ?? []) === 0) {
            return null;
        }

        // use first variant image
        foreach ($this->data['variants'] as $variant) {
            if (count($variant['images'] ?? []) > 0) {
                return new Image($variant['images'][0], 'variant', $variant['sku']);
            }
        }

        return null;
    }

    /**
     * @return ImageInterface[]
     */
    function getAllImages(): array
    {
        $images = [];

        foreach ($this->getVariants() as $variant) {
            foreach ($variant->getImages() as $image) {
                if (!isset($images[$image->getName()])) {
                    $images[$image->getName()] = $image;
                }
            }
        }

        foreach ($this->getImages() as $image) {
            if (!isset($images[$image->getName()])) {
                $images[$image->getName()] = $image;
            }
        }

        return $images;
    }

    /**
     * @return CategoryReferenceInterface[]
     */
    function getCategories(): array
    {
        return array_map(function($elem) {
           return new CategoryReference($elem);
        }, $this->data['categories'] ?? []);
    }

    function getMainCategory(): ?CategoryReferenceInterface
    {
        if (!isset($this->data['categories']) || count($this->data['categories']) === 0) {
            return null;
        }

        $mainCat = $this->data['categories'][0];
        foreach ($this->data['categories'] as $cat) {
            if ($cat['level'] > $mainCat['level']) {
                $mainCat = $cat;
            }
        }

        return new CategoryReference($mainCat);
    }

    function getCreated(): ?DateTime
    {
        return isset($this->data['created'])
            ? new DateTime($this->data['created'])
            : null;
    }

    function getModified(): ?DateTime
    {
        return isset($this->data['modified'])
            ? new DateTime($this->data['modified'])
            : null;
    }

    public function _normalize(): array
    {
        $data = NormalizationUtils::copyKeys([
            'itemNumber',
            'name',
            'slug',
            'description',
            'shortDescription',
            'created',
            'modified',
        ], $this->data);

        $data['status'] = $this->getStatus();

        $data['basePrice'] = $this->getBasePrice();
        $data['originalBasePrice'] = $this->getOriginalBasePrice();
        $data['reductionPercent'] = $this->getReductionPercent();
        $data['taxRate'] = $this->getTaxRate();
        $data['taxIncluded'] = $this->isTaxIncluded();

        $data['variants'] = array_map(function($elem) {
            return $elem->_normalize();
        }, $this->getVariants());

        $data['options'] = array_map(function($elem) {
            return $elem->_normalize();
        }, $this->getOptions());

        $data['attributes'] = array_map(function($elem) {
            return $elem->_normalize();
        }, $this->getAttributes());

        $data['images'] = array_map(function($elem) {
            return $elem->_normalize();
        }, $this->getImages());

        $firstImage = $this->getFirstImage();
        $data['firstImage'] = $firstImage ? $firstImage->_normalize() : null;

        $data['categories'] = array_map(function($elem) {
            return $elem->_normalize();
        }, $this->getCategories());

        $mainCategory = $this->getMainCategory();
        $data['mainCategory'] = $mainCategory ? $mainCategory->_normalize() : null;

        return $data;
    }
}