<?php

namespace AmazonService\Models\Product;

class Product
{
    /**
     * @var string
     */
    public $asin;
    /**
     * @var string
     */
    public $domain;
    /**
     * @var string
     */
    public $name;
    /**
     * @var array
     */
    public $images;
    /**
     * @var Merchant
     */
    public $merchant;
    /**
     * @var Category[]
     */
    public $categories;

    /**
     * Product constructor.
     * @param string $asin
     * @param string $domain
     * @param string $name
     * @param array $images
     * @param Merchant $merchant
     * @param array $categories
     */
    public function __construct(
        string $asin,
        string $domain,
        string $name,
        array $images,
        Merchant $merchant,
        array $categories)
    {
        $this->asin = $asin;
        $this->domain = $domain;
        $this->name = $name;
        $this->images = $images;
        $this->merchant = $merchant;
        $this->categories = $categories;
    }


}