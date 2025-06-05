<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

class GetOrder implements ResolverInterface
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
     * @var OrderFormatter
     */
    private $orderFormatter;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderFormatter $orderFormatter
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        OrderRepositoryInterface $orderRepository,
        OrderFormatter $orderFormatter,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderRepository = $orderRepository;
        $this->orderFormatter = $orderFormatter;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Resolves the order details based on the provided order number.
     * Gives the ability to retrieve order information by its increment ID, even for guest orders.
     * Relying on the token authorization to ensure the user has permission to view order details.
     * GetOrderItems is used to bypass the default order items resolver authorization check.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value The parent resolver's data, including the order model.
     * @param array|null $args The arguments passed to the GraphQL query.
     * @throws \Exception If authorization fails or order not found.
     *
     * @return mixed The resolved value for the requested field or null if not found.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_view');

        if (empty($args['order_number'])) {
            throw new GraphQlInputException(__('Order number is required'));
        }

        $orderNumber = $args['order_number'];

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $orderNumber)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        $order = reset($orders);

        if (!$order || !$order->getEntityId()) {
            throw new GraphQlNoSuchEntityException(
                __('Order with number "%increment_id" does not exist.', ['increment_id' => $orderNumber])
            );
        }

        $orderData = $this->orderFormatter->format($order);
        $orderData['model'] = $order;

        return $orderData;
    }
}
