<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

class Customer implements ResolverInterface
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
     * Resolves additional fields for the Customer type in the GraphQL query.
     *
     * This method extends the Customer type with custom resolvers, such as `customer_id`.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value The parent resolver's data, including the customer model.
     * @param array|null $args The arguments passed to the GraphQL query.
     * @throws \Exception If authorization fails.
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
        $this->tokenAuthorization->authorize('Magento_Customer::customer');

        if (!isset($value['model'])) {
            return null;
        }

        $customer = $value['model'];
        $fieldName = $field->getName();

        switch ($fieldName) {
            case 'tapbuy_customer_id':
                return $this->getCustomerId($customer);

            default:
                return null;
        }
    }

    /**
     * Get customer ID
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return int
     */
    private function getCustomerId($customer)
    {
        return $customer->getId();
    }

}
