<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Overtrue\CosClient\Signature;
use Overtrue\Flysystem\Cos\CosAdapter;
use watsonhaw\filesystem\Driver;
use InvalidArgumentException;

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
        $appId     = $this->config['app_id']     ?? null;
        $secretId  = $this->config['secret_id']  ?? null;
        $secretKey = $this->config['secret_key'] ?? null;
        $bucket    = $this->config['bucket']     ?? null;

        if ($appId === null || $secretId === null || $secretKey === null || $bucket === null) {
            throw new InvalidArgumentException(
                'Qcloud driver requires app_id, secret_id, secret_key and bucket in the config.'
            );
        }

        return new CosAdapter($this->config);
    }

    /**
     * 获取 COS 临时签名 URL
     *
     * 由于 CosAdapter::getObjectSignedUrl 没有支持在签名时带入 url_params，
     * 这里重新实现签名流程：先拼装好完整 URL（含图片处理等 query 参数），再用
     * Signature 对该 URL 进行签名，保证 query 参数也在签名范围内，避免访问时 403。
     *
     * @param string $path    资源路径
     * @param int    $expires 过期秒数
     * @param array  $options 支持 url_params 键追加查询参数
     * @return string
     */
    public function temporaryUrl(string $path, int $expires, array $options = []): string
    {
        $adapter = $this->unwrapAdapter($this->adapter);

        if (! $adapter instanceof CosAdapter) {
            return parent::temporaryUrl($path, $expires, $options);
        }

        $secretId  = $this->config['secret_id']  ?? null;
        $secretKey = $this->config['secret_key'] ?? null;

        if ($secretId === null || $secretKey === null) {
            throw new InvalidArgumentException(
                'Qcloud temporaryUrl requires secret_id and secret_key in the config.'
            );
        }

        $urlParams = $options['url_params'] ?? [];

        $useCdn       = ! empty($this->config['cdn']);
        $prefixedPath = $this->prefixer->prefixPath($path);

        if ($useCdn) {
            $baseUrl = rtrim($this->config['cdn'], '/') . '/' . ltrim($prefixedPath, '/');
        } else {
            $baseUrl = $adapter->getObjectClient()->getObjectUrl($prefixedPath);
        }

        $urlWithParams = $this->appendUrlParams($baseUrl, $urlParams);

        $request   = new Request('GET', $urlWithParams);
        $signature = new Signature($secretId, $secretKey);

        $signHeader = $signature->createAuthorizationHeader($request, $expires);

        $uri           = new Uri($urlWithParams);
        $existingQuery = $uri->getQuery();
        $signQuery     = http_build_query(['sign' => $signHeader]);
        $finalQuery    = $existingQuery === '' ? $signQuery : $existingQuery . '&' . $signQuery;

        return (string) $uri->withQuery($finalQuery);
    }
}
