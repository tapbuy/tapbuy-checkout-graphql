<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphQl\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for DeactivateCart GraphQL resolver.
 */
class DeactivateCartIntegrationTest extends TestCase
{
    public function testDeactivateCartSetsIsActiveToFalse(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and quote fixtures.'
        );
    }
}
