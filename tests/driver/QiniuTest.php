<?php

declare(strict_types=1);

namespace hulang\filesystem\tests\driver;

use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use hulang\filesystem\driver\Qiniu;
use hulang\filesystem\tests\TestCase;

class QiniuTest extends TestCase
{
    public function testCreateAdapterReturnsQiniuAdapter()
    {
        $driver = new Qiniu($this->getMockCache(), [
            'type' => 'qiniu',
            'access_key' => 'test-access-key',
            'secret_key' => 'test-secret-key',
            'bucket' => 'test-bucket',
            'domain' => 'https://cdn.example.com',
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(QiniuAdapter::class, $adapter);
    }
}