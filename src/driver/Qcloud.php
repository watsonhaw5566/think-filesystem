<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use Overtrue\Flysystem\Cos\CosAdapter;
use watsonhaw\filesystem\Driver;

class Qcloud extends Driver
{
    /**
     * 创建CosAdapter实例
     * 
     * 该方法负责实例化CosAdapter,以便后续进行具体的Cos操作
     * 
     * @return CosAdapter 返回一个CosAdapter实例,用于后续的Cos操作
     */
    protected function createAdapter(): CosAdapter
    {
        $appId     = $this->config['app_id'] ?? null;
        $secretId  = $this->config['secret_id'] ?? null;
        $secretKey = $this->config['secret_key'] ?? null;
        $bucket    = $this->config['bucket'] ?? null;

        if ($appId === null || $secretId === null || $secretKey === null || $bucket === null) {
            throw new \InvalidArgumentException(
                'Qcloud driver requires app_id, secret_id, secret_key and bucket in the config.'
            );
        }

        return new CosAdapter($this->config);
    }
}