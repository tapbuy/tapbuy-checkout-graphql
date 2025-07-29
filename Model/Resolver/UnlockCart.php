<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;
use Tapbuy\CheckoutGraphql\Helper\CartHelper;

class UnlockCart implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

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
     * @param OrderFactory $orderFactory
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteFactory $quoteFactory
     * @param CartHelper $cartHelper
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        OrderFactory $orderFactory,
        CartRepositoryInterface $cartRepository,
        QuoteFactory $quoteFactory,
        CartHelper $cartHelper
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderFactory = $orderFactory;
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
        $order = $this->orderFactory->create();
        $order->getResource()->load($order, $quoteId, 'quote_id');

        if ($order->getId()) {
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

            // Set the status and save the order
            $order->setStatus($orderStatus)->save();
        }
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
