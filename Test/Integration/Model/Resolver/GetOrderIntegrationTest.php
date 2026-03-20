<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphQl\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for GetOrder GraphQL resolver.
 * Requires a fully bootstrapped Magento environment with test fixtures.
 */
class GetOrderIntegrationTest extends TestCase
{
    public function testGetOrderReturnsOrderData(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and order fixtures.'
        );
    }

    public function testGetOrderRequiresAdminToken(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and auth token fixtures.'
        );
    }
}
