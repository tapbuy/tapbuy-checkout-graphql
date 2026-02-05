<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
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
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;
use Tapbuy\CheckoutGraphql\Api\OrderLocatorInterface;

class OrderAssignCustomer implements ResolverInterface
{
    /**
     * Required ACL resource for assigning orders to customers
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_ORDER_ASSIGN;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var OrderDataFormatterInterface
     */
    private $orderFormatter;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerAssignment
     */
    private $customerAssignment;

    /**
     * @var OrderLocatorInterface
     */
    private $orderLocator;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param OrderDataFormatterInterface $orderFormatter
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAssignment $customerAssignment
     * @param OrderLocatorInterface $orderLocator
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        OrderDataFormatterInterface $orderFormatter,
        CustomerRepositoryInterface $customerRepository,
        CustomerAssignment $customerAssignment,
        OrderLocatorInterface $orderLocator
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderFormatter = $orderFormatter;
        $this->customerRepository = $customerRepository;
        $this->customerAssignment = $customerAssignment;
        $this->orderLocator = $orderLocator;
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

        if (!$order->getCustomerIsGuest()) {
            if ((int)$order->getCustomerId() === $customerId) {
                return [
                    'success' => true,
                    'order' => $this->orderFormatter->format($order)
                ];
            }

            throw new GraphQlInputException(__('Order is already assigned to a customer.'));
        }

        $orderEmail = trim((string)$order->getCustomerEmail());
        $customerEmail = trim((string)$customer->getEmail());

        if ($orderEmail === '' || $customerEmail === '') {
            throw new GraphQlInputException(__('Order or customer email is missing.'));
        }

        if (strcasecmp($orderEmail, $customerEmail) !== 0) {
            throw new GraphQlInputException(__('Order email does not match the customer email.'));
        }

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
    private function getCustomerById(int $customerId)
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
}
