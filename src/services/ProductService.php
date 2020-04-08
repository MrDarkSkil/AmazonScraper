<?php

namespace AmazonService\Services;

use AmazonService\Exceptions\CouldNotProcessProduct;
use AmazonService\Exceptions\ProductNotFound;
use AmazonService\Models\Product\Category;
use AmazonService\Models\Product\Merchant;
use AmazonService\Models\Product\Product;
use AmazonService\Utils\DomParserUtils;
use AmazonService\Utils\UserAgentUtils;
use Exception;
use GuzzleHttp\Client;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\StrictException;

class ProductService
{
    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var Client
     */
    private $httpClient;

    public function __construct()
    {
        $this->userAgent = $this->getUserAgent();
        $this->httpClient = new Client([
            'headers' => [
                'User-Agent' => $this->userAgent
            ]
        ]);
    }

    /**
     * This will generate random userAgent
     * every 60 seconds.
     *
     * @return false|mixed|string
     */
    private function getUserAgent()
    {
        $cacheFilePath = '/tmp/userAgent';
        if (file_exists($cacheFilePath) && ((time() - 60) < filemtime($cacheFilePath))) {
            return file_get_contents($cacheFilePath);
        }
        $userAgent = UserAgentUtils::getRandomUserAgent();
        file_put_contents($cacheFilePath, $userAgent);
        return $userAgent;
    }

    /**
     * Retrieve product by given ASIN and DOMAIN (like: amazon.com)
     *
     * @param string $asin
     * @param string $domain
     * @return Product
     * @throws ProductNotFound
     * @throws CouldNotProcessProduct
     */
    public function getByAsin(string $asin, string $domain)
    {
        $productUrl = "https://www.$domain/dp/$asin";
        try {
            $dom = $this->initDom($productUrl);
        } catch (Exception $exception) {
            throw new ProductNotFound("Product for asin '$asin' was not found on '$domain'.", $this->userAgent, $exception->getCode(), $exception);
        }
        return $this->constructProduct($asin, $domain, $dom);
    }

    /**
     * Initialize a virtual dom of given url
     *
     * @param $url
     * @return Dom
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws StrictException
     */
    private function initDom($url): Dom
    {
        $dom = new Dom;
        $response = $this->httpClient->get($url);
        $dom->loadStr(html_entity_decode($response->getBody()->getContents()), [
            'enforceEncoding' => 'UTF-8'
        ]);
        return $dom;
    }

    /**
     * @param string $asin
     * @param string $domain
     * @param Dom $dom
     * @return Product
     * @throws CouldNotProcessProduct
     */
    private function constructProduct(string $asin, string $domain, Dom $dom): Product
    {
        return new Product(
            $asin,
            $domain,
            $this->getName($dom),
            $this->getImages($dom),
            $this->getMerchant($dom),
            $this->getCategories($dom, $domain)
        );
    }

