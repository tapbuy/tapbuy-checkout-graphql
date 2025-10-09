<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;
use Tapbuy\CheckoutGraphql\Model\OrderDataFormatter;
use Tapbuy\CheckoutGraphql\Model\OrderLocator;
use Magento\Framework\Exception\NoSuchEntityException;

class GetOrder implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @var OrderDataFormatter
     */
    private $orderFormatter;

    /**
     * @var OrderLocator
     */
    private $orderLocator;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param OrderDataFormatter $orderFormatter
     * @param OrderLocator $orderLocator
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        OrderDataFormatter $orderFormatter,
        OrderLocator $orderLocator
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->orderFormatter = $orderFormatter;
        $this->orderLocator = $orderLocator;
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
        ContextInterface $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_view');

        if (empty($args['order_number'])) {
            throw new GraphQlInputException(__('Order number is required'));
        }

        $orderNumber = $args['order_number'];

        try {
            $order = $this->orderLocator->getByIdentifier($orderNumber, OrderLocator::IDENTIFIER_TYPE_INCREMENT_ID);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Order with number "%increment_id" does not exist.', ['increment_id' => $orderNumber])
            );
        }

        return $this->orderFormatter->format($order);
    }
}
