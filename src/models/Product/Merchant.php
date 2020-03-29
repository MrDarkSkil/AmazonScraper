<?php


namespace AmazonService\Models\Product;


class Merchant
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $url;

    /**
     * Merchant constructor.
     * @param string $name
     * @param string $url
     */
    public function __construct(string $name, string $url)
    {
        $this->name = $name;
        $this->url = $url;
    }
}