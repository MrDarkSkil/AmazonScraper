<?php


namespace AmazonService\Models\Product;


class Category
{

    /**
     * @var bool
     */
    public $top;
    /**
     * @var int
     */
    public $position;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $url;

    /**
     * Category constructor.
     * @param bool $top
     * @param int $position
     * @param string $name
     * @param string $url
     */
    public function __construct(
        bool $top,
        int $position,
        string $name,
        string $url
    )
    {
        $this->top = $top;
        $this->position = $position;
        $this->name = $name;
        $this->url = $url;
    }
}