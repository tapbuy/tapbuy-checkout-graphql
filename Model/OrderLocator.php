<?php

namespace Tapbuy\CheckoutGraphql\Model;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderLocator
{
    public const IDENTIFIER_TYPE_AUTO = 'auto';
    public const IDENTIFIER_TYPE_ENTITY_ID = 'entity_id';
    public const IDENTIFIER_TYPE_INCREMENT_ID = 'increment_id';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
    }

    /**
     * Retrieve order by ID or increment ID.
     *
     * @param string $identifier
     * @param string $identifierType
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByIdentifier(string $identifier, string $identifierType = self::IDENTIFIER_TYPE_AUTO): OrderInterface
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            throw new NoSuchEntityException(__('Order identifier is empty.'));
        }

        if ($identifierType === self::IDENTIFIER_TYPE_INCREMENT_ID) {
            return $this->getByIncrementId($normalizedIdentifier, $identifier);
        }

        if ($identifierType === self::IDENTIFIER_TYPE_ENTITY_ID) {
            return $this->getByEntityId($normalizedIdentifier, $identifier);
        }

        // Auto mode: try increment ID first to preserve backwards compatibility and avoid collisions.
        try {
            return $this->getByIncrementId($normalizedIdentifier, $identifier);
        } catch (NoSuchEntityException $exception) {
            // fall back to entity ID if identifier looks numeric
        }

        return $this->getByEntityId($normalizedIdentifier, $identifier);
    }

    /**
     * Retrieve order by entity ID.
     *
     * @param string $normalizedIdentifier
     * @param string $originalIdentifier
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    private function getByEntityId(string $normalizedIdentifier, string $originalIdentifier): OrderInterface
    {
        if (!ctype_digit($normalizedIdentifier)) {
            throw new NoSuchEntityException(
                __('Order with identifier "%1" does not exist.', $originalIdentifier)
            );
        }

        $orderId = (int)$normalizedIdentifier;
        if ($orderId <= 0) {
            throw new NoSuchEntityException(
                __('Order with identifier "%1" does not exist.', $originalIdentifier)
            );
        }

        return $this->orderRepository->get($orderId);
    }

    /**
     * Retrieve order by increment ID.
     *
     * @param string $normalizedIdentifier
     * @param string $originalIdentifier
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    private function getByIncrementId(string $normalizedIdentifier, string $originalIdentifier): OrderInterface
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('increment_id', $normalizedIdentifier)
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        $order = reset($orders);

        if ($order && $order->getEntityId()) {
            return $order;
        }

        throw new NoSuchEntityException(
            __('Order with identifier "%1" does not exist.', $originalIdentifier)
        );
    }
}
