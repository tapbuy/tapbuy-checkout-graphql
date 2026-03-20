<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\Product\ParentSku;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class ParentSkuTest extends TestCase
{
    private ParentSku $resolver;
    private ConfigurableType&MockObject $configurableType;
    private ProductRepositoryInterface&MockObject $productRepository;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->configurableType = $this->createMock(ConfigurableType::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new ParentSku(
            $this->configurableType,
            $this->productRepository,
            $this->config
        );
    }

    public function testReturnsNullWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, null, $this->info);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoModel(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $result = $this->resolver->resolve($this->field, null, $this->info, []);

        $this->assertNull($result);
    }

    public function testReturnsNullForNonSimpleProduct(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('configurable');

        $result = $this->resolver->resolve($this->field, null, $this->info, ['model' => $product]);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoParentIds(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getId')->willReturn(10);

        $this->configurableType->method('getParentIdsByChild')->with(10)->willReturn([]);

        $result = $this->resolver->resolve($this->field, null, $this->info, ['model' => $product]);

        $this->assertNull($result);
    }

    public function testReturnsParentSkuWhenFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getId')->willReturn(10);

        $this->configurableType->method('getParentIdsByChild')->with(10)->willReturn([5]);

        $parentProduct = $this->createMock(ProductInterface::class);
        $parentProduct->method('getSku')->willReturn('PARENT-SKU');
        $this->productRepository->method('getById')->with(5)->willReturn($parentProduct);

        $result = $this->resolver->resolve($this->field, null, $this->info, ['model' => $product]);

        $this->assertSame('PARENT-SKU', $result);
    }

    public function testReturnsNullWhenParentProductNotFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getId')->willReturn(10);

        $this->configurableType->method('getParentIdsByChild')->willReturn([999]);
        $this->productRepository->method('getById')
            ->willThrowException(new NoSuchEntityException());

        $result = $this->resolver->resolve($this->field, null, $this->info, ['model' => $product]);

        $this->assertNull($result);
    }
}
