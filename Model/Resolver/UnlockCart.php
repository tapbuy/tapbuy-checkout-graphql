<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\Cart\CartResolverInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class UnlockCart implements ResolverInterface
{
    /**
     * Required ACL resource for unlocking carts
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_CART_UNLOCK;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param CartResolverInterface $cartResolver
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TokenAuthorizationInterface $tokenAuthorization,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartResolverInterface $cartResolver,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
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
        if (!$this->config->isEnabled()) {
            throw new GraphQlInputException(__('Tapbuy is disabled.'));
        }

        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Cart ID is required'));
        }

        $unlockReason = $args['unlock_reason'] ?? null;

        // Resolve cart ID (handles masked ID conversion)
        $cartId = $this->cartResolver->resolveCartId($args['cart_id']);

        $cancelOrders = true;

        if ($unlockReason !== 'update_payment_details') {
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
            $msgTxt = 'Tapbuy Unlock: ' . ($unlockReason === 'cancel' ? 'payment canceled' : 'payment refused');

            $this->cancelOrder($order, $msgTxt);

            try {
                $order->save();
            } catch (LocalizedException $e) {
                $this->logger->logException('Checkout-GraphQL: Failed to save order during unlock', $e, [
                    'order_id' => $order->getIncrementId(),
                ]);
                continue;
            }

            $this->logger->debug('Checkout-GraphQL: Updated order status during unlock', [
                'order_id' => $order->getIncrementId(),
                'new_status' => $order->getStatus(),
                'unlock_reason' => $unlockReason,
            ]);
        }
    }

    /**
     * Attempt to cancel a single order, choosing the appropriate cancellation path.
     *
     * Uses cancel() for regular orders, registerCancellation() for payment-review/fraud orders,
     * and logs a warning when the order state does not allow cancellation at all.
     *
     * @param Order $order
     * @param string $msgTxt Status history comment to attach.
     * @return void
     */
    private function cancelOrder(Order $order, string $msgTxt): void
    {
        if ($order->canCancel()) {
            // Standard cancellation: releases stock and transitions state via cancel()
            $order->addStatusHistoryComment($msgTxt)->setIsCustomerNotified(null);
            $order->cancel();
            return;
        }

        if ($order->isPaymentReview() || $order->isFraudDetected()) {
            // payment_review/fraud orders can't use cancel() — use registerCancellation() directly
            try {
                $order->getPayment()->cancel();
            } catch (LocalizedException $e) {
                $this->logger->warning(
                    'Checkout-GraphQL: Failed to cancel payment during unlock (Magento exception)',
                    [
                        'order_id' => $order->getIncrementId(),
                        'state' => $order->getState(),
                        'status' => $order->getStatus(),
                        'error' => $e->getMessage(),
                    ]
                );
            } catch (\RuntimeException $e) {
                $this->logger->warning(
                    'Checkout-GraphQL: Failed to cancel payment during unlock (gateway exception)',
                    [
                        'order_id' => $order->getIncrementId(),
                        'state' => $order->getState(),
                        'status' => $order->getStatus(),
                        'error' => $e->getMessage(),
                    ]
                );
            }
            // registerCancellation() adds its own status history comment
            $order->registerCancellation($msgTxt);
            return;
        }

        $order->addStatusHistoryComment(
            'Tapbuy Unlock: cancellation attempted but not possible (state: ' . $order->getState() . ')'
        )->setIsCustomerNotified(null);
        $this->logger->warning(
            'Checkout-GraphQL: Order could not be canceled during unlock',
            [
                'order_id' => $order->getIncrementId(),
                'state' => $order->getState(),
                'status' => $order->getStatus(),
            ]
        );
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
        } catch (NoSuchEntityException $e) {
            $this->logger->logException('Checkout-GraphQL: Cart not found for reactivation', $e, [
                'cart_id' => $cartId,
            ]);
            throw new GraphQlNoSuchEntityException(__('Cart not found: %1', $cartId), $e);
        } catch (LocalizedException $e) {
            $this->logger->logException('Checkout-GraphQL: Error loading cart for reactivation', $e, [
                'cart_id' => $cartId,
            ]);
            throw new GraphQlInputException(__('Could not reactivate the cart.'), $e);
        }

        if ($quote->getId()) {
            $quote->setIsActive(true);
            if ($cancelOrders) {
                $quote->setReservedOrderId(null);
            }
            try {
                $this->cartRepository->save($quote);
            } catch (LocalizedException $e) {
                $this->logger->logException('Checkout-GraphQL: Failed to save reactivated cart', $e, [
                    'cart_id' => $cartId,
                ]);
                throw new GraphQlInputException(__('Could not reactivate the cart.'), $e);
            }

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
