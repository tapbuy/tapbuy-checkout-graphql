<?php

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Tapbuy\CheckoutGraphql\Model\Authorization\TokenAuthorization;

class CustomerSearch implements ResolverInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;

    /**
     * @var TokenAuthorization
     */
    private $tokenAuthorization;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param ExtractCustomerData $extractCustomerData
     * @param TokenAuthorization $tokenAuthorization
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ExtractCustomerData $extractCustomerData,
        TokenAuthorization $tokenAuthorization
    ) {
        $this->customerRepository = $customerRepository;
        $this->extractCustomerData = $extractCustomerData;
        $this->tokenAuthorization = $tokenAuthorization;
    }

    /**
     * Resolves the customer search query.
     *
     * This method is responsible for handling the logic to resolve
     * customer search requests in the GraphQL API.
     *
     * @param \Magento\Framework\GraphQl\Config\Element\Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @throws \Exception
     *
     * @return mixed The result of the customer search query.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $this->tokenAuthorization->authorize('Magento_Customer::customer');

        if (empty($args['email'])) {
            throw new GraphQlInputException(__('Email is required'));
        }

        $email = $args['email'];

        try {
            $customer = $this->customerRepository->get($email);
            return $this->extractCustomerData->execute($customer);

        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }
}
