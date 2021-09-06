<?php

namespace AmazonService\Services;

use AmazonService\Exceptions\CouldNotProcessProduct;
use AmazonService\Exceptions\ProductNotFound;
use AmazonService\Models\Product\Category;
use AmazonService\Models\Product\Merchant;
use AmazonService\Models\Product\Product;
use AmazonService\Utils\DomParserUtils;
use AmazonService\Utils\UserAgentUtils;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;

class ProductService
{
    /**
     * @var string
     */
    private $userAgent;

    public function __construct()
    {
        $this->userAgent = $this->getUserAgent();
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
    public function getByAsin(string $asin, string $domain): Product
    {
        $productUrl = "https://www.$domain/dp/$asin";
        try {
            [$dom, $xPath] = $this->initDom($productUrl);
        } catch (Exception $exception) {
            throw new ProductNotFound("Product for asin '$asin' was not found on '$domain'.", $this->userAgent, $exception->getCode(), $exception);
        }
        return $this->constructProduct($asin, $domain, $dom, $xPath);
    }

    /**
     * Initialize a virtual dom of given url
     *
     * @param $url
     * @return array
     * @throws ProductNotFound
     */
    private function initDom($url): array
    {
        $dom = new DOMDocument();
        $html = $this->getHtml($url);
        libxml_use_internal_errors(true);

        if (!$html) {
            throw new ProductNotFound();
        }

        $dom->loadHTML($html);
        libxml_clear_errors();
        $xPath = new DomXpath($dom);

        return [$dom, $xPath];
    }

    /**
     * Get HTML from Url by initiating Curl
     * @param  string $url
     * @return String
     */
    public function getHtml(string $url): string
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($curl);
        curl_close($curl);

        return $html;
    }

    /**
     * @param string $asin
     * @param string $domain
     * @return Product
     * @throws CouldNotProcessProduct
     */
    private function constructProduct(string $asin, string $domain, DOMDocument $dom, DOMXPath $xPath): Product
    {
        return new Product(
            $asin,
            $domain,
            $this->getName($dom, $xPath),
            $this->getImages($dom, $xPath),
            $this->getMerchant($dom, $xPath),
            $this->getCategories($dom, $xPath, $domain)
        );
    }

