<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\tests\driver;

use watsonhaw\filesystem\driver\Aliyun;
use watsonhaw\filesystem\tests\TestCase;
use yzh52521\Flysystem\Oss\OssAdapter;

class AliyunTest extends TestCase
{
    public function testCreateAdapterReturnsOssAdapter()
    {
        $driver = new Aliyun($this->getMockCache(), [
            'type'          => 'aliyun',
            'access_id'     => 'test-id',
            'access_secret' => 'test-secret',
            'bucket'        => 'test-bucket',
            'endpoint'      => 'oss-cn-hangzhou.aliyuncs.com',
            'isCName'       => false,
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(OssAdapter::class, $adapter);
    }
}
