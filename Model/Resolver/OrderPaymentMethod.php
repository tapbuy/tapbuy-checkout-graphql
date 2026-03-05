<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Magento\Sales\Model\Order\Payment;

class OrderPaymentMethod implements ResolverInterface
{
    /**
     * Required ACL resource for viewing order payment methods
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_ORDER_VIEW;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ConfigInterface $config
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        ConfigInterface $config
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->config = $config;
    }

    /**
     * Resolves the GraphQL query for retrieving the order payment method.
     *
     * @param Field $field The GraphQL field being resolved.
     * @param ContextInterface $context The context of the GraphQL query.
     * @param ResolveInfo $info Metadata for the GraphQL query resolution.
     * @param array|null $value The value resolved by the parent field, if any.
     * @param array|null $args The arguments provided in the GraphQL query.
     * @return mixed The resolved payment method data for the order.
     * @throws LocalizedException If an error occurs during resolution.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!$this->config->isEnabled()) {
            return null;
        }

        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (!isset($value['model'])) {
            return null;
        }

        /** @var Payment $payment */
        $payment = $value['model'];

        $fieldName = $field->getName();

        switch ($fieldName) {
            case 'tapbuy_additional_information':
                return $this->getAdditionalInformation($payment);
            case 'tapbuy_amount_ordered':
                return $this->getAmountOrdered($payment);
            default:
                return null;
        }
    }

    /**
     * Retrieves the additional information associated with the given payment.
     *
     * @param Payment $payment The payment object from which to extract additional information.
     * @return array An array containing the additional information related to the payment.
     */
    private function getAdditionalInformation(Payment $payment): array
    {
        $additionalInformation = $payment->getAdditionalInformation() ?? [];
        $additionalData = $additionalInformation['additionalData'] ?? [];
        return [
            'guest_email'     => $additionalInformation['guestEmail'] ?? null,
            'cc_type'         => $additionalInformation['cc_type'] ?? null,
            'method_title'    => $additionalInformation['method_title'] ?? null,
            '_3d_active'      => $additionalInformation['3dActive'] ?? null,
            'result_code'     => $additionalInformation['resultCode'] ?? null,
            'psp_reference'   => $additionalInformation['pspReference'] ?? null,
            'additional_data' => [
                'issuer_country'    => $additionalData['issuerCountry'] ?? null,
                'card_bin'          => $additionalData['cardBin'] ?? null,
                'card_holder_name'  => $additionalData['cardHolderName'] ?? null,
                'card_summary'      => $additionalData['cardSummary'] ?? null,
                'payment_method'    => $additionalData['paymentMethod'] ?? null,
            ],
        ];
    }

    /**
     * Retrieves the total amount ordered associated with the given payment.
     *
     * @param Payment $payment The payment instance for which to get the ordered amount.
     * @return float|null The total amount ordered, or null if not available.
     */
    private function getAmountOrdered(Payment $payment): ?float
    {
        return (float) $payment->getAmountOrdered() ?? null;
    }
}
