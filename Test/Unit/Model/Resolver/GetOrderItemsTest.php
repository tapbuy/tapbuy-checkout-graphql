<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\SalesGraphQl\Model\OrderItem\DataProvider as OrderItemProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\GetOrderItems;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class GetOrderItemsTest extends TestCase
{
    private GetOrderItems $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private ValueFactory&MockObject $valueFactory;
    private OrderItemProvider&MockObject $orderItemProvider;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->valueFactory = $this->createMock(ValueFactory::class);
        $this->orderItemProvider = $this->createMock(OrderItemProvider::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new GetOrderItems(
            $this->tokenAuthorization,
            $this->valueFactory,
            $this->orderItemProvider,
            $this->config
        );
    }

    public function testReturnsEmptyWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertSame([], $result);
    }

    public function testThrowsWhenNoModelInValue(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(LocalizedException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, ['model' => 'not_an_order']);
    }

    public function testResolvesOrderItems(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $item1 = $this->createMock(OrderItemInterface::class);
        $item1->method('getItemId')->willReturn(10);
        $item1->method('getParentItemId')->willReturn(null);

        $item2 = $this->createMock(OrderItemInterface::class);
        $item2->method('getItemId')->willReturn(20);
        $item2->method('getParentItemId')->willReturn(10); // child item

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn([$item1, $item2]);

        $this->valueFactory->method('create')->willReturn('deferred_value');

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $order]
        );

        // Only non-parent items should be in the result (item1 only)
        $this->assertCount(1, $result);
    }
}
