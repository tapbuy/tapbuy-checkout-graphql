<?php

namespace Tapbuy\CheckoutGraphql\Model\Authorization;

use Magento\Framework\App\RequestInterface;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Integration\Model\IntegrationService;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

class TokenAuthorization
{
    /**
     * @var RequestInterface
     */
    private $requestInterface;

    /**
     * @var TokenFactory
     */
    private $tokenModelFactory;

    /**
     * @var IntegrationService
     */
    private $integrationService;


    /**
     * TokenAuthorization constructor.
     * @param RequestInterface $requestInterface
     * @param TokenFactory $tokenModelFactory
     * @param IntegrationService $integrationService
     */
    public function __construct(
        RequestInterface $requestInterface,
        TokenFactory $tokenModelFactory,
        IntegrationService $integrationService
    ) {
        $this->requestInterface = $requestInterface;
        $this->tokenModelFactory = $tokenModelFactory;
        $this->integrationService = $integrationService;
    }

    /**
     * Get the token from the request.
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        $token = null;
        $bearer = $this->requestInterface->getHeader('Authorization');

        if ($bearer) {
            $token = str_replace("Bearer ", "", $bearer);
        }
        if (!$token) {
            throw new GraphQlAuthorizationException(__('Token is required.'));
        }

        return $token;
    }

    /**
     * Check if the token has the required permission.
     *
     * @param string $requiredResource The resource to check permissions for.
     * @throws GraphQlAuthorizationException If the token is invalid or lacks permissions.
     */
    public function authorize(string $requiredResource): void
    {
        $token = $this->getToken();
        $tokenModel = $this->tokenModelFactory->create()->loadByToken($token);

        if (!$tokenModel->getId()) {
            throw new GraphQlAuthorizationException(__('Invalid token.'));
        }

        $consumerId = $tokenModel->getConsumerId();
        $integration = $this->integrationService->findByConsumerId($consumerId);

        if (!$integration->getId() || !$integration->getStatus()) {
            throw new GraphQlAuthorizationException(__('Invalid integration.'));
        }

        // Get integration permissions
        $permissions = $this->integrationService->getSelectedResources($integration->getId());

        if (
            !in_array('Magento_Backend::admin', $permissions) &&
            !in_array('Magento_Backend::all', $permissions) &&
            !in_array($requiredResource, $permissions)
        ) {
            throw new GraphQlAuthorizationException(__('You do not have permission to access this resource.'));
        }
    }
}
