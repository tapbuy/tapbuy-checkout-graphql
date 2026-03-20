<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\CustomerSearch;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class CustomerSearchTest extends TestCase
{
    private CustomerSearch $resolver;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private ExtractCustomerData&MockObject $extractCustomerData;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private ConfigInterface&MockObject $config;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->extractCustomerData = $this->createMock(ExtractCustomerData::class);
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new CustomerSearch(
            $this->customerRepository,
            $this->extractCustomerData,
            $this->tokenAuthorization,
            $this->config
        );
    }

    public function testThrowsWhenDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info);
    }

    public function testThrowsWhenEmailEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->expectException(GraphQlInputException::class);

        $this->resolver->resolve($this->field, $this->context, $this->info, null, ['email' => '']);
    }

    public function testReturnsCustomerDataWhenFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('get')->with('test@example.com')->willReturn($customer);
        $this->extractCustomerData->method('execute')->with($customer)->willReturn(['id' => 1, 'email' => 'test@example.com']);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['email' => 'test@example.com']
        );

        $this->assertSame(['id' => 1, 'email' => 'test@example.com'], $result);
    }

    public function testReturnsNullWhenCustomerNotFound(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException());

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            null,
            ['email' => 'nonexistent@example.com']
        );

        $this->assertNull($result);
    }
}
