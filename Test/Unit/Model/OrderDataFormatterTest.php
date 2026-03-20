<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderExtensionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\ShippingAssignmentInterface;
use Magento\Sales\Api\Data\ShippingInterface;
use Magento\Sales\Model\Order;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\OrderDataFormatter;

class OrderDataFormatterTest extends TestCase
{
    private OrderDataFormatter $formatter;
    private OrderFormatter&MockObject $orderFormatter;

    protected function setUp(): void
    {
        $this->orderFormatter = $this->createMock(OrderFormatter::class);
        $this->formatter = new OrderDataFormatter($this->orderFormatter);
    }

    public function testFormatReturnsBaseFormatterData(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('processing');

        $this->orderFormatter->method('format')->willReturn(['number' => '100000001']);

        $result = $this->formatter->format($order);

        $this->assertSame('100000001', $result['number']);
        $this->assertSame('processing', $result['tapbuy_state']);
        $this->assertSame($order, $result['model']);
    }

    public function testFormatAttachesShippingAddressModel(): void
    {
        $shippingAddress = $this->createMock(OrderAddressInterface::class);
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([]);

        $result = $this->formatter->format($order);

        $this->assertSame($shippingAddress, $result['shipping_address']['model']);
    }

    public function testFormatAttachesBillingAddressModel(): void
    {
        $billingAddress = $this->createMock(OrderAddressInterface::class);
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([]);

        $result = $this->formatter->format($order);

        $this->assertSame($billingAddress, $result['billing_address']['model']);
    }

    public function testFormatAttachesPaymentModel(): void
    {
        $payment = $this->createMock(OrderPaymentInterface::class);
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([]);

        $result = $this->formatter->format($order);

        $this->assertSame($payment, $result['payment_methods'][0]['model']);
    }

    public function testFormatShippingAssignmentsFromExtensionAttributes(): void
    {
        $address = $this->getMockBuilder(OrderAddressInterface::class)
            ->addMethods(['getData'])
            ->getMockForAbstractClass();
        $address->method('getStreet')->willReturn(['123 Main St', 'Apt 4']);
        $address->method('getCountryId')->willReturn('US');
        $address->method('getData')->willReturn(['city' => 'NY']);

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getMethod')->willReturn('flatrate_flatrate');
        $shipping->method('getAddress')->willReturn($address);

        $item = $this->getMockBuilder(\Magento\Sales\Api\Data\OrderItemInterface::class)
            ->getMockForAbstractClass();
        $item->method('getItemId')->willReturn(1);
        $item->method('getProductId')->willReturn(42);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);
        $assignment->method('getItems')->willReturn([$item]);

        $extensionAttributes = $this->getMockBuilder(OrderExtensionInterface::class)
            ->addMethods(['getShippingAssignments'])
            ->getMockForAbstractClass();
        $extensionAttributes->method('getShippingAssignments')->willReturn([$assignment]);

        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn($extensionAttributes);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([]);

        $result = $this->formatter->format($order);

        $this->assertCount(1, $result['tapbuy_shipping_assignments']);
        $this->assertSame('flatrate_flatrate', $result['tapbuy_shipping_assignments'][0]['method']);
        $this->assertCount(1, $result['tapbuy_shipping_assignments'][0]['items']);
        $this->assertSame(1, $result['tapbuy_shipping_assignments'][0]['items'][0]['item_id']);
    }

    public function testFormatShippingAssignmentsEmptyWhenNoExtensionAttributes(): void
    {
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([]);

        $result = $this->formatter->format($order);

        $this->assertSame([], $result['tapbuy_shipping_assignments']);
    }

    public function testFormatPreservesExistingShippingAddressData(): void
    {
        $shippingAddress = $this->createMock(OrderAddressInterface::class);
        $order = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getExtensionAttributes')->willReturn(null);
        $order->method('getState')->willReturn('new');

        $this->orderFormatter->method('format')->willReturn([
            'shipping_address' => ['city' => 'Paris']
        ]);

        $result = $this->formatter->format($order);

        $this->assertSame('Paris', $result['shipping_address']['city']);
        $this->assertSame($shippingAddress, $result['shipping_address']['model']);
    }
}
