<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\Order\CustomerAssignment;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;
use Tapbuy\RedirectTracking\Api\Order\OrderLocatorInterface;

class OrderAssignCustomer implements ResolverInterface
{
    /**
     * Required ACL resource for assigning orders to customers
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_ORDER_ASSIGN;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param OrderDataFormatterInterface $orderFormatter
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAssignment $customerAssignment
     * @param OrderLocatorInterface $orderLocator
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly TokenAuthorizationInterface $tokenAuthorization,
        private readonly OrderDataFormatterInterface $orderFormatter,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerAssignment $customerAssignment,
        private readonly OrderLocatorInterface $orderLocator,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Resolve order assignment to customer.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
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

        if (empty($args['order_id'])) {
            throw new GraphQlInputException(__('Order ID is required'));
        }

        if (empty($args['customer_id'])) {
            throw new GraphQlInputException(__('Customer ID is required'));
        }

        $orderIdentifier = (string)$args['order_id'];
        $customerId = (int)$args['customer_id'];
        $identifierType = $this->resolveIdentifierType(
            $args['order_identifier_type'] ?? OrderLocatorInterface::IDENTIFIER_TYPE_AUTO
        );

        try {
            $order = $this->orderLocator->getByIdentifier($orderIdentifier, $identifierType);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Order with identifier "%order_id" does not exist.', ['order_id' => $orderIdentifier])
            );
        }
        $customer = $this->getCustomerById($customerId);

        $alreadyAssigned = $this->resolveAlreadyAssigned($order, $customerId);
        if ($alreadyAssigned !== null) {
            return $alreadyAssigned;
        }

        $this->assertEmailsCompatible(
            trim((string)$order->getCustomerEmail()),
            trim((string)$customer->getEmail())
        );

        try {
            $this->customerAssignment->execute($order, $customer);
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException(__($exception->getMessage()));
        }

        if ((int)$order->getCustomerId() !== $customerId) {
            throw new GraphQlInputException(__('Failed to assign order to customer.'));
        }

        return [
            'success' => true,
            'order' => $this->orderFormatter->format($order)
        ];
    }

    /**
     * Retrieve customer by ID.
     *
     * @param int $customerId
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws GraphQlNoSuchEntityException
     */
    private function getCustomerById(int $customerId): CustomerInterface
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Customer with ID "%customer_id" does not exist.', ['customer_id' => $customerId])
            );
        }
    }

    /**
     * Resolve and validate the order identifier type argument.
     *
     * @param string|null $identifierType
     * @return string
     * @throws GraphQlInputException
     */
    private function resolveIdentifierType(?string $identifierType): string
    {
        if ($identifierType === null) {
            return OrderLocatorInterface::IDENTIFIER_TYPE_AUTO;
        }

        $normalizedType = strtolower(trim($identifierType));
        $allowedTypes = [
            OrderLocatorInterface::IDENTIFIER_TYPE_AUTO,
            OrderLocatorInterface::IDENTIFIER_TYPE_ENTITY_ID,
            OrderLocatorInterface::IDENTIFIER_TYPE_INCREMENT_ID,
        ];

        if (!in_array($normalizedType, $allowedTypes, true)) {
            throw new GraphQlInputException(
                __('Invalid order identifier type "%identifier_type". Allowed values: %allowed.', [
                    'identifier_type' => $identifierType,
                    'allowed' => implode(', ', $allowedTypes),
                ])
            );
        }

        return $normalizedType;
    }

    /**
     * Check if the order is already assigned (non-guest) and return result or throw if conflict.
     *
     * Returns null when the order is a guest order and assignment can proceed.
     * Returns the formatted order array when already assigned to the same customer (idempotent).
     * Throws when the order is assigned to a different customer.
     *
     * @param mixed $order
     * @param int $customerId
     * @return array|null
     * @throws GraphQlInputException
     */
    private function resolveAlreadyAssigned($order, int $customerId): ?array
    {
        if ($order->getCustomerIsGuest()) {
            return null;
        }

        if ((int)$order->getCustomerId() === $customerId) {
            return [
                'success' => true,
                'order' => $this->orderFormatter->format($order)
            ];
        }

        throw new GraphQlInputException(__('Order is already assigned to a customer.'));
    }

    /**
     * Assert that the order and customer emails are present and match (case-insensitive).
     *
     * @param string $orderEmail
     * @param string $customerEmail
     * @return void
     * @throws GraphQlInputException
     */
    private function assertEmailsCompatible(string $orderEmail, string $customerEmail): void
    {
        if ($orderEmail === '' || $customerEmail === '') {
            throw new GraphQlInputException(__('Order or customer email is missing.'));
        }

        if (strcasecmp($orderEmail, $customerEmail) !== 0) {
            throw new GraphQlInputException(__('Order email does not match the customer email.'));
        }
    }
}
