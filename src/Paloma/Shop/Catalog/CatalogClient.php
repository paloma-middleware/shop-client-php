<?php

namespace Paloma\Shop\Catalog;

use Paloma\Shop\BaseClient;
use Paloma\Shop\FileResponse;

class CatalogClient extends BaseClient implements CatalogClientInterface
{
    /**
     * CatalogClient accepts an array of constructor parameters.
     *
     * All parameters of BaseClient.
     *
     * @param string $baseUrl
     * @param array $options
     */
    public function __construct($baseUrl, array $options)
    {
        // Enable caching by default in the catalog endpoint
        $options['use_cache'] = isset($options['use_cache']) ? $options['use_cache'] : true;
        parent::__construct($baseUrl, $options);
    }

    public function search($search)
    {
        return $this->post($this->channel . '/' . $this->locale . '/search', null, $search);
    }

    public function searchFilterAggregates()
    {
        return $this->get($this->channel . '/' . $this->locale . '/search/filter-aggregates');
    }

    function searchSuggestions($query)
    {
        return $this->get($this->channel . '/' . $this->locale . '/search/suggestions', ['query' => $query]);
    }

    public function product($itemNumber)
    {
        return $this->get($this->channel . '/' . $this->locale . '/products/' . $itemNumber);
    }

    function similarProducts($itemNumber)
    {
        return $this->get($this->channel . '/' . $this->locale . '/products/' . $itemNumber . '/similar');
    }

    function recommendedProducts($itemNumber)
    {
        return $this->get($this->channel . '/' . $this->locale . '/products/' . $itemNumber . '/recommended');
    }

    function recommendations($order, $size = null)
    {
        return $this->post($this->channel . '/' . $this->locale . '/recommendations',
            $size ? ['size' => $size] : null, $order);
    }

    public function categories($depth = null, $products = true)
    {
        $query = ['products' => ($products ? 'true' : 'false')];
        if ($depth) {
            $query['depth'] = $depth;
        }

        return $this->get($this->channel . '/' . $this->locale . '/categories', $query);
    }

    public function category($code, $depth = null, $filterAggregates = null)
    {
        $query = [];
        if ($depth) {
            $query['depth'] = $depth;
        }
        if ($filterAggregates) {
            $query['filter-aggregates'] = $filterAggregates;
        }

        return $this->get($this->channel . '/' . $this->locale . '/categories/' . $code,
            count($query) > 0 ? $query : null);
    }

    public function categoryFilters($code)
    {
        return $this->get($this->channel . '/' . $this->locale . '/categories/' . $code . '/filter-aggregates');
    }

    function exportSearch($format, $body, $locale = null)
    {
        $query = ['format' => $format];
        $locale = $locale !== null ? $locale : $this->locale;
        return $this->post($this->channel . '/' . $locale . '/search/export', $query, $body);
    }

    function exportProducts($format, $body, $locale = null)
    {
        $query = ['format' => $format];
        $locale = $locale !== null ? $locale : $this->locale;
        return $this->post($this->channel . '/' . $locale . '/products/export', $query, $body);
    }

    function exportStatus($processId, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->locale;
        return $this->get($this->channel . '/' . $locale . '/export/' . $processId);
    }

    /**
     * @return FileResponse
     */
    function exportDownload($processId, $locale = null)
    {
        $locale = $locale !== null ? $locale : $this->locale;
        return $this->get($this->channel . '/' . $locale . '/export/' . $processId . '/download');
    }

}
