<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\OrderPaymentMethod;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class OrderPaymentMethodTest extends TestCase
{
    private OrderPaymentMethod $resolver;
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

        $this->resolver = new OrderPaymentMethod(
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

    public function testReturnsAdditionalInformation(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_additional_information');

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn([
            'guestEmail' => 'guest@example.com',
            'cc_type' => 'visa',
            'method_title' => 'Adyen CC',
            '3dActive' => true,
            'resultCode' => 'Authorised',
            'pspReference' => 'PSP123',
            'additionalData' => [
                'issuerCountry' => 'FR',
                'cardBin' => '411111',
                'cardHolderName' => 'John Doe',
                'cardSummary' => '1234',
                'paymentMethod' => 'visa',
            ],
        ]);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $payment]
        );

        $this->assertSame('guest@example.com', $result['guest_email']);
        $this->assertSame('visa', $result['cc_type']);
        $this->assertSame('PSP123', $result['psp_reference']);
        $this->assertSame('FR', $result['additional_data']['issuer_country']);
        $this->assertSame('411111', $result['additional_data']['card_bin']);
    }

    public function testReturnsAmountOrdered(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_amount_ordered');

        $payment = $this->createMock(Payment::class);
        $payment->method('getAmountOrdered')->willReturn(99.99);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $payment]
        );

        $this->assertSame(99.99, $result);
    }

    public function testReturnsNullForUnknownField(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('unknown');

        $payment = $this->createMock(Payment::class);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $payment]
        );

        $this->assertNull($result);
    }

    public function testAdditionalInformationWithNullValues(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->field->method('getName')->willReturn('tapbuy_additional_information');

        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn([]);

        $result = $this->resolver->resolve(
            $this->field,
            $this->context,
            $this->info,
            ['model' => $payment]
        );

        $this->assertNull($result['guest_email']);
        $this->assertNull($result['cc_type']);
        $this->assertNull($result['additional_data']['issuer_country']);
    }
}
