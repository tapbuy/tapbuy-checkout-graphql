<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

class OrderPaymentMethod implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @param TokenAuthorization $tokenAuthorization
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
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
        $this->tokenAuthorization->authorize('Magento_Sales::actions_view');

        if (!isset($value['model'])) {
            return null;
        }

        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $value['model'];

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
}
