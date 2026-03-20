<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for OrderAssignCustomer GraphQL resolver.
 */
class OrderAssignCustomerIntegrationTest extends TestCase
{
    public function testAssignCustomerToGuestOrder(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap with order and customer fixtures.'
        );
    }
}
