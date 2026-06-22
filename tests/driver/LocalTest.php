<?php

declare(strict_types=1);

namespace hulang\filesystem\tests\driver;

use hulang\filesystem\driver\Local;
use hulang\filesystem\tests\TestCase;
use League\Flysystem\Local\LocalFilesystemAdapter;

class LocalTest extends TestCase
{
    public function testCreateAdapterReturnsLocalAdapter()
    {
        $tmpDir = sys_get_temp_dir() . '/think_fs_local_test_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        $driver = new Local($this->getMockCache(), [
            'type' => 'local',
            'root' => $tmpDir,
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(LocalFilesystemAdapter::class, $adapter);

        $this->rmdirRecursive($tmpDir);
    }

    public function testRootFromConfig()
    {
        $tmpDir = sys_get_temp_dir() . '/think_fs_local_root_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        $driver = new Local($this->getMockCache(), [
            'type' => 'local',
            'root' => $tmpDir,
        ]);

        $path = $driver->path('foo.txt');
        $this->assertStringStartsWith($tmpDir, $path);

        $this->rmdirRecursive($tmpDir);
    }

    public function testPutAndGetThroughLocal()
    {
        $tmpDir = sys_get_temp_dir() . '/think_fs_local_putget_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        $driver = new Local($this->getMockCache(), [
            'type' => 'local',
            'root' => $tmpDir,
        ]);

        $this->assertTrue($driver->put('msg.txt', 'hello'));
        $this->assertSame('hello', $driver->get('msg.txt'));

        $this->rmdirRecursive($tmpDir);
    }
}