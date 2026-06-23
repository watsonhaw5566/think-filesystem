<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\tests\driver;

use InvalidArgumentException;
use watsonhaw\filesystem\driver\Obs;
use watsonhaw\filesystem\tests\TestCase;
use yzh52521\Flysystem\Obs\ObsAdapter;

class ObsTest extends TestCase
{
    public function testCreateAdapterReturnsObsAdapter()
    {
        $driver = new Obs($this->getMockCache(), [
            'type'     => 'obs',
            'key'      => 'test-key',
            'secret'   => 'test-secret',
            'endpoint' => 'obs.cn-north-4.myhuaweicloud.com',
            'bucket'   => 'test-bucket',
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(ObsAdapter::class, $adapter);
    }

    public function testCreateAdapterWithSecurityToken()
    {
        $driver = new Obs($this->getMockCache(), [
            'type'           => 'obs',
            'key'            => 'test-key',
            'secret'         => 'test-secret',
            'endpoint'       => 'obs.cn-north-4.myhuaweicloud.com',
            'bucket'         => 'test-bucket',
            'security_token' => 'test-security-token',
        ]);

        $adapter = $driver->getAdapter();
        $this->assertInstanceOf(ObsAdapter::class, $adapter);
    }

    public function testMissingKeyThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        new Obs($this->getMockCache(), [
            'type'     => 'obs',
            'secret'   => 'test-secret',
            'endpoint' => 'obs.cn-north-4.myhuaweicloud.com',
            'bucket'   => 'test-bucket',
        ]);
    }

    public function testMissingSecretThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        new Obs($this->getMockCache(), [
            'type'     => 'obs',
            'key'      => 'test-key',
            'endpoint' => 'obs.cn-north-4.myhuaweicloud.com',
            'bucket'   => 'test-bucket',
        ]);
    }

    public function testMissingEndpointThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        new Obs($this->getMockCache(), [
            'type'   => 'obs',
            'key'    => 'test-key',
            'secret' => 'test-secret',
            'bucket' => 'test-bucket',
        ]);
    }

    public function testMissingBucketThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        new Obs($this->getMockCache(), [
            'type'     => 'obs',
            'key'      => 'test-key',
            'secret'   => 'test-secret',
            'endpoint' => 'obs.cn-north-4.myhuaweicloud.com',
        ]);
    }

    public function testEmptyConfigThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);

        new Obs($this->getMockCache(), ['type' => 'obs']);
    }

    public function testGetDriverReturnsFlysystem()
    {
        $driver = new Obs($this->getMockCache(), [
            'type'     => 'obs',
            'key'      => 'test-key',
            'secret'   => 'test-secret',
            'endpoint' => 'obs.cn-north-4.myhuaweicloud.com',
            'bucket'   => 'test-bucket',
        ]);

        $this->assertInstanceOf(\League\Flysystem\Filesystem::class, $driver->getDriver());
    }
}