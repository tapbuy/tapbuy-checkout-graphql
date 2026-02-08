<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class DeactivateCart implements ResolverInterface
{
    /**
     * Required ACL resource for deactivating carts
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_CART_DEACTIVATE;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartResolverInterface
     */
    private $cartResolver;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param CartRepositoryInterface $cartRepository
     * @param CartResolverInterface $cartResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        CartRepositoryInterface $cartRepository,
        CartResolverInterface $cartResolver,
        LoggerInterface $logger
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->cartRepository = $cartRepository;
        $this->cartResolver = $cartResolver;
        $this->logger = $logger;
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
        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Cart ID is required'));
        }

        $cartId = $args['cart_id'];

        // Deactivate the cart (handles masked ID conversion internally)
        $cart = $this->deactivateCart($cartId);

        return [
            'cart' => $cart
        ];
    }

    /**
     * Deactivate the cart
     *
     * @param string $cartId The cart ID (masked or numeric)
     * @return array
     */
    private function deactivateCart(string $cartId): array
    {
        try {
            $quote = $this->cartResolver->resolveAndLoadQuote($cartId);
        } catch (\Exception $e) {
            $this->logger->logException('Error loading cart for deactivation', $e, [
                'cart_id' => $cartId,
            ]);
            return [
                'model' => null,
                'id' => null,
                'is_active' => false
            ];
        }

        if ($quote->getId()) {
            $quote->setIsActive(false);
            $this->cartRepository->save($quote);

            $this->logger->debug('Checkout-GraphQL: Deactivated cart', [
                'cart_id' => $quote->getId(),
            ]);
        }

        // Return basic cart data for the response
        return [
            'model' => $quote,
            'id' => $quote->getId(),
            'is_active' => $quote->getIsActive()
        ];
    }
}
