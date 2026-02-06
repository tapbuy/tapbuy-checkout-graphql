<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Plugin;

use Magento\QuoteGraphQl\Model\Resolver\SetPaymentMethodOnCart;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;
use Tapbuy\RedirectTracking\Api\TapbuyConstants;
use Tapbuy\RedirectTracking\Api\TapbuyRequestDetectorInterface;

class SetPaymentMethodOnCartPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TapbuyRequestDetectorInterface
     */
    private $requestDetector;

    /**
     * Constructor
     *
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param TapbuyRequestDetectorInterface $requestDetector
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        TapbuyRequestDetectorInterface $requestDetector
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->requestDetector = $requestDetector;
    }

    /**
     * After resolve plugin for SetPaymentMethodOnCart.
     *
     * @param SetPaymentMethodOnCart $subject
     * @param mixed $result
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     */
    public function afterResolve(
        SetPaymentMethodOnCart $subject,
        $result,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        // Early return for non-Tapbuy requests to avoid unnecessary processing
        if (!$this->requestDetector->isTapbuyCall()) {
            return $result;
        }

        try {
            $cartId = $args['input']['cart_id'] ?? null;
            $paymentMethod = $args['input']['payment_method'] ?? null;
            $tapbuyAdditionalInfo = $paymentMethod['tapbuy_additional_information'] ?? null;

            if ($cartId && $tapbuyAdditionalInfo) {
                $this->setTapbuyAdditionalInformation($cartId, $tapbuyAdditionalInfo);

                $this->logger->debug('Checkout-GraphQL: Set Tapbuy additional information on cart', [
                    'cart_id' => $cartId,
                    'additional_info_keys' => array_keys($tapbuyAdditionalInfo),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->logException('Error setting Tapbuy additional information', $e);
        }

        return $result;
    }

    /**
     * Set Tapbuy additional information on payment
     *
     * @param string $cartId
     * @param array $additionalInfo
     */
    private function setTapbuyAdditionalInformation(string $cartId, array $additionalInfo): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());

        $payment = $quote->getPayment();

        $payment->setAdditionalInformation(
            TapbuyConstants::PAYMENT_ADDITIONAL_INFO_KEY,
            $this->serializer->serialize($additionalInfo)
        );
        $this->cartRepository->save($quote);
    }
}
