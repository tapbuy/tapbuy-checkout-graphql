<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;
use Tapbuy\CheckoutGraphql\Model\Resolver\GetOrder;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;

class GetOrderTest extends TestCase
{
    private GetOrder $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private OrderDataFormatterInterface&MockObject $orderFormatter;
    private OrderLocatorInterface&MockObject $orderLocator;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->orderFormatter = $this->createMock(OrderDataFormatterInterface::class);
        $this->orderLocator = $this->createMock(OrderLocatorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new GetOrder(
            $this->tokenAuthorization,
            $this->orderFormatter,
            $this->orderLocator,
            $this->config
        );
    }

    public function testThrowsWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info);
    }

    public function testThrowsWhenNoOrderIdentifier(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, []);
    }

    public function testResolvesOrderByOrderNumber(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $order = $this->createMock(OrderInterface::class);

        $this->orderLocator->method('getByIdentifier')
            ->with('100000001', OrderLocatorInterface::IDENTIFIER_TYPE_INCREMENT_ID)
            ->willReturn($order);

        $this->orderFormatter->method('format')->with($order)->willReturn(['number' => '100000001']);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['order_number' => '100000001']
        );

        $this->assertSame('100000001', $result['number']);
    }

    public function testResolvesOrderByEntityId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $order = $this->createMock(OrderInterface::class);

        $this->orderLocator->method('getByIdentifier')
            ->with('42', OrderLocatorInterface::IDENTIFIER_TYPE_ENTITY_ID)
            ->willReturn($order);

        $this->orderFormatter->method('format')->willReturn(['id' => 42]);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['order_id' => '42']
        );

        $this->assertSame(42, $result['id']);
    }

    public function testThrowsNotFoundWhenOrderDoesNotExist(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->orderLocator->method('getByIdentifier')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(GraphQlNoSuchEntityException::class);

        $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['order_number' => '999999']
        );
    }
}
