<?php
/**
 * Copied directly from the Magento resolver.
 * The only change is that the authorized customer check is removed.
 */

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\OrderItem\DataProvider as OrderItemProvider;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

/**
 * Resolve order items for order.
 */
class GetOrderItems implements ResolverInterface
{
    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @var ValueFactory
     */
    private $valueFactory;

    /**
     * @var OrderItemProvider
     */
    private $orderItemProvider;

    /**
     * @param TokenAuthorization $tokenAuthorization
     * @param ValueFactory $valueFactory
     * @param OrderItemProvider $orderItemProvider
     */
    public function __construct(
        TokenAuthorization $tokenAuthorization,
        ValueFactory $valueFactory,
        OrderItemProvider $orderItemProvider
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->valueFactory = $valueFactory;
        $this->orderItemProvider = $orderItemProvider;
    }

    /**
     * Resolves the GraphQL query for retrieving order items.
     * This method checks if the token is authorized to view order items,
     *
     * @param Field $field The GraphQL field being resolved.
     * @param mixed $context The context of the GraphQL request.
     * @param ResolveInfo $info Information about the GraphQL query.
     * @param array|null $value The value passed from the parent resolver, if any.
     * @param array|null $args The arguments provided in the GraphQL query.
     * @return mixed The resolved data for the order items.
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_view');

        if (!(($value['model'] ?? null) instanceof OrderInterface)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var OrderInterface $parentOrder */
        $parentOrder = $value['model'];
        $orderItemIds = [];
        foreach ($parentOrder->getItems() as $item) {
            if (!$item->getParentItemId()) {
                $orderItemIds[] = (int)$item->getItemId();
            }
            $this->orderItemProvider->addOrderItemId((int)$item->getItemId());
        }
        $itemsList = [];
        foreach ($orderItemIds as $orderItemId) {
            $itemsList[] = $this->valueFactory->create(
                function () use ($orderItemId) {
                    return $this->orderItemProvider->getOrderItemById((int)$orderItemId);
                }
            );
        }
        return $itemsList;
    }
}