    /**
     * Parse virtual dom to find product name
     *
     * @return mixed
     * @throws CouldNotProcessProduct
     */
    private function getName(DOMDocument $dom, DOMXPath $xPath): string
    {
        $selectors = [
            "#title",
            '#productTitle'
        ];

        if (($nameDiv = DomParserUtils::findParentDiv($dom, $xPath, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("name", $selectors, $this->userAgent);
        }
        if ($nameDiv->getElementsByTagName('span')->count() === 0) {
            throw CouldNotProcessProduct::childDivNotFound("name", $selectors, 'span', $this->userAgent);
        }

        return explode("\n", trim($nameDiv->textContent))[0];
    }


    /**
     * Utils functions
     */

    /**
     * Parse virtual dom to find product images
     *
     * @param  DOMDocument  $dom
     * @param  DOMXPath  $xPath
     * @return array
     * @throws CouldNotProcessProduct
     */
    private function getImages(DOMDocument $dom, DOMXPath $xPath): array
    {
        $images = [];
        $selectors = [
            "#altImages",
            "#imageBlock_feature_div",
            "#imageBlockNew_feature_div",
            "#imageBlock",
            '#ebooksImageBlock'
        ];
        if (($imagesDiv = DomParserUtils::findParentDiv($dom, $xPath, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("images", $selectors, $this->userAgent);
        }
        $imagesDiv = $imagesDiv->getElementsByTagName('img');

        foreach ($imagesDiv as $key => $div) {
            $image = $div->attributes->getNamedItem('src');
            if (!$image) {
                $image = $div->attributes->getNamedItem('data-old-hires');
            }
            $image = $image->textContent;

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
     * @param  DOMDocument  $dom
     * @param  DOMXPath  $xPath
     * @return Merchant
     * @throws CouldNotProcessProduct
     */
    private function getMerchant(DOMDocument $dom, DOMXPath$xPath): Merchant
    {
        $selectors = [
            '.contributorNameID',
            '.prodDetAttrValue',
            "#bylineInfo",
        ];
        if (($merchantDiv = DomParserUtils::findParentDiv($dom, $xPath, $selectors)) === null) {
            throw CouldNotProcessProduct::parentDivNotFound("merchant", $selectors, $this->userAgent);
        }

        if ($merchantDiv instanceof DOMNodeList && $merchantDiv->count() > 0) {

            $merchantDiv = $merchantDiv->item(0);

            if ($merchantDiv) {
                return new Merchant(
                  $merchantDiv->firstChild->textContent ?? '',
                  $merchantDiv->getAttribute('href') ?? ''
                );
            }

        } else if ($merchantDiv instanceof DOMElement) {
            if ($merchantDiv->getAttribute('href')) {
                return new Merchant(
                  $merchantDiv->firstChild->textContent ?? '',
                  $merchantDiv->getAttribute('href') ?? ''
                );
            }

            $merchantDiv = $merchantDiv->getElementsByTagName('a');
            if (!$merchantDiv || $merchantDiv->count() == 0) {
                throw CouldNotProcessProduct::childDivNotFound("merchant", $selectors, 'a', $this->userAgent);
            }
        }

        return new Merchant(
          $merchantDiv->item(0)->textContent ?? '',
          $merchantDiv->item(0)->getAttribute('href') ?? ''
        );
    }

    /**
     * Retrieve product categories from virtual dom.
     *
     * @param  string  $domain
     * @return array
     * @throws CouldNotProcessProduct
     */
    private function getCategories(DOMDocument $dom, DOMXPath $xPath, string $domain): array
    {
        $selectors = [
            '#SalesRank',
            '#detailBullets_feature_div',
            '#productDetails_detailBullets_sections1'
        ];

        if (($rankDiv = DomParserUtils::findParentDiv($dom, $xPath, $selectors)) === null) {
            throw CouldNotProcessProduct::noCategoriesFound($this->userAgent);
        }

        $categories = [];

        if ($rankDiv instanceof DOMNodeList) {
            $rankDiv = $rankDiv->item(0);
        }

        $ranksDiv = $rankDiv->getElementsByTagName('a');

        foreach ($ranksDiv as $div) {
            $isTop = (bool) strstr($div->textContent, '100');
            $url = $div->getAttribute('href');
            if ($isTop) {
                $name = self::getCategoryNameWithUrl("https://www.$domain/" . $url);
                $position = $div->parentNode->textContent;
            } else {
                $name = $div->textContent;
                $position = $div->parentNode->parentNode->textContent;
            }
            $name = trim($name);
            $position = str_replace('-', '', $position);
            $position = filter_var($position, FILTER_SANITIZE_NUMBER_INT);

            if (str_contains($url,'/gp/')) {
                $categories[] = new Category(
                  $isTop,
                  (int)$position,
                  $name,
                  $url
                );
            }
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
    public function getCategoryNameWithUrl($url): string
    {
        try {
            [$dom, $xPath] = $this->initDom($url);
        } catch (Exception $exception) {
            throw CouldNotProcessProduct::cannotRetrieveCategoryNameWithUrl($url, $this->userAgent);
        }

        if (($categoryDiv = DomParserUtils::findParentDiv($dom, $xPath, ['.category'])) === null) {
            throw CouldNotProcessProduct::cannotRetrieveCategoryNameWithUrl($url, $this->userAgent);
        }

        if ($categoryDiv instanceof DOMNodeList) {
            $categoryDiv = $categoryDiv->item(0);
        }

        return trim($categoryDiv->textContent ?? '');

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
        $curl = curl_init("https://www.$domain/dp/$asin");
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $html = curl_exec($curl);
        curl_close($curl);

        if (!$html) {
            return false;
        }

        return true;
    }
}
