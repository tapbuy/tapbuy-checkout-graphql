<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\UnlockCart;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class UnlockCartTest extends TestCase
{
    private UnlockCart $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private OrderCollectionFactory&MockObject $orderCollectionFactory;
    private CartRepositoryInterface&MockObject $cartRepository;
    private CartResolverInterface&MockObject $cartResolver;
    private ConfigInterface&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartResolver = $this->createMock(CartResolverInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new UnlockCart(
            $this->tokenAuthorization,
            $this->orderCollectionFactory,
            $this->cartRepository,
            $this->cartResolver,
            $this->config,
            $this->logger
        );
    }

    public function testThrowsWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info);
    }

    public function testThrowsWhenCartIdEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, ['cart_id' => '']);
    }

    public function testUnlockWithUpdatePaymentDetailsSkipsOrderCancellation(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->cartResolver->method('resolveCartId')->willReturn(42);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(true);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);

        // With 'update_payment_details', orders should NOT be canceled but reservedOrderId should NOT be cleared
        $quote->expects($this->never())->method('setReservedOrderId');

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'masked_id', 'unlock_reason' => 'update_payment_details']
        );

        $this->assertSame(42, $result['cart']['id']);
    }

    public function testUnlockWithCancelReasonCancelsOrders(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->cartResolver->method('resolveCartId')->willReturn(42);

        // Mock order collection returning an order that can be canceled
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['canCancel', 'cancel', 'save', 'getIncrementId', 'getState', 'getStatus', 'addStatusHistoryComment', 'isPaymentReview', 'isFraudDetected'])
            ->getMock();
        $order->method('canCancel')->willReturn(true);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getState')->willReturn('new');
        $order->method('getStatus')->willReturn('pending');
        $order->expects($this->once())->method('cancel');

        $historyComment = $this->createMock(\Magento\Sales\Model\Order\Status\History::class);
        $historyComment->method('setIsCustomerNotified')->willReturn($historyComment);
        $order->method('addStatusHistoryComment')->willReturn($historyComment);

        $collection = $this->createMock(OrderCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn([$order]);
        $this->orderCollectionFactory->method('create')->willReturn($collection);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(true);
        $this->cartRepository->method('get')->with(42)->willReturn($quote);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'masked_id', 'unlock_reason' => 'cancel']
        );

        $this->assertArrayHasKey('cart', $result);
    }

    public function testThrowsNotFoundWhenCartMissing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->cartResolver->method('resolveCartId')->willReturn(999);

        // Mock order collection (empty — no orders to cancel)
        $collection = $this->createMock(OrderCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn([]);
        $this->orderCollectionFactory->method('create')->willReturn($collection);

        $this->cartRepository->method('get')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(GraphQlNoSuchEntityException::class);

        $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'missing', 'unlock_reason' => 'cancel']
        );
    }
}
