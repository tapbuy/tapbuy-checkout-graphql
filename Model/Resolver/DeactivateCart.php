<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;
use Tapbuy\CheckoutGraphql\Helper\CartHelper;

class DeactivateCart implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteFactory $quoteFactory
     * @param CartHelper $cartHelper
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        CartRepositoryInterface $cartRepository,
        QuoteFactory $quoteFactory,
        CartHelper $cartHelper
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->cartRepository = $cartRepository;
        $this->quoteFactory = $quoteFactory;
        $this->cartHelper = $cartHelper;
    }

    /**
     * Deactivate a cart
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_edit');

        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Cart ID is required'));
        }

        $cartId = $args['cart_id'];

        // Handle masked cart ID conversion first
        $realCartId = $this->cartHelper->getRealCartId($cartId);

        // Deactivate the cart
        $cart = $this->deactivateCart($realCartId);

        return [
            'cart' => $cart
        ];
    }

    /**
     * Deactivate the cart
     *
     * @param string $cartId
     * @return array
     */
    private function deactivateCart(string $cartId): array
    {
        try {
            $quote = $this->quoteFactory->create()->load($cartId, 'entity_id');
        } catch (\Exception $e) {
            return [
                'model' => null,
                'id' => null,
                'is_active' => false
            ];
        }

        if ($quote->getId()) {
            $quote->setIsActive(0);
            $this->cartRepository->save($quote);
        }

        // Return basic cart data for the response
        return [
            'model' => $quote,
            'id' => $quote->getId(),
            'is_active' => $quote->getIsActive()
        ];
    }
}
