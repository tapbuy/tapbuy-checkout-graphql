<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Order;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;
use Tapbuy\CheckoutGraphql\Helper\CartHelper;

class UnlockCart implements ResolverInterface
{
    /**
     * @var TokenAuthorization
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
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var CartHelper
     */
    private $cartHelper;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteFactory $quoteFactory
     * @param CartHelper $cartHelper
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        OrderCollectionFactory $orderCollectionFactory,
        CartRepositoryInterface $cartRepository,
        QuoteFactory $quoteFactory,
        CartHelper $cartHelper
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->quoteFactory = $quoteFactory;
        $this->cartHelper = $cartHelper;
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
        $this->tokenAuthorization->authorize('Magento_Sales::actions_edit');

        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Cart ID is required'));
        }

        $unlockReason = $args['unlock_reason'] ?? null;

        // Handle masked cart ID conversion first
        $cartId = $this->cartHelper->getRealCartId($args['cart_id']);

        // Update order status if order exists
        $this->updateOrderStatus($cartId, $unlockReason);

        // Reactivate the cart
        $cart = $this->reactivateCart($cartId);

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
        // Get the most recent order for this quote
        $order = $this->getLatestOrderByQuoteId($quoteId);

        if ($order && $order->getId()) {
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
                $orderStatus = 'canceled';
            }

            // Ensure the order is canceled, release stock, etc.
            $order->cancel();
            // Set the status and save the order
            $order->setStatus($orderStatus)->save();
        }
    }

    /**
     * Get the latest active order for a given quote ID
     *
     * @param string $quoteId
     * @return Order|null
     */
    private function getLatestOrderByQuoteId(string $quoteId): ?Order
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('quote_id', $quoteId);
        // Filter out canceled and complete orders
        $orderCollection->addFieldToFilter('state', [
            'nin' => [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED]
        ]);
        $orderCollection->setOrder('created_at', 'DESC');
        $orderCollection->setPageSize(1);

        $order = $orderCollection->getFirstItem();
        return $order->getId() ? $order : null;
    }

    /**
     * Reactivate the cart
     *
     * @param string $cartId
     * @return array
     */
    private function reactivateCart(string $cartId): array
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
            $quote->setIsActive(1)->setReservedOrderId(null);
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
