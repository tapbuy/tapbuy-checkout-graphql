<?php

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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CustomerAssignment;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;
use Tapbuy\CheckoutGraphql\Model\OrderDataFormatter;
use Tapbuy\CheckoutGraphql\Model\OrderLocator;

class OrderAssignCustomer implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderDataFormatter
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
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderDataFormatter $orderFormatter
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAssignment $customerAssignment
     * @param OrderLocator $orderLocator
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        OrderRepositoryInterface $orderRepository,
        OrderDataFormatter $orderFormatter,
        CustomerRepositoryInterface $customerRepository,
        CustomerAssignment $customerAssignment,
        OrderLocator $orderLocator
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderRepository = $orderRepository;
        $this->orderFormatter = $orderFormatter;
        $this->customerRepository = $customerRepository;
        $this->customerAssignment = $customerAssignment;
        $this->orderLocator = $orderLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_edit');

        if (empty($args['order_id'])) {
            throw new GraphQlInputException(__('Order ID is required'));
        }

        if (empty($args['customer_id'])) {
            throw new GraphQlInputException(__('Customer ID is required'));
        }

        $orderIdentifier = (string)$args['order_id'];
        $customerId = (int)$args['customer_id'];

        try {
            $order = $this->orderLocator->getByIdentifier($orderIdentifier);
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

        $assignedOrder = $this->orderRepository->get((int)$order->getEntityId());

        if ((int)$assignedOrder->getCustomerId() !== $customerId) {
            throw new GraphQlInputException(__('Failed to assign order to customer.'));
        }

        return [
            'success' => true,
            'order' => $this->orderFormatter->format($assignedOrder)
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

}
