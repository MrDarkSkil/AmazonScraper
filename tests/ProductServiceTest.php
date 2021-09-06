<?php

namespace AmazonService\Tests;

use AmazonService\Enums\AmazonDomains;
use AmazonService\Exceptions\AmazonServiceException;
use AmazonService\Exceptions\CouldNotProcessProduct;
use AmazonService\Exceptions\ProductNotFound;
use AmazonService\Services\ProductService;
use PHPUnit\Framework\TestCase;

class ProductServiceTest extends TestCase
{
    const AMAZON_DOMAIN = AmazonDomains::FR;

    const BOOK_ASIN = "B07KPJP3XP";
    const BOOK_NAME = "BRILLE, tant que tu vis!: Un roman qui donne envie d'aimer";
    const BOOK_MERCHANT_NAME = "Alice Quinn";

    const PRODUCT_ASIN = "B0756F2SV2";
    const PRODUCT_NAME = "Calendrier 2022 - format A4 - Papier épais - prévu pour l'écriture.";
    const PRODUCT_MERCHANT_NAME = "La petite fabrique";

    const WRONG_ASIN = "FooBar";

    const COULD_NOT_PROCESS_ASIN = "B07ZPML7NP"; // Apple AirPods Pro don't have categories (ASIN: B07ZPML7NP)
    const COULD_NOT_PROCESS_EXCEPTION_MESSAGE = "No categories found.";

    /**
     * @var ProductService
     */
    private ProductService $productService;

    /**
     * @test
     *
     * @throws CouldNotProcessProduct
     * @throws ProductNotFound
     */
    public function product_service_must_retrieve_correct_data_with_book()
    {
        $product = $this->productService->getByAsin($this::BOOK_ASIN, $this::AMAZON_DOMAIN);

        $this->assertEquals($this::BOOK_NAME, $product->name);
        $this->assertEquals($this::BOOK_ASIN, $product->asin);
        $this->assertEquals($this::AMAZON_DOMAIN, $product->domain);
        $this->assertCount(4, $product->categories);
        $this->assertEquals($this::BOOK_MERCHANT_NAME, $product->merchant->name);
        $this->assertNotNull($product->merchant->url);

        foreach ($product->categories as $category) {
            $this->assertIsBool($category->top);
            $this->assertIsNumeric($category->position);
            $this->assertNotEmpty($category->name);
            $this->assertNotEmpty($category->url);
        }
    }

    /**
     * @test
     *
     * @throws CouldNotProcessProduct
     * @throws ProductNotFound
     */
    public function product_service_must_retrieve_correct_data_with_product()
    {
        $product = $this->productService->getByAsin($this::PRODUCT_ASIN, $this::AMAZON_DOMAIN);

        $this->assertEquals($this::PRODUCT_NAME, $product->name);
        $this->assertEquals($this::PRODUCT_ASIN, $product->asin);
        $this->assertEquals($this::AMAZON_DOMAIN, $product->domain);
        $this->assertCount(2, $product->categories);
        $this->assertEquals($this::PRODUCT_MERCHANT_NAME, $product->merchant->name);
        $this->assertNotNull($product->merchant->url);

        foreach ($product->categories as $category) {
            $this->assertIsBool($category->top);
            $this->assertIsNumeric($category->position);
            $this->assertNotEmpty($category->name);
            $this->assertNotEmpty($category->url);
        }
    }

    /**
     * @test
     * @covers
     *
     * @throws ProductNotFound
     * @throws CouldNotProcessProduct
     */
    public function must_return_product_not_found_exceptions_with_wrong_asin()
    {
        $this->expectException(ProductNotFound::class);
        $this->productService->getByAsin($this::WRONG_ASIN, $this::AMAZON_DOMAIN);
    }

    /**
     * @test
     */
    public function when_exception_handled_user_agent_must_be_not_null()
    {
        try {
            $this->productService->getByAsin($this::WRONG_ASIN, $this::AMAZON_DOMAIN);
        } catch (AmazonServiceException $exception) {
            $this->assertNotNull($exception->getUserAgent());
        }
    }

    /**
     * @test
     *
     * @throws ProductNotFound
     * @throws CouldNotProcessProduct
     */
    public function must_return_could_not_process_product_exceptions()
    {
        $this->expectException(CouldNotProcessProduct::class);
        $this->expectExceptionMessage($this::COULD_NOT_PROCESS_EXCEPTION_MESSAGE);
        $this->productService->getByAsin($this::COULD_NOT_PROCESS_ASIN, $this::AMAZON_DOMAIN);
    }

    /**
     * @test
     */
    public function product_exist_must_return_false_with_wrong_asin()
    {
        $this->assertNotTrue($this->productService->existByAsin($this::WRONG_ASIN, $this::AMAZON_DOMAIN));
    }

    /**
     * @test
     */
    public function product_exist_must_return_true_with_good_asin()
    {
        $this->assertTrue($this->productService->existByAsin($this::BOOK_ASIN, $this::AMAZON_DOMAIN));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = new ProductService();
    }
}
