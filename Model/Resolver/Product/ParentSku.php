<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

/**
 * Resolver for parent_sku field on ProductInterface.
 *
 * Returns the SKU of the parent configurable product for simple products
 * that are variants of a configurable product.
 */
class ParentSku implements ResolverInterface
{
    /**
     * @param ConfigurableType $configurableType
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ConfigurableType $configurableType,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Resolves the parent_sku field for a product.
     *
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return string|null The parent SKU or null if not a child product
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?string {
        if (!$this->config->isEnabled()) {
            return null;
        }

        if (!isset($value['model'])) {
            return null;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $value['model'];

        // Only simple products can have a parent configurable product
        if ($product->getTypeId() !== 'simple') {
            return null;
        }

        // Get parent configurable product IDs for this simple product
        $parentIds = $this->configurableType->getParentIdsByChild($product->getId());

        if (empty($parentIds)) {
            return null;
        }

        // Get the first parent product (a simple product typically has one parent)
        try {
            $parentProduct = $this->productRepository->getById((int) $parentIds[0]);
            return $parentProduct->getSku();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
