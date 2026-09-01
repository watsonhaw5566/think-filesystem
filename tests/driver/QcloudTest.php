<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\tests\driver;

use Overtrue\Flysystem\Cos\CosAdapter;
use watsonhaw\filesystem\driver\Qcloud;
use watsonhaw\filesystem\tests\TestCase;

class QcloudTest extends TestCase
{
    public function testCreateAdapterReturnsCosAdapter()
    {
        $driver = new Qcloud($this->getMockCache(), [
            'type'            => 'qcloud',
            'region'          => 'ap-guangzhou',
            'app_id'          => '1234567890',
            'secret_id'       => 'test-secret-id',
            'secret_key'      => 'test-secret-key',
            'bucket'          => 'test-bucket',
            'timeout'         => 60,
            'connect_timeout' => 60,
            'cdn'             => 'https://cdn.example.com',
            'scheme'          => 'https',
            'read_from_cdn'   => false,
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(CosAdapter::class, $adapter);
    }
}
