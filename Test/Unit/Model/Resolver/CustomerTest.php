<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\Customer;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class CustomerTest extends TestCase
{
    private Customer $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new Customer(
            $this->tokenAuthorization,
            $this->config
        );
    }

    public function testReturnsNullWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoModel(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, []);

        $this->assertNull($result);
    }

    public function testReturnsCustomerIdForTapbuyCustomerIdField(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_customer_id');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $customer]
        );

        $this->assertSame(42, $result);
    }

    public function testReturnsNullForUnknownField(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('unknown_field');

        $customer = $this->createMock(CustomerInterface::class);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $customer]
        );

        $this->assertNull($result);
    }

    public function testReturnsNullWhenCustomerIdIsNull(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_customer_id');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(null);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $customer]
        );

        $this->assertNull($result);
    }

    public function testCastsStringCustomerIdToInt(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_customer_id');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('42');

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $customer]
        );

        $this->assertSame(42, $result);
    }
}
