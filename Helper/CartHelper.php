<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Helper;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Tapbuy\CheckoutGraphql\Api\CartHelperInterface;

class CartHelper implements CartHelperInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Get real cart ID from masked cart ID if needed
     *
     * @param string $cartId
     * @return string
     */
    public function getRealCartId(string $cartId): string
    {
        if (!is_numeric($cartId)) {
            $maskedId = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            return $maskedId->getQuoteId();
        }
        return $cartId;
    }
}
