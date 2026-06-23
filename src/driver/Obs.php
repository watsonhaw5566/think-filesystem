<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use InvalidArgumentException;
use Obs\ObsClient;
use watsonhaw\filesystem\Driver;
use yzh52521\Flysystem\Obs\ObsAdapter;

class Obs extends Driver
{
    /**
     * 创建 OBS 适配器实例
     *
     * 该方法负责实例化 ObsClient 并基于它创建 ObsAdapter
     * 会对必需的配置参数进行校验，缺失时抛出异常
     *
     * @return ObsAdapter 返回一个使用当前配置初始化的 ObsAdapter 实例
     */
    protected function createAdapter(): ObsAdapter
    {
        $key      = $this->config['key'] ?? null;
        $secret   = $this->config['secret'] ?? null;
        $endpoint = $this->config['endpoint'] ?? null;
        $bucket   = $this->config['bucket'] ?? null;

        if ($key === null || $secret === null || $endpoint === null || $bucket === null) {
            throw new InvalidArgumentException(
                'Obs driver requires key, secret, endpoint and bucket in the config.'
            );
        }

        $clientConfig = [
            'key'      => $key,
            'secret'   => $secret,
            'endpoint' => $endpoint,
        ];

        if (isset($this->config['security_token'])) {
            $clientConfig['security_token'] = $this->config['security_token'];
        }

        $client  = new ObsClient($clientConfig);
        $prefix  = $this->config['prefix'] ?? '';
        $options = $this->config['options'] ?? [];

        return new ObsAdapter($client, $bucket, $prefix, null, null, $options);
    }
}