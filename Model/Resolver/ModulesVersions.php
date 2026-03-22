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
     * @param TokenAuthorizationInterface $tokenAuthorization
     * @param ComponentRegistrar $componentRegistrar
     * @param File $file
     * @param Json $json
     * @param ModuleManager $moduleManager
     * @param ConfigInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TokenAuthorizationInterface $tokenAuthorization,
        private readonly ComponentRegistrar $componentRegistrar,
        private readonly File $file,
        private readonly Json $json,
        private readonly ModuleManager $moduleManager,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
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
            // Skip non-Tapbuy modules
            if (strpos($moduleName, 'Tapbuy_') !== 0) {
                continue;
            }

            $composerJsonPath = $modulePath . '/composer.json';
            $isEnabled = $this->moduleManager->isEnabled($moduleName);

            try {
                $tapbuyModules[] = $this->resolveModuleVersionEntry($moduleName, $composerJsonPath, $isEnabled);
            } catch (\InvalidArgumentException $e) {
                $this->logger->logException(
                    'Failed to parse composer.json for module (malformed JSON)',
                    $e,
                    ['module' => $moduleName, 'path' => $composerJsonPath]
                );
                $tapbuyModules[] = ['name' => $moduleName, 'version' => 'Unknown', 'enabled' => $isEnabled];
            } catch (\RuntimeException $e) {
                $this->logger->logException(
                    'Failed to read composer.json for module (filesystem error)',
                    $e,
                    ['module' => $moduleName, 'path' => $composerJsonPath]
                );
                $tapbuyModules[] = ['name' => $moduleName, 'version' => 'Unknown', 'enabled' => $isEnabled];
            }
        }

        return $tapbuyModules;
    }

    /**
     * Resolve the version entry for a single Tapbuy module.
     *
     * Reads the module's composer.json if it exists; otherwise returns an Unknown version entry.
     *
     * @param string $moduleName
     * @param string $composerJsonPath
     * @param bool $isEnabled
     * @return array
     * @throws \RuntimeException If the file cannot be read.
     * @throws \InvalidArgumentException If the JSON is malformed.
     */
    private function resolveModuleVersionEntry(string $moduleName, string $composerJsonPath, bool $isEnabled): array
    {
        if (!$this->file->isExists($composerJsonPath)) {
            return ['name' => $moduleName, 'version' => 'Unknown', 'enabled' => $isEnabled];
        }

        $composerContent = $this->file->fileGetContents($composerJsonPath);
        $composerData = $this->json->unserialize($composerContent);

        return [
            'name' => $composerData['name'] ?? $moduleName,
            'version' => $composerData['version'] ?? 'Unknown',
            'enabled' => $isEnabled,
        ];
    }
}
