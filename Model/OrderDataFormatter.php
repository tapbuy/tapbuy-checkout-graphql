<?php

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

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            if (!isset($orderData['shipping_address']) || !is_array($orderData['shipping_address'])) {
                $orderData['shipping_address'] = [];
            }
            $orderData['shipping_address']['model'] = $shippingAddress;
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            if (!isset($orderData['billing_address']) || !is_array($orderData['billing_address'])) {
                $orderData['billing_address'] = [];
            }
            $orderData['billing_address']['model'] = $billingAddress;
        }

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

        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getShippingAssignments()) {
            $orderData['tapbuy_shipping_assignments'] = [];
            foreach ($extensionAttributes->getShippingAssignments() as $shippingAssignment) {
                $items = [];
                foreach ($shippingAssignment->getItems() as $item) {
                    $items[] = [
                        'item_id' => $item->getItemId(),
                        'product_id' => $item->getProductId(),
                    ];
                }

                $shipping = $shippingAssignment->getShipping();
                $address = null;
                if ($shipping && $shipping->getAddress()) {
                    $address = $shipping->getAddress()->getData();
                    if (is_array($address)) {
                        $address['street'] = $shipping->getAddress()->getStreet();
                        $address['country_code'] = $shipping->getAddress()->getCountryId();
                    }
                }

                $orderData['tapbuy_shipping_assignments'][] = [
                    'method' => $shipping ? $shipping->getMethod() : null,
                    'address' => $address,
                    'items' => $items,
                ];
            }
        }

        $orderData['tapbuy_state'] = $order->getState();
        $orderData['model'] = $order;

        return $orderData;
    }
}
