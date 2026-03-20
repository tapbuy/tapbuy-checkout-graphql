<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphQl\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for CustomerSearch GraphQL resolver.
 * Requires a fully bootstrapped Magento environment with customer fixtures.
 */
class CustomerSearchIntegrationTest extends TestCase
{
    public function testSearchByEmailReturnsCustomer(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and customer fixtures.'
        );
    }
}
