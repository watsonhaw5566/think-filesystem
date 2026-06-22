<?php

declare(strict_types=1);

namespace hulang\filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\Cache;
use think\File;
use think\file\UploadedFile;
use think\helper\Arr;
use voku\helper\ASCII;

/**
 * Class Driver
 * @package hulang\filesystem
 * @mixin Filesystem
 */
abstract class Driver
{

    /** @var Cache */
    protected $cache;

    /** @var Filesystem */
    protected $filesystem;

    protected $adapter;

    /**
     * The Flysystem PathPrefixer instance.
     *
     * @var PathPrefixer
     */
    protected $prefixer;

    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    public function __construct(Cache $cache, array $config)
    {
        $this->cache = $cache;
        $this->config = array_merge($this->config, $config);

        $separator = $config['directory_separator'] ?? DIRECTORY_SEPARATOR;
        $this->prefixer = new PathPrefixer($config['root'] ?? '', $separator);

        if (isset($config['prefix'])) {
            $this->prefixer = new PathPrefixer($this->prefixer->prefixPath($config['prefix']), $separator);
        }

        $this->adapter = $this->createAdapter();
        $this->filesystem = $this->createFilesystem($this->adapter, $this->config);
    }

    abstract protected function createAdapter(): FilesystemAdapter;

    /**
     * 根据配置创建Filesystem实例
     * 
     * 此方法主要用于根据传入的适配器和配置数组创建一个合适的Filesystem实例
     * 它允许将文件系统设置为只读,或者为路径添加前缀,并根据配置数组的特定参数配置Filesystem实例
     * 
     * @param FilesystemAdapter $adapter 文件系统适配器,用于与文件系统交互
     * @param array $config 配置数组,包含文件系统的配置信息,如读取模式和路径前缀等
     * @return Filesystem 返回配置好的Filesystem实例
     */
    protected function createFilesystem(FilesystemAdapter $adapter, array $config): Filesystem
    {
        // 如果配置中设置为只读，创建并使用只读文件系统适配器包装原始适配器
        if (($config['read-only'] ?? false) === true) {
            $adapter = new ReadOnlyFilesystemAdapter($adapter);
        }

        // 如果配置中设置了前缀，创建并使用路径前缀适配器包装原始适配器
        if (!empty($config['prefix'])) {
            $adapter = new PathPrefixedAdapter($adapter, $config['prefix']);
        }

        // 返回新的 Filesystem 实例，使用配置好的适配器和部分配置参数
        return new Filesystem($adapter, Arr::only($config, [
            'directory_visibility',
            'disable_asserts',
            'temporary_url',
            'url',
            'visibility',
        ]));
    }

    /**
     * 获取文件完整路径
     * 
     * 该方法接受一个相对路径作为参数,并返回一个完整的文件路径
     * 完整路径是通过前缀路径服务(prefixer)和提供的相对路径拼接而成
     * 此方法用于将应用程序中的相对文件路径转换为可用于文件操作的绝对路径
     * 
     * @param string $path 相对路径字符串,表示相对于某个基础路径的文件或目录位置
     * @return mixed|string 返回拼接前缀后的完整文件路径字符串
     */
    public function path(string $path): string
    {
        return $this->prefixer->prefixPath($path);
    }

