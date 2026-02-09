<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;

class OrderDataFormatter implements OrderDataFormatterInterface
{
    /**
     * @var OrderFormatter
     */
    private $orderFormatter;

    /**
     * @param OrderFormatter $orderFormatter
     */
    public function __construct(OrderFormatter $orderFormatter)
    {
        $this->orderFormatter = $orderFormatter;
    }

    /**
     * Format order for GraphQL response with additional Tapbuy data.
     *
     * @param OrderInterface $order
     * @return array
     */
    public function format(OrderInterface $order): array
    {
        $orderData = $this->orderFormatter->format($order);

        $orderData = $this->formatShippingAddress($orderData, $order);
        $orderData = $this->formatBillingAddress($orderData, $order);
        $orderData = $this->formatPaymentMethods($orderData, $order);
        $orderData['tapbuy_shipping_assignments'] = $this->formatShippingAssignments($order);
        $orderData = $this->formatTapbuyMetadata($orderData, $order);

        return $orderData;
    }

    /**
     * Format shipping address data with model attachment.
     *
     * @param array $orderData
     * @param OrderInterface $order
     * @return array
     */
    private function formatShippingAddress(array $orderData, OrderInterface $order): array
    {
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            if (!isset($orderData['shipping_address']) || !is_array($orderData['shipping_address'])) {
                $orderData['shipping_address'] = [];
            }
            $orderData['shipping_address']['model'] = $shippingAddress;
        }

        return $orderData;
    }

    /**
     * Format billing address data with model attachment.
     *
     * @param array $orderData
     * @param OrderInterface $order
     * @return array
     */
    private function formatBillingAddress(array $orderData, OrderInterface $order): array
    {
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            if (!isset($orderData['billing_address']) || !is_array($orderData['billing_address'])) {
                $orderData['billing_address'] = [];
            }
            $orderData['billing_address']['model'] = $billingAddress;
        }

        return $orderData;
    }

    /**
     * Format payment methods data with model attachment.
     *
     * @param array $orderData
     * @param OrderInterface $order
     * @return array
     */
    private function formatPaymentMethods(array $orderData, OrderInterface $order): array
    {
        $payment = $order->getPayment();
        if ($payment) {
            if (!isset($orderData['payment_methods']) || !is_array($orderData['payment_methods'])) {
                $orderData['payment_methods'] = [];
            }

            if (!isset($orderData['payment_methods'][0]) || !is_array($orderData['payment_methods'][0])) {
                $orderData['payment_methods'][0] = [];
            }

            $orderData['payment_methods'][0]['model'] = $payment;
        }

        return $orderData;
    }

    /**
     * Format shipping assignments from order extension attributes.
     *
     * @param OrderInterface $order
     * @return array
     */
    private function formatShippingAssignments(OrderInterface $order): array
    {
        $shippingAssignments = [];
        $extensionAttributes = $order->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getShippingAssignments()) {
            foreach ($extensionAttributes->getShippingAssignments() as $shippingAssignment) {
                $items = $this->extractShippingItems($shippingAssignment);
                $shipping = $shippingAssignment->getShipping();
                $address = $this->extractShippingAddress($shipping);

                $shippingAssignments[] = [
                    'method' => $shipping ? $shipping->getMethod() : null,
                    'address' => $address,
                    'items' => $items,
                ];
            }
        }

        return $shippingAssignments;
    }

    /**
     * Extract shipping items from a shipping assignment.
     *
     * @param \Magento\Sales\Api\Data\ShippingAssignmentInterface $assignment
     * @return array
     */
    private function extractShippingItems($assignment): array
    {
        $items = [];
        foreach ($assignment->getItems() as $item) {
            $items[] = [
                'item_id' => $item->getItemId(),
                'product_id' => $item->getProductId(),
            ];
        }

        return $items;
    }

    /**
     * Extract and format shipping address from shipping object.
     *
     * @param \Magento\Sales\Api\Data\ShippingInterface|null $shipping
     * @return array|null
     */
    private function extractShippingAddress($shipping): ?array
    {
        if (!$shipping || !$shipping->getAddress()) {
            return null;
        }

        $address = $shipping->getAddress()->getData();
        if (!is_array($address)) {
            return null;
        }

        $address['street'] = $shipping->getAddress()->getStreet();
        $address['country_code'] = $shipping->getAddress()->getCountryId();

        return $address;
    }

    /**
     * Add Tapbuy-specific metadata to order data.
     *
     * @param array $orderData
     * @param OrderInterface $order
     * @return array
     */
    private function formatTapbuyMetadata(array $orderData, OrderInterface $order): array
    {
        $orderData['tapbuy_state'] = $order->getState();
        $orderData['model'] = $order;

        return $orderData;
    }
}