    /**
     * Parse virtual dom to find product name
     *
     * @param Dom $dom
     * @return mixed
     * @throws CouldNotProcessProduct
     */
    private function getName(Dom $dom): string
    {
        $selectors = [
            "#title"
        ];
        if (($nameDiv = DomParserUtils::findParentDiv($dom, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("name", $selectors, $this->userAgent);
        }
        if ($nameDiv->offsetGet(0)->find('span')->count() === 0) {
            throw CouldNotProcessProduct::childDivNotFound("name", $selectors, 'span', $this->userAgent);
        }
        return trim($nameDiv->offsetGet(0)->find('span')->firstChild()->text());
    }


    /**
     * Utils functions
     */

    /**
     * Parse virtual dom to find product images
     *
     * @param Dom $dom
     * @return array
     * @throws CouldNotProcessProduct
     */
    private function getImages(Dom $dom): array
    {
        $images = [];
        $selectors = [
            "#altImages",
            "#imageBlock_feature_div",
            "#imageBlockNew_feature_div",
            "#imageBlock"
        ];
        if (($imagesDiv = DomParserUtils::findParentDiv($dom, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("images", $selectors, $this->userAgent);
        }
        $imagesDiv = $imagesDiv->find('img');
        foreach ($imagesDiv as $key => $div) {
            $image = null;
            if ($div->getAttribute('src')) {
                $image = $div->getAttribute('src');
            } else if ($div->getAttribute('data-old-hires')) {
                $image = $div->getAttribute('data-old-hires');
            }
            if ($image &&
                (strstr($image, "/images/I/") ||
                    strstr($image, "base64"))) {
                $image = str_replace("S40_", "S300_", $image);
                $images[] = $image;
            }
        }

        return $images;
    }

    /**
     * Parse virtual dom to retrieve merchant information
     *
     * @param Dom $dom
     * @return Merchant
     * @throws CouldNotProcessProduct
     */
    private function getMerchant(Dom $dom): Merchant
    {
        $selectors = [
            "#bylineInfo"
        ];
        if (($merchantDiv = DomParserUtils::findParentDiv($dom, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("merchant", $selectors, $this->userAgent);
        }
        if ($merchantDiv->getAttribute('href')) {
            return new Merchant(
                $merchantDiv->firstChild()->text() ?? '',
                $merchantDiv->getAttribute('href') ?? ''
            );
        }
        $merchantDiv = $merchantDiv->find('a');
        if (!$merchantDiv || $merchantDiv->count() == 0) {
            throw CouldNotProcessProduct::childDivNotFound("merchant", $selectors, 'a', $this->userAgent);
        }
        return new Merchant(
            $merchantDiv->firstChild()->text() ?? '',
            $merchantDiv->getAttribute('href') ?? ''
        );
    }

    /**
     * Retrieve product categories from virtual dom.
     *
     * @param Dom $dom
     * @param string $domain
     * @return array
     * @throws CouldNotProcessProduct
     */
    private function getCategories(Dom $dom, string $domain): array
    {
        $selectors = [
            '#SalesRank'
        ];
        if (($rankDiv = DomParserUtils::findParentDiv($dom, $selectors)) === null) {
            throw CouldNotProcessProduct::noCategoriesFound($this->userAgent);
        }
        $categories = [];
        /**
         * @var $randDiv Dom\Collection
         */
        $ranksDiv = $rankDiv->find('a');

        foreach ($ranksDiv as $div) {
            $isTop = (strstr($div->text(), '100') ? true : false);
            $url = $div->getAttribute('href');
            if ($isTop) {
                $name = self::getCategoryNameWithUrl("https://www.$domain/" . $url);
                $position = $div->getParent()->text();
            } else {
                $name = $div->text();
                $position = $div->getParent()->getParent()->find('.zg_hrsr_rank')->text();
            }
            $name = trim($name);
            $position = str_replace('-', '', $position);
            $position = filter_var($position, FILTER_SANITIZE_NUMBER_INT);
            $categories[] = new Category(
                $isTop,
                $position,
                $name,
                $url
            );
        }

        return $categories;
    }

    /**
     * Retrieve a specific category name from url
     *
     * @param $url
     * @return string
     * @throws CouldNotProcessProduct
     */
    public function getCategoryNameWithUrl($url)
    {
        try {
            $dom = $this->initDom($url);
        } catch (Exception $exception) {
            throw CouldNotProcessProduct::cannotRetrieveCategoryNameWithUrl($url, $this->userAgent);
        }

        if (($categoryDiv = DomParserUtils::findParentDiv($dom, ['.category'])) === null) {
            throw CouldNotProcessProduct::cannotRetrieveCategoryNameWithUrl($url, $this->userAgent);
        }

        return trim($categoryDiv->firstChild()->text() ?? '');

    }

    /**
     * Tell if a product exits on Amazon with a given ASIN and DOMAIN
     *
     * @param string $asin
     * @param string $domain
     * @return bool
     */
    public function existByAsin(string $asin, string $domain): bool
    {
        try {
            $this->httpClient->get("https://www.$domain/dp/$asin");
        } catch (Exception $exception) {
            return false;
        }
        return true;
    }
}