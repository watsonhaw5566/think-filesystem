<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use OSS\Core\OssException;
use OSS\OssClient;
use watsonhaw\filesystem\Driver;
use yzh52521\Flysystem\Oss\OssAdapter;
use InvalidArgumentException;
use RuntimeException;

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
        $accessId     = $this->config['access_id']     ?? null;
        $accessSecret = $this->config['access_secret'] ?? null;
        $bucket       = $this->config['bucket']        ?? null;
        $isCName      = $this->config['isCName']       ?? null;

        if ($accessId === null || $accessSecret === null || $bucket === null || $isCName === null) {
            throw new InvalidArgumentException(
                'Aliyun driver requires access_id, access_secret, bucket and isCName in the config.'
            );
        }

        try {
            return new OssAdapter($this->config);
        } catch (OssException $e) {
            throw new RuntimeException(
                sprintf('Aliyun driver failed to initialize OssClient: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * 获取 OSS 临时签名 URL
     *
     * 与基类不同，此方法会把 $options['url_params'] 中能被 OSS SDK 签名白名单识别的参数
     * （例如 x-oss-process、response-content-type 等）传入 signUrl，使其参与签名以避免 403。
     * 白名单外的参数会在签名后追加到 URL 末尾（使用这些参数需自行承担签名校验失败风险）。
     *
     * @param string $path    资源路径
     * @param int    $expires 过期秒数
     * @param array  $options 支持 url_params 键追加查询参数
     * @return string
     */
    public function temporaryUrl(string $path, int $expires, array $options = []): string
    {
        $adapter = $this->unwrapAdapter($this->adapter);

        if (! $adapter instanceof OssAdapter) {
            return parent::temporaryUrl($path, $expires, $options);
        }

        $urlParams = $options['url_params'] ?? [];

        $signOptions  = [];
        $remainParams = [];

        if (is_array($urlParams)) {
            // OSS SDK generateQueryString 会处理的参数白名单（用于参与签名）
            $signedKeys = [
                OssClient::OSS_PART_NUM,
                'response-content-type',
                'response-content-language',
                'response-cache-control',
                'response-content-encoding',
                'response-expires',
                'response-content-disposition',
                OssClient::OSS_UPLOAD_ID,
                OssClient::OSS_COMP,
                OssClient::OSS_LIVE_CHANNEL_STATUS,
                OssClient::OSS_LIVE_CHANNEL_START_TIME,
                OssClient::OSS_LIVE_CHANNEL_END_TIME,
                OssClient::OSS_PROCESS,
                OssClient::OSS_POSITION,
                OssClient::OSS_SYMLINK,
                OssClient::OSS_RESTORE,
                OssClient::OSS_TAGGING,
                OssClient::OSS_WORM_ID,
                OssClient::OSS_TRAFFIC_LIMIT,
                OssClient::OSS_VERSION_ID,
                OssClient::OSS_CONTINUATION_TOKEN,
                'x-oss-process',
            ];

            foreach ($urlParams as $key => $value) {
                if (is_string($key) && in_array($key, $signedKeys, true)) {
                    $signOptions[$key] = $value;
                } else {
                    $remainParams[$key] = $value;
                }
            }
        } else {
            $remainParams = $urlParams;
        }

        $signedUrl = (string) $adapter->getTemporaryUrl($path, $expires, $signOptions);

        if (empty($remainParams)) {
            return $signedUrl;
        }

        return $this->appendUrlParams($signedUrl, $remainParams);
    }
}
