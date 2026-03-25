<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class Customer implements ResolverInterface
{
    /**
     * Required ACL resource for viewing customer data
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_CUSTOMER_VIEW;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly TokenAuthorizationInterface $tokenAuthorization,
        private readonly ConfigInterface $config
    ) {
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
        if (!$this->config->isEnabled()) {
            return null;
        }

        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (!isset($value['model'])) {
            return null;
        }

        /** @var \Magento\Customer\Model\Data\Customer $customer */
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
     * @param CustomerInterface $customer
     * @return int|null
     */
    private function getCustomerId(CustomerInterface $customer): ?int
    {
        $id = $customer->getId();
        return $id !== null ? (int) $id : null;
    }
}
