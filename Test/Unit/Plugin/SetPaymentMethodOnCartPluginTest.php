<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\QuoteGraphQl\Model\Resolver\SetPaymentMethodOnCart;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Plugin\SetPaymentMethodOnCartPlugin;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class SetPaymentMethodOnCartPluginTest extends TestCase
{
    private SetPaymentMethodOnCartPlugin $plugin;
    private CartRepositoryInterface&MockObject $cartRepository;
    private CartResolverInterface&MockObject $cartResolver;
    private SerializerInterface&MockObject $serializer;
    private LoggerInterface&MockObject $logger;
    private TapbuyRequestDetectorInterface&MockObject $requestDetector;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartResolver = $this->createMock(CartResolverInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestDetector = $this->createMock(TapbuyRequestDetectorInterface::class);

        $this->plugin = new SetPaymentMethodOnCartPlugin(
            $this->cartRepository,
            $this->cartResolver,
            $this->serializer,
            $this->logger,
            $this->requestDetector
        );
    }

    public function testReturnsResultUnchangedWhenNotTapbuyCall(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(false);

        $result = ['cart' => []];
        $actual = $this->plugin->afterResolve(
            $this->createMock(SetPaymentMethodOnCart::class),
            $result,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class)
        );

        $this->assertSame($result, $actual);
    }

    public function testSetsAdditionalInformationWhenTapbuyCall(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);

        $payment = $this->createMock(Payment::class);
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        $this->cartResolver->method('resolveAndLoadQuote')->with('cart123')->willReturn($quote);
        $this->serializer->method('serialize')->willReturn('{"key":"value"}');

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(TapbuyConstants::PAYMENT_ADDITIONAL_INFO_KEY, '{"key":"value"}');

        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        $args = [
            'input' => [
                'cart_id' => 'cart123',
                'payment_method' => [
                    'tapbuy_additional_information' => ['key' => 'value'],
                ],
            ],
        ];

        $result = ['cart' => []];
        $actual = $this->plugin->afterResolve(
            $this->createMock(SetPaymentMethodOnCart::class),
            $result,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            $args
        );

        $this->assertSame($result, $actual);
    }

    public function testSkipsWhenNoAdditionalInformation(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);

        $this->cartResolver->expects($this->never())->method('resolveAndLoadQuote');

        $args = [
            'input' => [
                'cart_id' => 'cart123',
                'payment_method' => [
                    'code' => 'adyen_cc',
                ],
            ],
        ];

        $result = ['cart' => []];
        $this->plugin->afterResolve(
            $this->createMock(SetPaymentMethodOnCart::class),
            $result,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            $args
        );
    }

    public function testLogsAndSwallowsExceptionOnCartNotFound(): void
    {
        $this->requestDetector->method('isTapbuyCall')->willReturn(true);

        $this->cartResolver->method('resolveAndLoadQuote')
            ->willThrowException(new NoSuchEntityException());

        $this->logger->expects($this->once())->method('logException');

        $args = [
            'input' => [
                'cart_id' => 'missing',
                'payment_method' => [
                    'tapbuy_additional_information' => ['key' => 'value'],
                ],
            ],
        ];

        $result = ['cart' => []];
        $actual = $this->plugin->afterResolve(
            $this->createMock(SetPaymentMethodOnCart::class),
            $result,
            $this->createMock(Field::class),
            null,
            $this->createMock(ResolveInfo::class),
            null,
            $args
        );

        // Original result should be returned despite exception
        $this->assertSame($result, $actual);
    }
}
