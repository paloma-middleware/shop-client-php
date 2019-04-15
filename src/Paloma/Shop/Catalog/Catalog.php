<?php

namespace Paloma\Shop\Catalog;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Paloma\Shop\Error\BackendUnavailable;
use Paloma\Shop\Error\CategoryNotFound;
use Paloma\Shop\Error\InvalidInput;
use Paloma\Shop\Error\ProductNotFound;
use Paloma\Shop\PalomaClientInterface;

class Catalog implements CatalogInterface
{
    /**
     * @var PalomaClientInterface
     */
    private $client;

    public function __construct(PalomaClientInterface $client)
    {
        $this->client = $client;
    }

    function search(SearchRequestInterface $searchRequest): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->search([
                'category' => $searchRequest->getCategory(),
                'query' => $searchRequest->getQuery(),
                'page' => max(0, $searchRequest->getPage()),
                'size' => min(100, $searchRequest->getSize()),
                'filters' => array_map(function (SearchFilterInterface $filter) {
                        return [
                            'property' => $filter->getName(), // Filter name can be used here
                            'values' => array_values($filter->getValues()),
                        ];
                    }, $searchRequest->getFilters()),
                'filterAggregates' => $searchRequest->includeFilterAggregates(),
                'sort' => $searchRequest->getSort(),
                'order' => $searchRequest->isOrderDesc() ? 'desc' : 'asc',
                // TODO context
            ]);

            return new ProductPage($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        } catch (BadResponseException $bse) {
            throw new InvalidInput();
        }
    }

    function getSearchSuggestions(string $query): SearchSuggestionsInterface
    {
        try {

            $data = $this->client->catalog()->searchSuggestions($query);

            return new SearchSuggestions($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }
    }

    function getProduct(string $itemNumber): ProductInterface
    {
        try {

            $data = $this->client->catalog()->product($itemNumber);

            return new Product($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        }
    }

    function getSimilarProducts(string $itemNumber): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->similarProducts($itemNumber);

            return new ProductPage($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        }
    }

    function getRecommendedProducts(string $itemNumber): ProductPageInterface
    {
        try {

            $data = $this->client->catalog()->recommendedProducts($itemNumber);

            return new ProductPage($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }  catch (BadResponseException $bre) {
            if ($bre->getCode() === 404) {
                throw new ProductNotFound();
            }
            throw $bre;
        }
    }

    function getProductsForCart($size = 5): ProductPageInterface
    {
        $cart = $this->client->checkout()->checkoutOrder();

        if ($cart->itemsCount() === 0) {
            return ProductPage::createEmpty();
        }

        try {

            $data = $this->client->catalog()->recommendations($cart->get(), $size);

            return new ProductPage($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }
    }

    /**
     * @param int $depth
     * @return Category[]
     * @throws BackendUnavailable
     */
    function getCategories(int $depth = 0): array
    {
        try {

            $data = $this->client->catalog()->categories($depth);

            return array_map(function($elem) {
                return new Category($elem);
            }, $data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        }
    }

    function getCategory(string $categoryCode, int $depth = 0, bool $includeFilterAggregates = false): CategoryInterface
    {
        try {

            $data = $this->client->catalog()->category($categoryCode, $depth, $includeFilterAggregates);

            return new Category($data);

        } catch (ServerException $se) {
            throw new BackendUnavailable();
        } catch (BadResponseException $bre) {
            throw new CategoryNotFound();
        }
    }
}