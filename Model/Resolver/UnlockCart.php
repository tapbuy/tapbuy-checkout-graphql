<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class UnlockCart implements ResolverInterface
{
    /**
     * Required ACL resource for unlocking carts
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_CART_UNLOCK;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

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
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param CartResolverInterface $cartResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        OrderCollectionFactory $orderCollectionFactory,
        CartRepositoryInterface $cartRepository,
        CartResolverInterface $cartResolver,
        LoggerInterface $logger
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->cartResolver = $cartResolver;
        $this->logger = $logger;
    }

    /**
     * Unlock a cart by updating the associated order status and reactivating the quote
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

        $unlockReason = $args['unlock_reason'] ?? null;

        // Resolve cart ID (handles masked ID conversion)
        $cartId = $this->cartResolver->resolveCartId($args['cart_id']);

        $cancelOrders = true;

        if (!$unlockReason === 'update_payment_details') {
            // Update order status if order exists
            $this->updateOrderStatus((string) $cartId, $unlockReason);
            $cancelOrders = false;
        }

        // Reactivate the cart
        $cart = $this->reactivateCart((string) $cartId, $cancelOrders);

        return [
            'cart' => $cart
        ];
    }

    /**
     * Update order status based on unlock reason
     *
     * @param string $quoteId
     * @param string|null $unlockReason
     * @return void
     */
    private function updateOrderStatus(string $quoteId, ?string $unlockReason): void
    {
        // Get all active orders for this quote
        $orders = $this->getActiveOrdersByQuoteId($quoteId);

        foreach ($orders as $order) {
            // Definition of the unlock reason
            $msgTxt = "Tapbuy Unlock: ";
            if ($unlockReason === 'cancel') {
                $configDataKey = "order_status_payment_canceled";
                $msgTxt .= "payment canceled";
            } else {
                $configDataKey = "order_status_payment_refused";
                $msgTxt .= "payment refused";
            }

            // Set message to order
            $message = $order->addStatusHistoryComment($msgTxt);
            $message->setIsCustomerNotified(null);

            try {
                $paymentMethodInstance = $order->getPayment()->getMethodInstance();
                $orderStatus = $paymentMethodInstance->getConfigData($configDataKey);
            } catch (\Exception $e) {
                $this->logger->warning(
                    'Checkout-GraphQL: Failed to get order status from payment method, using canceled',
                    [
                        'order_id' => $order->getIncrementId(),
                        'config_key' => $configDataKey,
                        'error' => $e->getMessage(),
                    ]
                );
                $orderStatus = 'canceled';
            }

            // Ensure the order is canceled, release stock, etc.
            $order->cancel();
            // Set the status and save the order
            $order->setStatus($orderStatus)->save();

            $this->logger->debug('Checkout-GraphQL: Updated order status during unlock', [
                'order_id' => $order->getIncrementId(),
                'new_status' => $orderStatus,
                'unlock_reason' => $unlockReason,
            ]);
        }
    }

    /**
     * Get all active orders for a given quote ID
     *
     * @param string $quoteId
     * @return Order[]
     */
    private function getActiveOrdersByQuoteId(string $quoteId): array
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('quote_id', $quoteId);
        // Filter out canceled and complete orders
        $orderCollection->addFieldToFilter('state', [
            'nin' => [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED]
        ]);
        $orderCollection->setOrder('created_at', 'DESC');

        return $orderCollection->getItems();
    }

    /**
     * Reactivate the cart.
     *
     * With option to cancel associated orders.
     *
     * @param string $cartId The numeric cart ID
     * @param bool $cancelOrders
     * @return array
     */
    private function reactivateCart(string $cartId, bool $cancelOrders): array
    {
        try {
            $quote = $this->cartRepository->get((int) $cartId);
        } catch (\Exception $e) {
            $this->logger->logException('Error loading cart for reactivation', $e, [
                'cart_id' => $cartId,
            ]);
            return [
                'model' => null,
                'id' => null,
                'is_active' => false
            ];
        }

        if ($quote->getId()) {
            $quote->setIsActive(true);
            if ($cancelOrders) {
                $quote->setReservedOrderId(null);
            }
            $this->cartRepository->save($quote);

            $this->logger->debug('Checkout-GraphQL: Reactivated cart', [
                'cart_id' => $quote->getId(),
                'cancel_orders' => $cancelOrders,
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