    /**
     * 将给定的路径拼接到URL末尾
     * 
     * 该方法用于生成正确的URL格式,确保URL和路径可以完美拼接,不会出现多余的斜杠
     * 
     * @param string $url 基础URL,例如 "http://example.com"
     * @param string $path 要拼接的路径,例如 "resource"
     * 
     * @return mixed|string 拼接后的完整URL
     */
    protected function concatPathToUrl(string $url, string $path): string
    {
        return rtrim($url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 判断指定路径的资源是否存在
     *
     * @param string $path 要检查的路径
     * @return bool 如果路径存在,则返回true;否则返回false
     */
    public function exists(string $path): bool
    {
        return $this->filesystem->has($path);
    }

    /**
     * 判断指定路径的文件或目录是否缺失
     *
     * @param string $path 待检查的文件或目录路径
     * @return bool 如果文件或目录缺失,则返回true,否则返回false
     */
    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    /**
     * 检查文件是否存在于指定路径
     *
     * @param string $path 要检查的文件路径
     * @return bool 文件是否存在
     */
    public function fileExists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }

    /**
     * 检查文件是否缺失
     *
     * @param string $path 文件的路径
     * @return bool 文件是否缺失的布尔值
     */
    public function fileMissing(string $path): bool
    {
        return !$this->fileExists($path);
    }

    /**
     * 检查指定路径的目录是否存在
     *
     * @param string $path 要检查的目录路径
     * @return bool 目录存在返回true,否则返回false
     */
    public function directoryExists(string $path): bool
    {
        return $this->filesystem->directoryExists($path);
    }

    /**
     * 检查目录是否缺失
     *
     * @param string $path 目录路径
     * @return bool 目录是否缺失
     */
    public function directoryMissing(string $path): bool
    {
        return !$this->directoryExists($path);
    }

    /**
     * 读取指定路径的文件内容
     *
     * @param string $path 要读取的文件路径
     * @return string|null 文件内容字符串,如果读取成功;否则返回 null
     * @throws UnableToReadFile 如果文件无法读取,并且当前配置为抛出异常
     */
    public function get(string $path): ?string
    {
        try {
            return $this->filesystem->read($path);
        } catch (UnableToReadFile $e) {
            throw_if($this->throwsExceptions(), $e);
        }
        return null;
    }

    /**
     * 生成一个流式响应,用于下载或显示指定路径的文件
     *
     * @param string $path 文件的路径
     * @param string|null $name 可选,文件的名称,默认为null
     * @param array $headers HTTP头信息数组,默认为空数组
     * @param mixed|string|null $disposition 内容处置类型,可以是'inline'或'attachment',默认为'inline'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse 返回一个流式响应对象
     */
    public function response(string $path, ?string $name = null, array $headers = [], string $disposition = 'inline'): StreamedResponse
    {
        $response = new StreamedResponse;

        if (!array_key_exists('Content-Type', $headers)) {
            $headers['Content-Type'] = $this->mimeType($path);
        }

        if (!array_key_exists('Content-Length', $headers)) {
            $headers['Content-Length'] = $this->size($path);
        }

        if (!array_key_exists('Content-Disposition', $headers)) {
            $filename = $name ?? basename($path);

            $dispositionHeader = $response->headers->makeDisposition(
                $disposition,
                $filename,
                $this->fallbackName($filename)
            );

            $headers['Content-Disposition'] = $dispositionHeader;
        }

        $response->headers->replace($headers);

        $response->setCallback(function () use ($path) {
            $stream = $this->readStream($path);
            if (! is_resource($stream)) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        });

        return $response;
    }

    /**
     * 提供文件下载功能
     *
     * 该方法主要用于让用户下载指定的文件
     * 它通过流的方式响应文件下载请求,可以有效地减少内存使用,特别适用于大文件的下载
     * 支持自定义下载文件的显示名称及额外的HTTP头信息
     *
     * @param string $path 文件的路径,可以是本地路径或者一个可访问的URL
     * @param string|null $name 可选参数,指定下载时文件显示的名称,默认为null,即使用原文件名
     * @param array $headers 可选参数,一个包含HTTP头信息的数组,用于设置额外的响应头,默认为空数组
     * @return StreamedResponse 返回一个 StreamedResponse 对象,该对象负责实际的文件流传输
     */
    public function download(string $path, ?string $name = null, array $headers = []): StreamedResponse
    {
        return $this->response($path, $name, $headers, 'attachment');
    }

    /**
     * 处理文件名的 fallbackName 方法
     *
     * 将给定的名称转换为不包含任何 ASCII 转义字符的字符串
     * 主要用于清理和转换名称字段以备后续使用
     *
     * @param string $name 需要处理的名称
     * @return string 处理后的不含 ASCII 转义字符的名称
     */
    protected function fallbackName(string $name): string
    {
        return str_replace('%', '', ASCII::to_ascii($name, 'en'));
    }

    /**
     * 根据给定的路径,获取文件或目录的可见性(公开或私有)
     *
     * @param string $path 文件或目录的路径
     * @return mixed|string 返回'public'表示公开,返回'private'表示私有
     */
    public function getVisibility(string $path): string
    {
        // 检查路径的可见性，如果为 PUBLIC，则返回 'public'
        if ($this->filesystem->visibility($path) === Visibility::PUBLIC) {
            return 'public';
        }
        // 否则，返回 'private'
        return 'private';
    }

    /**
     * 设置文件系统的文件或目录的可见性
     *
     * @param string $path 文件或目录的路径
     * @param string $visibility 新的可见性设置
     * @return mixed|bool 成功设置可见性返回true,失败返回false
     *
     * 该方法尝试将给定路径的可见性设置为指定的值如果设置过程中发生无法设置可见性的错误,
     * 并且该类被配置为抛出异常,则会抛出此异常;否则,当发生错误时返回false
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        try {
            $this->filesystem->setVisibility($path, $visibility);
        } catch (UnableToSetVisibility $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 在文件的开头添加内容
     *
     * @param string $path 文件路径
     * @param string $data 要写入的数据
     * @param string $separator 分隔符，默认为换行符
     * @return bool 返回操作是否成功
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL): bool
    {
        if ($this->fileExists($path)) {
            return $this->put($path, $data . $separator . $this->get($path));
        }
        return $this->put($path, $data);
    }

    /**
     * 向文件追加数据
     *
     * @param string $path 文件路径
     * @param string $data 要写入或追加的数据
     * @param string $separator 分隔符,默认为系统换行符
     * @return bool 操作是否成功
     */
    public function append(string $path, string $data, string $separator = PHP_EOL): bool
    {
        if ($this->fileExists($path)) {
            return $this->put($path, $this->get($path) . $separator . $data);
        }
        return $this->put($path, $data);
    }

    /**
     * 删除一个或多个文件或目录
     *
     * @param string|array $paths 要删除的文件或目录的路径,可以是单个路径字符串或路径数组
     * @return bool 所有指定的文件或目录均成功删除则返回 true,否则返回 false
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : func_get_args();
        $success = true;

        foreach ($paths as $path) {
            try {
                $this->filesystem->delete($path);
            } catch (UnableToDeleteFile | UnableToDeleteDirectory $e) {
                throw_if($this->throwsExceptions(), $e);
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 复制文件或目录从一个路径到另一个路径
     *
     * @param string $from 源文件或目录的路径
     * @param string $to 目标文件或目录的路径
     * @return bool 返回 true 表示复制成功,否则返回 false
     */
    public function copy(string $from, string $to): bool
    {
        try {
            $this->filesystem->copy($from, $to);
        } catch (UnableToCopyFile $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 将文件或目录从一个位置移动到另一个位置
     *
     * @param string $from 移动前的路径
     * @param string $to 移动后的路径
     * @return bool 移动成功返回 true,失败返回 false
     */
    public function move(string $from, string $to): bool
    {
        try {
            $this->filesystem->move($from, $to);
        } catch (UnableToMoveFile $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 获取文件大小
     *
     * 此方法接受一个文件路径作为参数,并返回该文件的大小(以字节为单位)
     * 如果文件不存在或其他文件系统错误发生,则会抛出FilesystemException异常
     *
     * @param string $path 文件路径
     * @return mixed|int 文件大小(字节)
     * @throws FilesystemException 如果文件不存在或读取文件大小时发生错误
     */
    public function size(string $path): int
    {
        return $this->filesystem->fileSize($path);
    }

    /**
     * 获取指定路径文件的 MIME 类型
     *
     * @param string $path 文件路径
     * @return string|false 成功时返回文件的 MIME 类型字符串,失败时返回 false
     * @throws UnableToRetrieveMetadata 当无法获取 MIME 类型且配置为抛出异常时
     */
    public function mimeType(string $path): string|false
    {
        try {
            return $this->filesystem->mimeType($path);
        } catch (UnableToRetrieveMetadata $e) {
            throw_if($this->throwsExceptions(), $e);
        }
        return false;
    }

    /**
     * 获取文件或目录的最后修改时间
     *
     * @param string $path 需要查询的文件或目录的路径
     * @return int 返回文件或目录的最后修改时间,以时间戳形式表示
     */
    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified($path);
    }

    /**
     * 通过流读取指定路径的文件内容
     *
     * @param string $path 要读取的文件路径
     * @return mixed 成功时返回一个流资源,读取失败时返回 null
     * @throws UnableToReadFile 如果文件无法读取且当前配置为抛出异常时
     */
    public function readStream(string $path): mixed
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (UnableToReadFile $e) {
            throw_if($this->throwsExceptions(), $e);
        }
        return null;
    }

    /**
     * 使用流写入文件
     *
     * @param string $path 文件路径,包括文件名和可选的文件系统路径
     * @param mixed $resource 流资源,用于读取并写入到文件系统
     * @param array $options 可选参数数组,用于控制写入操作
     * @return bool 成功写入返回 true,发生错误返回 false
     * @throws UnableToWriteFile 当写入操作失败且配置为抛出异常时
     * @throws UnableToSetVisibility 当写入文件后尝试设置可见性失败时
     */
    public function writeStream(string $path, mixed $resource, array $options = []): bool
    {
        try {
            $this->filesystem->writeStream($path, $resource, $options);
        } catch (UnableToWriteFile | UnableToSetVisibility $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 获取本地URL
     * 
     * 此方法用于根据配置中的URL和给定的路径生成完整的URL
     * 如果配置中没有指定URL,则返回原始路径
     * 主要用于根据当前的配置信息,结合外部给定的路径,生成访问资源所需的完整URL这对于在有统一配置的情况下,
     * 根据路径动态生成访问地址非常有用如果配置中已经提供了URL,那么会将该URL与给定的路径拼接起来;否则,
     * 将直接返回给定的路径
     * 
     * @param string $path 要拼接到URL的路径这部分路径将被加到配置中给出的基础URL之后
     * 
     * @return mixed|string 完整的URL如果配置中没有提供URL,则返回原始路径
     */
    protected function getLocalUrl(string $path): string
    {
        if (isset($this->config['url'])) {
            return $this->concatPathToUrl($this->config['url'], $path);
        }
        return $path;
    }

    /**
     * 根据指定路径获取资源的 URL
     *
     * @param string $path 资源路径
     * @return string 资源的 URL
     * @throws \RuntimeException 如果无法获取 URL
     */
    public function url(string $path): string
    {
        $adapter = $this->adapter;

        if (method_exists($adapter, 'getUrl')) {
            return $adapter->getUrl($path);
        }

        if ($adapter instanceof LocalFilesystemAdapter) {
            return $this->getLocalUrl($path);
        }

        throw new \RuntimeException('This driver does not support retrieving URLs.');
    }

    /**
     * 替换基础 URL
     *
     * 解析给定的 URL 并替换 URI 对象的基础 URL 部分
     * 保留原始 URI 的路径和查询参数,仅替换协议、主机和端口
     *
     * @param object $uri URI 对象
     * @param string $url 新的基础 URL
     * @return object 返回一个新的 URI 对象,基础 URL 部分已被替换
     */
    protected function replaceBaseUrl(object $uri, string $url): object
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $uri;
        }

        if (isset($parsed['scheme'])) {
            $uri = $uri->withScheme($parsed['scheme']);
        }
        if (isset($parsed['host'])) {
            $uri = $uri->withHost($parsed['host']);
        }
        if (array_key_exists('port', $parsed)) {
            $uri = $uri->withPort($parsed['port']);
        } else {
            if (method_exists($uri, 'withPort')) {
                $uri = $uri->withPort(null);
            }
        }

        return $uri;
    }

    /**
     * 获取当前实例所使用的 Flysystem 文件系统操作器
     *
     * @return Filesystem 文件系统操作器实例
     */
    public function getDriver(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * 获取当前实例所使用的文件系统适配器
     *
     * @return FilesystemAdapter 文件系统适配器实例
     */
    public function getAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    /**
     * 保存文件
     * 
     * 此方法用于将文件保存到指定的路径
     * 它支持自定义文件名规则和传递额外的选项
     * 文件名规则可以是一个字符串、null、或者一个Closure对象,用于动态生成文件名
     * 
     * @param string $path 路径 保存文件的目录路径
     * @param File|string $file 文件 要保存的文件,可以是一个文件路径字符串或File对象
     * @param null|string|\Closure $rule 文件名规则 文件名的生成规则,可为空,默认为文件的哈希值
     * @param array $options 参数 额外的保存选项,例如存储类型或权限设置
     * @return mixed|bool|string 返回保存文件的结果,成功时返回文件名,失败时返回false
     */
    public function putFile(string $path, File|string $file, mixed $rule = null, array $options = []): string|false
    {
        if (is_string($file)) {
            $file = new File($file);
        }
        return $this->putFileAs($path, $file, $file->hashName($rule), $options);
    }

    /**
     * 指定文件名保存文件
     *
     * @param string $path 保存文件的目录路径
     * @param File $file 文件对象
     * @param string $name 保存的文件名
     * @param array $options 额外参数
     * @return string|false 返回保存的文件路径或失败时返回 false
     */
    public function putFileAs(string $path, File $file, string $name, array $options = []): string|false
    {
        $realPath = $file->getRealPath();
        $stream   = $realPath !== false ? fopen($realPath, 'r') : false;
        if ($stream === false) {
            return false;
        }

        $path = trim($path . '/' . $name, '/');

        $result = $this->put($path, $stream, $options);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $result ? $path : false;
    }

    /**
     * 将数据写入指定路径的文件中
     *
     * @param string $path 要写入的文件路径
     * @param mixed $contents 要写入的文件内容,可以是字符串、资源、实现了 FileInterface 的实例或 StreamInterface 的实例
     * @param mixed $options 写入操作的选项,可以是字符串(表示文件可见性)或数组类型
     *
     * @return bool 表示写入操作是否成功
     * @throws UnableToWriteFile 如果写入文件时发生错误且配置为抛出异常
     * @throws UnableToSetVisibility 如果设置文件可见性时发生错误且配置为抛出异常
     */
    public function put(string $path, mixed $contents, mixed $options = []): bool
    {
        $options = is_string($options) ? ['visibility' => $options] : (array) $options;

        if ($contents instanceof File || $contents instanceof UploadedFile) {
            return (bool) $this->putFile($path, $contents, $options);
        }

        try {
            if ($contents instanceof StreamInterface) {
                $this->writeStream($path, $contents->detach(), $options);
                return true;
            }

            if (is_resource($contents)) {
                if ($this->writeStream($path, $contents, $options) === false) {
                    return false;
                }
            } else {
                $this->write($path, $contents, $options);
            }
        } catch (UnableToWriteFile | UnableToSetVisibility $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 获取指定目录中的所有文件列表
     * 
     * 该方法使用递归方式获取目录中的文件
     * 如果指定了目录,则返回该目录及其子目录中的所有文件;如果未指定目录,则返回根目录及其子目录中的所有文件
     * 返回的列表按路径排序
     * 
     * @param string|null $directory 可选参数,指定要获取文件的目录路径.如果未提供,将从根目录开始
     * @param bool $recursive 指定是否递归获取目录中的文件,默认为 false,即不递归
     * @return mixed|array 返回包含所有文件路径的数组
     */
    public function files(?string $directory = null, bool $recursive = false): array
    {
        return $this->filesystem->listContents($directory ?? '', $recursive)
            ->filter(function (StorageAttributes $attributes) {
                return $attributes->isFile();
            })
            ->sortByPath()
            ->map(function (StorageAttributes $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * 获取指定目录下的所有文件（递归）
     *
     * @param string|null $directory 可选参数,指定要获取文件的目录路径,默认为当前目录
     * @return array 返回包含目录下所有文件的数组
     */
    public function allFiles(?string $directory = null): array
    {
        return $this->files($directory, true);
    }

    /**
     * 获取目录中的所有子目录
     *
     * @param string|null $directory 要列出其子目录的目录路径,如果为 null,则表示当前目录
     * @param bool $recursive 是否递归地列出子目录,默认为 false
     * @return array 包含目录路径的数组
     */
    public function directories(?string $directory = null, bool $recursive = false): array
    {
        return $this->filesystem->listContents($directory ?? '', $recursive)
            ->filter(function (StorageAttributes $attributes) {
                return $attributes->isDir();
            })
            ->map(function (StorageAttributes $attributes) {
                return $attributes->path();
            })
            ->toArray();
    }

    /**
     * 获取所有目录（递归）
     *
     * @param string|null $directory 可选参数,指定要搜索的目录
     * @return array 返回一个包含所有子目录的数组
     */
    public function allDirectories(?string $directory = null): array
    {
        return $this->directories($directory, true);
    }

    /**
     * 创建目录
     *
     * @param string $path 需要创建的目录的路径
     * @return bool 目录创建成功返回 true,否则返回 false
     */
    public function makeDirectory(string $path): bool
    {
        try {
            $this->filesystem->createDirectory($path);
        } catch (UnableToCreateDirectory | UnableToSetVisibility $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 删除一个目录及其内容
     *
     * @param string $directory 待删除的目录路径
     * @return bool 目录删除成功返回 true,否则返回 false
     * @throws UnableToDeleteDirectory 当目录无法删除且配置为抛出异常时
     */
    public function deleteDirectory(string $directory): bool
    {
        try {
            $this->filesystem->deleteDirectory($directory);
        } catch (UnableToDeleteDirectory $e) {
            throw_if($this->throwsExceptions(), $e);
            return false;
        }
        return true;
    }

    /**
     * 判断是否抛出异常
     *
     * @return bool true 表示允许抛出异常,false 表示不允许
     */
    protected function throwsExceptions(): bool
    {
        return (bool) ($this->config['throw'] ?? false);
    }

    /**
     * 动态调用未定义的方法
     *
     * 允许通过动态方法调用的方式访问 Filesystem 类中的方法
     *
     * @param string $method 动态调用的方法名
     * @param array $parameters 调用方法时传递的参数数组
     * @return mixed 返回 Filesystem 类中对应方法的执行结果
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->filesystem->$method(...$parameters);
    }
}