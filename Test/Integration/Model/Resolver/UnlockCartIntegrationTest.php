<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphQl\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for UnlockCart GraphQL resolver.
 */
class UnlockCartIntegrationTest extends TestCase
{
    public function testUnlockCartActivatesQuote(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap and quote fixtures.'
        );
    }
}
