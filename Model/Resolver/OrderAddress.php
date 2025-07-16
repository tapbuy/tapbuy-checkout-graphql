<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

class OrderAddress implements ResolverInterface
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
     * Resolve additional fields for the OrderAddress type.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return mixed
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Sales::actions_view');

        if (!isset($value['model'])) {
            return null;
        }

        $address = $value['model'];
        $fieldName = $field->getName();

        switch ($fieldName) {
            case 'tapbuy_entity_id':
                return $this->getEntityId($address);
            default:
                return null;
        }
    }

    /**
     * Get address entity ID
     *
     * @param mixed $address
     * @return int|null
     */
    private function getEntityId($address)
    {
        if (method_exists($address, 'getEntityId')) {
            return (int) $address->getEntityId();
        }
        if (method_exists($address, 'getId')) {
            return (int) $address->getId();
        }
        return null;
    }
}
