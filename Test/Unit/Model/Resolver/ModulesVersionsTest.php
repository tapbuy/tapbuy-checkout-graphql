<?php

declare(strict_types=1);

namespace Tapbuy\CheckoutGraphql\Test\Unit\Model\Resolver;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\CheckoutGraphql\Model\Resolver\ModulesVersions;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LoggerInterface;

class ModulesVersionsTest extends TestCase
{
    private ModulesVersions $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuthorization;
    private ComponentRegistrar&MockObject $componentRegistrar;
    private File&MockObject $file;
    private Json&MockObject $json;
    private ModuleManager&MockObject $moduleManager;
    private ConfigInterface&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuthorization = $this->createMock(TokenAuthorizationInterface::class);
        $this->componentRegistrar = $this->createMock(ComponentRegistrar::class);
        $this->file = $this->createMock(File::class);
        $this->json = $this->createMock(Json::class);
        $this->moduleManager = $this->createMock(ModuleManager::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);

        $this->resolver = new ModulesVersions(
            $this->tokenAuthorization,
            $this->componentRegistrar,
            $this->file,
            $this->json,
            $this->moduleManager,
            $this->config,
            $this->logger
        );
    }

    public function testReturnsDisabledMessageWhenConfigDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->componentRegistrar->method('getPaths')->willReturn([]);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('disabled', $result[0]['name']);
        $this->assertFalse($result[0]['enabled']);
    }

    public function testReturnsTapbuyModulesWithVersions(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Tapbuy_CheckoutGraphql' => '/path/to/checkout-graphql',
            'Magento_Sales' => '/path/to/sales', // non-Tapbuy module should be skipped
        ]);
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->file->method('isExists')->willReturn(true);
        $this->file->method('fileGetContents')->willReturn('{}');
        $this->json->method('unserialize')->willReturn([
            'name' => 'tapbuy/checkout-graphql',
            'version' => '1.2.3',
        ]);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertCount(1, $result);
        $this->assertSame('tapbuy/checkout-graphql', $result[0]['name']);
        $this->assertSame('1.2.3', $result[0]['version']);
        $this->assertTrue($result[0]['enabled']);
    }

    public function testHandlesMissingComposerJson(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Tapbuy_Forter' => '/path/to/forter',
        ]);
        $this->moduleManager->method('isEnabled')->willReturn(false);
        $this->file->method('isExists')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertSame('Tapbuy_Forter', $result[0]['name']);
        $this->assertSame('Unknown', $result[0]['version']);
        $this->assertFalse($result[0]['enabled']);
    }

    public function testHandlesMalformedComposerJson(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Tapbuy_Alma' => '/path/to/alma',
        ]);
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->file->method('isExists')->willReturn(true);
        $this->file->method('fileGetContents')->willReturn('{bad');
        $this->json->method('unserialize')
            ->willThrowException(new \InvalidArgumentException('Cannot unserialize'));

        $this->logger->expects($this->once())->method('logException');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertSame('Tapbuy_Alma', $result[0]['name']);
        $this->assertSame('Unknown', $result[0]['version']);
    }

    public function testHandlesFileSystemError(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->componentRegistrar->method('getPaths')->willReturn([
            'Tapbuy_Adyen' => '/path/to/adyen',
        ]);
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->file->method('isExists')->willReturn(true);
        $this->file->method('fileGetContents')
            ->willThrowException(new \RuntimeException('Permission denied'));

        $this->logger->expects($this->once())->method('logException');

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertSame('Unknown', $result[0]['version']);
    }
}
