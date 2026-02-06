<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\CheckoutGraphql\Api\OrderDataFormatterInterface;
use Tapbuy\CheckoutGraphql\Api\OrderLocatorInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class GetOrder implements ResolverInterface
{
    /**
     * Required ACL resource for viewing orders
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_ORDER_VIEW;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var OrderDataFormatterInterface
     */
    private $orderFormatter;

    /**
     * @var OrderLocatorInterface
     */
    private $orderLocator;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param OrderDataFormatterInterface $orderFormatter
     * @param OrderLocatorInterface $orderLocator
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        OrderDataFormatterInterface $orderFormatter,
        OrderLocatorInterface $orderLocator
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
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (empty($args['order_number']) && empty($args['order_id'])) {
            throw new GraphQlInputException(__('Either order_number or order_id is required'));
        }

        $orderNumber = $args['order_number'] ?? $args['order_id'];
        $identifierType = isset($args['order_id'])
            ? OrderLocatorInterface::IDENTIFIER_TYPE_ENTITY_ID
            : OrderLocatorInterface::IDENTIFIER_TYPE_INCREMENT_ID;

        try {
            $order = $this->orderLocator->getByIdentifier($orderNumber, $identifierType);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Order with number "%increment_id" does not exist.', ['increment_id' => $orderNumber])
            );
        }

        return $this->orderFormatter->format($order);
    }
}
