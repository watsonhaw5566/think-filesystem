<?php

declare(strict_types=1);

namespace hulang\filesystem\tests;

use hulang\filesystem\Driver;
use hulang\filesystem\Filesystem;
use think\App;

class FilesystemTest extends TestCase
{
    private ?string $tmpDir = null;
    private ?App $app = null;
    private ?Filesystem $filesystem = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/think_filesystem_manager_' . uniqid();
        @mkdir($this->tmpDir, 0755, true);

        $this->app = new App();

        // 设置配置
        $this->app->config->set([
            'default' => 'local',
            'disks' => [
                'local' => [
                    'type' => 'local',
                    'root' => $this->tmpDir,
                ],
                'cloud' => [
                    'type' => 'local',
                    'root' => $this->tmpDir . '/cloud',
                    'url' => 'https://example.com',
                ],
                'local_custom' => [
                    'type' => 'local',
                    'root' => $this->tmpDir . '/custom',
                ],
            ],
        ], 'filesystem');

        $this->filesystem = new Filesystem($this->app);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->rmdirRecursive($this->tmpDir);
        }
        parent::tearDown();
    }

    public function testDiskReturnsDriverInstance()
    {
        $driver = $this->filesystem->disk('local');
        $this->assertInstanceOf(Driver::class, $driver);
        $this->assertInstanceOf(\hulang\filesystem\driver\Local::class, $driver);
    }

    public function testDiskWithoutNameUsesDefault()
    {
        $driver = $this->filesystem->disk();
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function testCloudReturnsDriverInstance()
    {
        $driver = $this->filesystem->cloud('cloud');
        $this->assertInstanceOf(Driver::class, $driver);
    }

    public function testGetConfig()
    {
        $config = $this->filesystem->getConfig('default');
        $this->assertSame('local', $config);

        $all = $this->filesystem->getConfig();
        $this->assertIsArray($all);
        $this->assertSame('local', $all['default'] ?? null);
    }

    public function testGetDiskConfig()
    {
        $config = $this->filesystem->getDiskConfig('local');
        $this->assertIsArray($config);
        $this->assertSame('local', $config['type'] ?? null);

        $value = $this->filesystem->getDiskConfig('local', 'type');
        $this->assertSame('local', $value);

        $defaultVal = $this->filesystem->getDiskConfig('local', 'missing-key', 'default-value');
        $this->assertSame('default-value', $defaultVal);
    }

    public function testGetDiskConfigInvalidDiskThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->filesystem->getDiskConfig('does-not-exist');
    }

    public function testGetDefaultDriver()
    {
        $this->assertSame('local', $this->filesystem->getDefaultDriver());
    }

    public function testDriverIsSameOnMultipleCalls()
    {
        $driverA = $this->filesystem->disk('local');
        $driverB = $this->filesystem->disk('local');
        $this->assertSame($driverA, $driverB);
    }

    public function testDifferentDisksHaveDifferentInstances()
    {
        $driverA = $this->filesystem->disk('local');
        $driverB = $this->filesystem->disk('cloud');
        $this->assertNotSame($driverA, $driverB);
    }

    public function testExtend()
    {
        $customDriver = $this->createLocalDriver();
        $this->filesystem->extend('custom-type', function () use ($customDriver) {
            return $customDriver;
        });

        // 添加一个使用自定义驱动类型的磁盘
        $currentConfig = $this->filesystem->getConfig();
        $currentConfig['disks']['custom-disk'] = ['type' => 'custom-type'];
        $this->app->config->set($currentConfig, 'filesystem');

        $result = $this->filesystem->disk('custom-disk');
        $this->assertSame($customDriver, $result);

        $this->rmdirRecursive($this->extractRootFromDriver($customDriver));
    }

    private function extractRootFromDriver($driver): string
    {
        $prop = $this->getPrivateProperty(Driver::class, 'config');
        $config = $prop->getValue($driver);
        return $config['root'];
    }

    public function testDynamicCallPassthroughToDefaultDriver()
    {
        $this->filesystem->disk('local')->put('dynamic.txt', 'hello');
        $content = $this->filesystem->get('dynamic.txt');
        $this->assertSame('hello', $content);
    }

    public function testCreateDriverWithCustomCreators()
    {
        $called = false;
        $this->filesystem->extend('my-driver', function () use (&$called) {
            $called = true;
            return $this->createLocalDriver();
        });

        $currentConfig = $this->filesystem->getConfig();
        $currentConfig['disks']['my-disk'] = ['type' => 'my-driver'];
        $this->app->config->set($currentConfig, 'filesystem');

        $driver = $this->filesystem->disk('my-disk');
        $this->assertTrue($called);
        $this->assertInstanceOf(Driver::class, $driver);

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }
}