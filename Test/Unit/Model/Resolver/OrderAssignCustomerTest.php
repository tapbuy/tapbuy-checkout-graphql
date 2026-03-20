<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\CustomerAssignment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;
use Tapbuy\CheckoutGraphql\Model\Resolver\OrderAssignCustomer;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;

class OrderAssignCustomerTest extends TestCase
{
    private OrderAssignCustomer $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private OrderDataFormatterInterface&MockObject $orderFormatter;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private CustomerAssignment&MockObject $customerAssignment;
    private OrderLocatorInterface&MockObject $orderLocator;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->orderFormatter = $this->createMock(OrderDataFormatterInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerAssignment = $this->createMock(CustomerAssignment::class);
        $this->orderLocator = $this->createMock(OrderLocatorInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new OrderAssignCustomer(
            $this->tokenAuthorization,
            $this->orderFormatter,
            $this->customerRepository,
            $this->customerAssignment,
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

    public function testThrowsWhenOrderIdMissing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, ['customer_id' => 1]);
    }

    public function testThrowsWhenCustomerIdMissing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, ['order_id' => '100']);
    }

    public function testThrowsWhenOrderNotFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->orderLocator->method('getByIdentifier')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(GraphQlNoSuchEntityException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '999',
            'customer_id' => 1,
        ]);
    }

    public function testThrowsWhenCustomerNotFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn(true);
        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $this->customerRepository->method('getById')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(GraphQlNoSuchEntityException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 999,
        ]);
    }

    public function testReturnsSuccessWhenOrderAlreadyAssignedToSameCustomer(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn(false);
        $order->method('getCustomerId')->willReturn(42);
        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->orderFormatter->method('format')->willReturn(['number' => '100']);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 42,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testThrowsWhenOrderAlreadyAssignedToDifferentCustomer(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn(false);
        $order->method('getCustomerId')->willReturn(99);
        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('already assigned');

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 42,
        ]);
    }

    public function testThrowsWhenEmailsDontMatch(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn(true);
        $order->method('getCustomerEmail')->willReturn('order@example.com');
        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('different@example.com');
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('does not match');

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 42,
        ]);
    }

    public function testSuccessfulAssignment(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerIsGuest')->willReturn(true);
        $order->method('getCustomerEmail')->willReturn('test@example.com');
        // After assignment, getCustomerId returns the assigned customer
        $order->method('getCustomerId')->willReturn(42);
        $this->orderLocator->method('getByIdentifier')->willReturn($order);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('test@example.com');
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->customerAssignment->expects($this->once())->method('execute')->with($order, $customer);
        $this->orderFormatter->method('format')->willReturn(['number' => '100']);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 42,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testThrowsOnInvalidIdentifierType(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Invalid order identifier type');

        $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'order_id' => '100',
            'customer_id' => 42,
            'order_identifier_type' => 'invalid_type',
        ]);
    }
}
