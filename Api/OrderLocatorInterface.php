<?php

/**
 * Tapbuy Order Locator Interface
 *
 * @category  Tapbuy
 * @package   Tapbuy_CheckoutGraphql
 */

namespace Tapbuy\CheckoutGraphql\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface OrderLocatorInterface
 *
 * Provides order lookup functionality for Tapbuy.
 */
interface OrderLocatorInterface
{
    public const IDENTIFIER_TYPE_AUTO = 'auto';
    public const IDENTIFIER_TYPE_ENTITY_ID = 'entity_id';
    public const IDENTIFIER_TYPE_INCREMENT_ID = 'increment_id';

    /**
     * Retrieve order by ID or increment ID.
     *
     * @param string $identifier
     * @param string $identifierType
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByIdentifier(string $identifier, string $identifierType = self::IDENTIFIER_TYPE_AUTO): OrderInterface;
}
