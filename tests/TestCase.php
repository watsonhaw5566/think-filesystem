<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\tests;

use watsonhaw\filesystem\driver\Local;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionClass;
use think\Cache;
use ReflectionProperty;

abstract class TestCase extends PHPUnitTestCase
{
    protected function getMockCache()
    {
        return $this->createMock(Cache::class);
    }

    protected function createLocalDriver(array $extraConfig = [])
    {
        $tmpDir = sys_get_temp_dir() . '/think_filesystem_' . uniqid() . '_' . bin2hex(random_bytes(4));
        @mkdir($tmpDir, 0755, true);

        $config = array_merge([
            'type' => 'local',
            'root' => $tmpDir,
        ], $extraConfig);

        $driver = new Local($this->getMockCache(), $config);

        return $driver;
    }

    protected function getPrivateProperty(string $class, string $name): ReflectionProperty
    {
        $reflection = new ReflectionClass($class);
        $property   = $reflection->getProperty($name);
        $property->setAccessible(true);

        return $property;
    }

    protected function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->rmdirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
