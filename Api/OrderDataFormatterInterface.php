<?php

declare(strict_types=1);

/**
 * Tapbuy Order Data Formatter Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_CheckoutGraphql
 */

namespace Tapbuy\CheckoutGraphql\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface OrderDataFormatterInterface
 *
 * Provides order data formatting for GraphQL responses.
 */
interface OrderDataFormatterInterface
{
    /**
     * Format order for GraphQL response with additional Tapbuy data.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function format(OrderInterface $order): array;
}
