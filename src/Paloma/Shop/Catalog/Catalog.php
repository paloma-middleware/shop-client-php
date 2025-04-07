<?php

namespace Paloma\Shop\Catalog;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use Paloma\Shop\Common\PricingContextProviderInterface;
use Paloma\Shop\Customers\CustomersInterface;
use Paloma\Shop\Error\BackendUnavailable;
use Paloma\Shop\Error\CategoryNotFound;
use Paloma\Shop\Error\InvalidInput;
use Paloma\Shop\Error\ProductNotFound;
use Paloma\Shop\PalomaClientInterface;
use Paloma\Shop\Security\PalomaSecurityInterface;

class Catalog implements CatalogInterface
{
    /**
     * @var PalomaClientInterface
     */
    private $client;

    /**
     * @var PricingContextProviderInterface
     */
    private $contextProvider;

    public function __construct(PalomaClientInterface $client, PricingContextProviderInterface $contextProvider)
    {
        $this->client = $client;
        $this->contextProvider = $contextProvider;
    }

    function search(SearchRequestInterface $searchRequest): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->search([
                'category' => $searchRequest->getCategory(),
                'query' => $searchRequest->getQuery(),
                'page' => max(0, $searchRequest->getPage()),
                'size' => min(100, $searchRequest->getSize()),
                'filters' => array_map(function(SearchFilterInterface $filter) {
                        return $this->normalizeSearchFilter($filter);
                    }, $searchRequest->getFilters()),
                'filterAggregates' => $searchRequest->isIncludeFilterAggregates(),
                'sort' => $searchRequest->getSort(),
                'order' => $searchRequest->isOrderDesc() ? 'desc' : 'asc',
                'context' => $this->createContext(),
            ]);

            return new ProductPage($data);

        } catch (BadResponseException $bse) {
            throw new InvalidInput();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getSearchSuggestions(string $query): SearchSuggestionsInterface
    {
        try {

            $data = $this->client->catalog()->searchSuggestions($query);

            return new SearchSuggestions($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getProduct(string $itemNumber): ProductInterface
    {
        try {

            $data = $this->client->catalog()->product($itemNumber, $this->createContext());

            return new Product($data);

        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getSimilarProducts(string $itemNumber): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->similarProducts($itemNumber, $this->createContext());

            return new ProductPage($data);

        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getRecommendedProducts(string $itemNumber): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->recommendedProducts($itemNumber, $this->createContext());

            return new ProductPage($data);

        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getPurchasedTogether(string $itemNumber, int $max = 5): ProductPageInterface
    {
        $max = min(20, $max);

        $data = $this->client->customers()->getItemCodesPurchasedTogether($itemNumber, $max);

        $itemCodes = array_map(function($elem) {
            return $elem['itemCode'];
        }, $data);

        return $this->getProductsForItemCodes($itemCodes, $max);
    }

    /**
     * @param array $itemCodes
     * @param int $max
     * @return ProductPage|ProductPageInterface
     * @throws BackendUnavailable
     * @throws InvalidInput
     */
    protected function getProductsForItemCodes(array $itemCodes = [], int $max = 5): ProductPageInterface
    {
        if (count($itemCodes) === 0) {
            return ProductPage::createEmpty();
        }

        // By default, order item codes contain product item numbers.
        // However, depending on the Paloma configuration, the item code can contain other identifiers like a short number.
        // If this is the case, this method can be overridden to find the appropriate products in the catalog.

        $searchFilters = [
            new SearchFilter('itemNumber', $itemCodes),
        ];

        return $this->search(new SearchRequest(
            null,
            null,
            $searchFilters,
            false,
            0,
            $max
        ));
    }

    function getProductsForCart($size = 5): ProductPageInterface
    {
        $cart = $this->client->checkout()->checkoutOrder();

        if ($cart->itemsCount() === 0) {
            return ProductPage::createEmpty();
        }

        try {

            $data = $this->client->catalog()->recommendations($cart->get(), $size, $this->createContext());

            return new ProductPage($data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    /**
     * @param int $depth
     * @param bool $includeUnlisted
     * @return Category[]
     * @throws BackendUnavailable
     */
    function getCategories(int $depth = 0, bool $includeUnlisted = false): array
    {
        try {

            $data = $this->client->catalog()->categories($depth, false, $includeUnlisted);

            return array_map(function($elem) {
                return new Category($elem);
            }, $data);

        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    function getCategory(string $categoryCode, int $depth = 0, bool $includeFilterAggregates = false, bool $includeUnlisted = false): CategoryInterface
    {
        try {

            $data = $this->client->catalog()->category($categoryCode, $depth, $includeFilterAggregates, $includeUnlisted);

            return new Category($data);

        } catch (BadResponseException $bre) {
            throw new CategoryNotFound();
        } catch (TransferException $se) {
            throw BackendUnavailable::ofException($se);
        }
    }

    private function createContext()
    {
        $context = $this->contextProvider->provide();

        return $context ? $context->toArray() : null;
    }

    /**
     * @param SearchFilterInterface $filter
     * @return array
     */
    function normalizeSearchFilter(SearchFilterInterface $filter): array
    {
        return [
            'property' => $filter->getName(), // Filter name can be used here
            'values' => array_values($filter->getValues()),
            'greaterThan' => $filter->getGreaterThan(),
            'lessThan' => $filter->getLessThan(),
            'match' => $filter->getMatch(),
            'or' => ($filter->getOr() ? $this->normalizeSearchFilter($filter->getOr()) : null),
        ];
    }
}