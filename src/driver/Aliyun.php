<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use OSS\Core\OssException;
use watsonhaw\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;

class Aliyun extends Driver
{
    /**
     * 创建OSS适配器实例
     *
     * 该方法负责实例化OssAdapter,并将当前类的配置信息传递给它
     * 主要用于内部实现存储或处理文件操作的适配层创建
     *
     * 由于底层 OssClient 在配置异常时会抛出 OssException (非 SPL 标准异常)，
     * 这里将其捕获并包装为 RuntimeException，保证上层能有一致的异常类型处理。
     *
     * @return OssAdapter 返回一个使用当前配置初始化的OssAdapter实例
     */
    protected function createAdapter(): OssAdapter
    {
        $accessId     = $this->config['access_id'] ?? null;
        $accessSecret = $this->config['access_secret'] ?? null;
        $bucket       = $this->config['bucket'] ?? null;
        $isCName      = $this->config['isCName'] ?? null;

        if ($accessId === null || $accessSecret === null || $bucket === null || $isCName === null) {
            throw new \InvalidArgumentException(
                'Aliyun driver requires access_id, access_secret, bucket and isCName in the config.'
            );
        }

        try {
            return new OssAdapter($this->config);
        } catch (OssException $e) {
            throw new \RuntimeException(
                sprintf('Aliyun driver failed to initialize OssClient: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}