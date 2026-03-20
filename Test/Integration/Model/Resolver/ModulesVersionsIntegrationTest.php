<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphQl\Test\Integration\Model\Resolver;

use PHPUnit\Framework\TestCase;

/**
 * Integration test stub for ModulesVersions GraphQL resolver.
 * Requires a fully bootstrapped Magento environment.
 */
class ModulesVersionsIntegrationTest extends TestCase
{
    public function testReturnsInstalledModuleVersions(): void
    {
        $this->markTestIncomplete(
            'Integration test requires Magento bootstrap with module registry.'
        );
    }
}
