<?php

/**
 * Tapbuy Cart Helper Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_CheckoutGraphql
 */

namespace Tapbuy\CheckoutGraphql\Api;

/**
 * Interface CartHelperInterface
 *
 * Provides cart helper utilities for Tapbuy checkout operations.
 */
interface CartHelperInterface
{
    /**
     * Get real cart ID from masked cart ID if needed
     *
     * @param string $cartId
     * @return string
     */
    public function getRealCartId(string $cartId): string;
}
