<?php

namespace Tapbuy\CheckoutGraphql\Model;

use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderLocator
{
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
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByIdentifier(string $identifier): OrderInterface
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            throw new NoSuchEntityException(__('Order identifier is empty.'));
        }

        if (ctype_digit($normalizedIdentifier)) {
            $orderId = (int)$normalizedIdentifier;
            if ($orderId > 0) {
                try {
                    return $this->orderRepository->get($orderId);
                } catch (NoSuchEntityException $exception) {
                    // Continue and try with increment ID below.
                }
            }
        }

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
            __('Order with identifier "%1" does not exist.', $identifier)
        );
    }
}
