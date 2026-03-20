<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\DeactivateCart;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class DeactivateCartTest extends TestCase
{
    private DeactivateCart $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
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
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartResolver = $this->createMock(CartResolverInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new DeactivateCart(
            $this->tokenAuthorization,
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

    public function testDeactivatesCartSuccessfully(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);
        $quote->method('getIsActive')->willReturn(false);

        $this->cartResolver->method('resolveAndLoadQuote')->with('abc123')->willReturn($quote);
        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'abc123']
        );

        $this->assertSame(42, $result['cart']['id']);
    }

    public function testThrowsNotFoundWhenCartMissing(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->cartResolver->method('resolveAndLoadQuote')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(GraphQlNoSuchEntityException::class);

        $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'missing']
        );
    }

    public function testThrowsInputExceptionOnSaveFailure(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(42);

        $this->cartResolver->method('resolveAndLoadQuote')->willReturn($quote);
        $this->cartRepository->method('save')
            ->willThrowException(new LocalizedException(__('Save failed')));

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['cart_id' => 'abc123']
        );
    }
}
