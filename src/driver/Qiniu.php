<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use Overtrue\Flysystem\Qiniu\QiniuAdapter;
use watsonhaw\filesystem\Driver;

class Qiniu extends Driver
{
    /**
     * 创建七牛云存储适配器
     *
     * 该方法负责实例化并返回一个新的 QiniuAdapter 对象,该对象用于与七牛云存储进行交互
     * 它使用了当前实例的配置信息,包括访问密钥、秘密密钥、存储桶名称和域名
     *
     * @return QiniuAdapter 返回一个配置好的七牛云存储适配器实例
     */
    protected function createAdapter(): QiniuAdapter
    {
        $accessKey = $this->config['access_key'] ?? null;
        $secretKey = $this->config['secret_key'] ?? null;
        $bucket    = $this->config['bucket'] ?? null;
        $domain    = $this->config['domain'] ?? null;

        if ($accessKey === null || $secretKey === null || $bucket === null || $domain === null) {
            throw new \InvalidArgumentException(
                'Qiniu driver requires access_key, secret_key, bucket and domain in the config.'
            );
        }

        return new QiniuAdapter($accessKey, $secretKey, $bucket, $domain);
    }
}