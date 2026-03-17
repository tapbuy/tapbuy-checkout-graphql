<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;

class CustomerSearch implements ResolverInterface
{
    /**
     * Required ACL resource for searching customers
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_CUSTOMER_SEARCH;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param ExtractCustomerData $extractCustomerData
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ConfigInterface $config
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ExtractCustomerData $extractCustomerData,
        TokenAuthorizationInterface $tokenAuthorization,
        ConfigInterface $config
    ) {
        $this->customerRepository = $customerRepository;
        $this->extractCustomerData = $extractCustomerData;
        $this->tokenAuthorization = $tokenAuthorization;
        $this->config = $config;
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
        if (!$this->config->isEnabled()) {
            throw new GraphQlInputException(__('Tapbuy is disabled.'));
        }

        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        if (empty($args['email'])) {
            throw new GraphQlInputException(__('Email is required'));
        }

        $email = $args['email'];

        try {
            $customer = $this->customerRepository->get($email);
            return $this->extractCustomerData->execute($customer);

        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}
