<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\OrderAddress;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class OrderAddressTest extends TestCase
{
    private OrderAddress $resolver;
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

        $this->resolver = new OrderAddress(
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

    public function testReturnsEntityIdViaGetEntityId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_entity_id');

        $address = new class {
            public function getEntityId(): int
            {
                return 99;
            }
        };

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $address]
        );

        $this->assertSame(99, $result);
    }

    public function testReturnsEntityIdViaGetIdFallback(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_entity_id');

        $address = new class {
            public function getId(): int
            {
                return 55;
            }
        };

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $address]
        );

        $this->assertSame(55, $result);
    }

    public function testReturnsNullForUnknownField(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('unknown_field');

        $address = new class {
            public function getEntityId(): int
            {
                return 99;
            }
        };

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $address]
        );

        $this->assertNull($result);
    }
}
