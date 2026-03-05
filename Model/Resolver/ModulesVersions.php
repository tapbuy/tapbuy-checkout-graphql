<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Model\Resolver;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Module\Manager as ModuleManager;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class ModulesVersions implements ResolverInterface
{
    /**
     * Required ACL resource for viewing module versions
     */
    private const ACL_RESOURCE = TokenAuthorizationInterface::TAPBUY_MODULES_VERSIONS;

    /**
     * @var TokenAuthorizationInterface
     */
    private $tokenAuthorization;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ComponentRegistrar
     */
    private $componentRegistrar;

    /**
     * @var File
     */
    private $file;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ComponentRegistrar $componentRegistrar
     * @param File $file
     * @param Json $json
     * @param ModuleManager $moduleManager
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        TokenAuthorizationInterface $tokenAuthorization,
        ComponentRegistrar $componentRegistrar,
        File $file,
        Json $json,
        ModuleManager $moduleManager,
        ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->tokenAuthorization = $tokenAuthorization;
        $this->componentRegistrar = $componentRegistrar;
        $this->file = $file;
        $this->json = $json;
        $this->moduleManager = $moduleManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Resolves the versions of all installed Tapbuy modules.
     *
     * This method scans all registered modules, filters those under the Tapbuy namespace,
     * and retrieves their version information from their respective composer.json files.
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws \Exception If authorization fails.
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $this->tokenAuthorization->authorize(self::ACL_RESOURCE);

        $tapbuyModules = [];

        if (!$this->config->isEnabled()) {
            $tapbuyModules[] = ['name' => 'Tapbuy configuration is disabled', 'version' => 'N/A', 'enabled' => false];
        }

        $allModules = $this->componentRegistrar->getPaths(ComponentRegistrar::MODULE);

        foreach ($allModules as $moduleName => $modulePath) {
            // Check if module is under Tapbuy namespace
            if (strpos($moduleName, 'Tapbuy_') === 0) {
                $composerJsonPath = $modulePath . '/composer.json';
                $isEnabled = $this->moduleManager->isEnabled($moduleName);

                try {
                    if ($this->file->isExists($composerJsonPath)) {
                        $composerContent = $this->file->fileGetContents($composerJsonPath);
                        $composerData = $this->json->unserialize($composerContent);

                        $tapbuyModules[] = [
                            'name' => $composerData['name'] ?? $moduleName,
                            'version' => $composerData['version'] ?? 'Unknown',
                            'enabled' => $isEnabled
                        ];
                    } else {
                        // If no composer.json, still add module with unknown version
                        $tapbuyModules[] = [
                            'name' => $moduleName,
                            'version' => 'Unknown',
                            'enabled' => $isEnabled
                        ];
                    }
                } catch (\Exception $e) {
                    // Log the error and add module with unknown version
                    $this->logger->logException(
                        'Failed to read composer.json for module',
                        $e,
                        ['module' => $moduleName, 'path' => $composerJsonPath]
                    );
                    $tapbuyModules[] = [
                        'name' => $moduleName,
                        'version' => 'Unknown',
                        'enabled' => $isEnabled
                    ];
                }
            }
        }

        return $tapbuyModules;
    }
}
