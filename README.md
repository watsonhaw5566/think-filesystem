<h2><p align="center">think-filesystem</p></h2>
<p align="center">thinkphp 8.0.0+ 的文件系统扩展包</p>
<p align="center">支持本地文件系统、阿里云OSS、腾讯云COS</p>

#### 环境

1. php >= 8.0.2
2. thinkphp >= 8.0.0

#### 支持

1. 本地（Local）
2. 阿里云 OSS
3. 腾讯云 COS

#### 安装

第一步：

```shell
composer require watsonhaw/think-filesystem
```

第二步： 在config/filesystem.php中添加配置

##### Local 本地驱动

Local 驱动用于将文件存储在服务器的本地文件系统中，完整配置如下：

```php
return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'local'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root'   => app()->getRuntimePath() . 'storage',
        ],
        'public' => [
            // 磁盘类型
            'type'       => 'local',
            // 磁盘路径
            'root'       => app()->getRootPath() . 'public/storage',
            // 磁盘路径对应的外部URL路径
            'url'        => '/storage',
            // 可见性
            'visibility' => 'public',
        ],
        // 更多的磁盘配置信息
    ],
];
```

`local` 磁盘用于存储不对外公开的文件，存储在 `runtime/storage` 目录下；`public` 磁盘用于存储需要公开访问的文件，存储在 `public/storage` 目录下，并通过 `/storage` URL 访问。

##### 公有云驱动

```php
return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'aliyun'),
    // 磁盘列表
    'disks'   => [
        'aliyun' => [
            'type' => 'aliyun',
            'access_id' => '******',
            'access_secret' => '******',
            'bucket' => 'bucket',
            'endpoint' => 'oss-cn-hongkong.aliyuncs.com',
            'isCName' => true,
            'cdnUrl' => '',
            'prefix' => '',
            'options' => [
                'endpoint' => '',
                'bucket_endpoint' => '',
            ],
        ],
        'qcloud' => [
            'type' => 'qcloud',
            'region' => '***', //bucket 所属区域 英文
            'app_id' => '***', // 域名中数字部分
            'secret_id' => '***',
            'secret_key' => '***',
            'bucket' => '***',
            'timeout' => 60,
            'connect_timeout' => 60,
            'cdn' => '您的 CDN 域名',
            'scheme' => 'https',
            'read_from_cdn' => false,
        ]
    ],
];
```

第三步： 开始使用

##### demo

```php
$file = $this->request->file('image');
try {
    validate(
        [
            'image' => [
                // 限制文件大小（单位 byte），这里限制为 4MB
                'fileSize' => 4 * 1024 * 1024,
                // 限制文件后缀，多个后缀以英文逗号分割
                'fileExt'  => 'gif,jpg,png,jpeg',
            ],
        ]
    )->check(['image' => $file]);

    $path = \watsonhaw\filesystem\facade\Filesystem::disk('public')->putFile('test', $file);
    $url  = \watsonhaw\filesystem\facade\Filesystem::disk('public')->url($path);

    return json(['path' => $path, 'url' => $url]);
} catch (\think\exception\ValidateException $e) {
    echo $e->getMessage();
}
```

##### Local 驱动使用示例

```php
use watsonhaw\filesystem\facade\Filesystem;

// 使用默认的 local 磁盘
Filesystem::disk('local')->put('example.txt', 'Hello, think-filesystem!');
echo Filesystem::disk('local')->get('example.txt');

// 使用 public 磁盘(需要在配置中定义)
$path = Filesystem::disk('public')->putFile('images', $file);
// 获取文件的可访问 URL
$url = Filesystem::disk('public')->url($path); // /storage/images/xxx.jpg

// 获取文件的绝对路径
$absolutePath = Filesystem::disk('local')->path('example.txt');

// 设置文件可见性
Filesystem::disk('public')->setVisibility('secret.txt', 'private');
echo Filesystem::disk('public')->getVisibility('secret.txt'); // private

// 目录操作
Filesystem::disk('local')->makeDirectory('docs');
$files = Filesystem::disk('local')->files('docs');
$dirs = Filesystem::disk('local')->directories('docs');
```

#### 检索文件
> get  方法可用于检索文件的内容。该方法将返回文件的原始字符串内容。
 切记，所有文件路径的指定都应该相对于该磁盘所配置的「root」目录：

```php
$contents = Filesystem::get('file.jpg');
```

>exists 方法可以用来判断一个文件是否存在于磁盘上：
```php
if (Filesystem::disk('local')->exists('file.jpg')) {
    // ...
}
```

>missing 方法可以用来判断一个文件是否缺失于磁盘上：
```php
if (Filesystem::disk('local')->missing('file.jpg')) {
    // ...
}
```

#### 下载文件

>download 方法可以用来生成一个响应，强制用户的浏览器下载给定路径的文件。
download 方法接受一个文件名作为方法的第二个参数，这将决定用户下载文件时看到的文件名。最后，你可以传递一个 HTTP 头部的数组作为方法的第三个参数：

```php
return Filesystem::download('file.jpg');

return Filesystem::download('file.jpg', $name, $headers);
```

#### 文件 URL

>你可以使用 url 方法来获取给定文件的 URL。如果你使用的是 local 驱动，这通常只会在给定路径前加上 /storage，并返回一个相对 URL。如果你使用的是 aliyun / qcloud 这类云驱动，将返回完全限定的远程 URL：
```php
$url = Filesystem::url('file.jpg');
```

>通过 `url()` 方法的第二个参数 `$options['url_params']`，可以在生成的 URL 后追加自定义查询参数。这对于阿里云 OSS、腾讯云 COS 等公有云对象存储的「图片处理」「视频截帧」接口非常有用，不需要修改文件本身，直接通过 URL 参数即可实现实时处理：

```php
use watsonhaw\filesystem\facade\Filesystem;

// ============ 阿里云 OSS 图片处理 ============
// 文档：https://help.aliyun.com/zh/oss/user-guide/img-parameters

// 方式一：字符串形式，按 OSS 文档拼接好即可
$thumb = Filesystem::disk('aliyun')->url('images/a.jpg', [
    'url_params' => 'x-oss-process=image/resize,m_fill,w_200,h_200/format,webp/quality,80',
]);

// 方式二：key=>value 形式，url_params 会被自动作为 query 追加
$thumb = Filesystem::disk('aliyun')->url('images/a.jpg', [
    'url_params' => [
        'x-oss-process' => 'image/resize,m_fill,w_200,h_200/format,webp',
    ],
]);

// ============ 腾讯云 COS 图片处理 ============
// 文档：https://cloud.tencent.com/document/product/436/44870

// COS 图片处理参数的 key 本身包含斜杠（如 imageMogr2/thumbnail/200x200!），无对应 value
// 推荐使用「数字索引数组」形式传入原始字符串，框架不会对它做任何编码
$thumb = Filesystem::disk('qcloud')->url('images/a.jpg', [
    'url_params' => [
        'imageMogr2/thumbnail/200x200!/format/webp/quality/80',
    ],
]);

// 等价写法：使用空值 key => '' 形式
$thumb = Filesystem::disk('qcloud')->url('images/a.jpg', [
    'url_params' => ['imageMogr2/thumbnail/200x200!/format/webp/quality/80' => ''],
]);
```

>除了图片处理，任何需要追加到 URL 的查询参数都可以通过 `url_params` 传入，支持以下三种形式：
>1. **字符串**：原样作为 query 追加（例如 `'a=1&b=2'`）
>2. **关联数组**：正常的 `key => value`，会做 `urlencode` 后追加
>3. **数字索引数组**：视为已拼好的原始 query 片段，原样追加不做编码，适合 COS 图片处理这类需要特殊 key 的场景

#### 临时签名 URL

>对于私有读写权限的 Bucket，直接使用 `url()` 生成的 URL 访问会被云厂商返回 403 拒绝。需要使用 `temporaryUrl()` 方法生成**带签名**的临时访问链接，该链接仅在指定的过期时间内有效：

```php
use watsonhaw\filesystem\facade\Filesystem;

// 生成 1 小时内有效的临时访问 URL
$url = Filesystem::disk('aliyun')->temporaryUrl('private/report.pdf', 3600);

$url = Filesystem::disk('qcloud')->temporaryUrl('private/report.pdf', 3600);
```

>`temporaryUrl()` 同样支持 `url_params`，用于图片处理等场景。**重要：本扩展已保证图片处理参数会一并参与签名计算**，避免出现「先签名再追加参数导致签名不匹配、仍然 403」的常见问题：

```php
// OSS 私有 Bucket：x-oss-process 会自动参与签名，访问时不会 403
$thumb = Filesystem::disk('aliyun')->temporaryUrl('images/a.jpg', 3600, [
    'url_params' => ['x-oss-process' => 'image/resize,w_200,h_200/format,webp'],
]);

// COS 私有 Bucket：imageMogr2 参数会自动参与签名
$thumb = Filesystem::disk('qcloud')->temporaryUrl('images/a.jpg', 3600, [
    'url_params' => ['imageMogr2/thumbnail/200x200!/format/webp'],
]);
```

> 注：Local 本地驱动不支持临时签名 URL，私有文件请自行实现权限控制。

#### 文件元数据
>除了读写文件，还可以提供有关文件本身的信息。例如，size 方法可用于获取文件大小（以字节为单位）：
```php
$size = Filesystem::size('file.jpg');
```

>lastModified 方法返回上次修改文件时的时间戳：
```php
$time = Filesystem::lastModified('file.jpg');
```

>可以通过 mimeType 方法获取给定文件的 MIME 类型：
```php
$mime = Filesystem::mimeType('file.jpg')
```

#### 文件路径
>你可以使用 path 方法获取给定文件的路径。如果你使用的是 local 驱动，这将返回文件的绝对路径。如果你使用的是 aliyun 驱动，此方法将返回 aliyun 存储桶中文件的相对路径：

```php
$path = Filesystem::path('file.jpg');
```

#### 保存文件
>可以使用 put 方法将文件内容存储在磁盘上。你还可以将 PHP resource 传递给 put 方法，该方法将使用 Flysystem 的底层流支持。请记住，应相对于为磁盘配置的「根」目录指定所有文件路径：
```php
Filesystem::put('file.jpg', $contents);

Filesystem::put('file.jpg', $resource);
```
#### 写入失败

>如果 put 方法（或其他「写入」操作）无法将文件写入磁盘，将返回 false。
```php
if (! Filesystem::put('file.jpg', $contents)) {
    // 该文件无法写入磁盘...
}
```
>你可以在你的文件系统磁盘的配置数组中定义 throw 选项。当这个选项被定义为 true 时，「写入」的方法如 <code>put</code> 将在写入操作失败时抛出一个 League\Flysystem\UnableToWriteFile 的实例。

```php
'public' => [
    'type' => 'local',
    // ...
    'throw' => true,
],
```

#### 追加内容到文件开头或结尾
>prepend 和 append 方法允许你将内容写入文件的开头或结尾：
```php
Filesystem::prepend('file.log', 'Prepended Text');

Filesystem::append('file.log', 'Appended Text');
```
#### 复制/移动文件
>copy 方法可用于将现有文件复制到磁盘上的新位置，而 move 方法可用于重命名现有文件或将其移动到新位置：

```php
Filesystem::copy('old/file.jpg', 'new/file.jpg');

Filesystem::move('old/file.jpg', 'new/file.jpg');
```
#### 自动流式传输
>将文件流式传输到存储位置可显著减少内存使用量。如果你希望 thinkphp 自动管理将给定文件流式传输到你的存储位置，你可以使用 putFile 或 putFileAs 方法。此方法接受一个 think\File 或 think\file\UploadedFile 实例，并自动将文件流式传输到你所需的位置：

```php
use think\File;

// 为文件名自动生成一个唯一的 ID...
$path = Filesystem::putFile('photos', new File('/path/to/photo'));

// 手动指定一个文件名...
$path = Filesystem::putFileAs('photos', new File('/path/to/photo'), 'photo.jpg');
```

>关于 putFile 方法有几点重要的注意事项。注意，我们只指定了目录名称而不是文件名。默认情况下，putFile 方法将生成一个唯一的 ID 作为文件名。文件的扩展名将通过检查文件的 MIME 类型来确定。文件的路径将由 putFile 方法返回，因此你可以将路径（包括生成的文件名）存储在数据库中。

>putFile 和 putFileAs 方法还支持通过第 4 个参数 `$options` 传入存储选项（例如可见性、SDK 写参数等）。如果你希望上传到云盘的文件默认公开访问，可以传入 `visibility => public`：

```php
use think\File;

// putFile 参数顺序：目录, 上传文件, 文件名规则(null=自动生成唯一ID), 选项数组
$path = Filesystem::putFile(
    'photos',
    new File('/path/to/photo'),
    null,
    ['visibility' => 'public']
);
```

#### 删除文件
>delete 方法接收一个文件名或一个文件名数组来将其从磁盘中删除：
```php
Filesystem::delete('file.jpg');

Filesystem::delete(['file.jpg', 'file2.jpg']);
```
如果需要，你可以指定应从哪个磁盘删除文件。
```php
Filesystem::disk('aliyun')->delete('path/file.jpg');
```
 #### 目录
 ##### 获取目录下所有的文件
>files 将以数组的形式返回给定目录下所有的文件。如果你想要检索给定目录的所有文件及其子目录的所有文件，你可以使用 allFiles 方法：
```php
$files = Filesystem::files($directory);
$files = Filesystem::allFiles($directory);
```
##### 获取特定目录下的子目录
>directories 方法以数组的形式返回给定目录中的所有目录。此外，你还可以使用
allDirectories 方法递归地获取给定目录中的所有目录及其子目录中的目录：

```php
$directories = Filesystem::directories($directory);
$directories = Filesystem::allDirectories($directory);
```
##### 创建目录
>makeDirectory 方法可递归的创建指定的目录:

```php
Filesystem::makeDirectory($directory);
```
##### 删除一个目录
>最后，deleteDirectory 方法可用于删除一个目录及其下所有的文件：
```php
Filesystem::deleteDirectory($directory);
```
#### 自定义文件系统
>你可以在 系统服务 中注册一个带有 boot 方法的驱动。在提供者的 boot 方法中，你可以使用 Filesystem 门面的 extend 方法来定义一个自定义驱动：

```php
use League\Flysystem\Filesystem as FlysystemFilesystem;
use think\App;
use think\Service;
use watsonhaw\filesystem\facade\Filesystem;

// 示例：接入一个自定义驱动（以假设的 ftp 适配器为例，实际需自行安装对应 flysystem 适配器包并保证与 league/flysystem 3.x 兼容）
// use League\Flysystem\Ftp\FtpAdapter;
// use League\Flysystem\Ftp\FtpConnectionOptions;

class AppService extends Service
{
    public function boot()
    {
        Filesystem::extend('ftp', function (App $app, array $config) {
            // $adapter = new FtpAdapter(FtpConnectionOptions::fromArray($config));
            // 闭包必须返回 League\Flysystem\Filesystem 实例
            return new FlysystemFilesystem($adapter, $config);
        });
    }
}
```
extend 方法的第一个参数是驱动程序的名称，第二个参数是接收 `$app` 和 `$config` 变量的闭包。闭包必须返回 `League\Flysystem\Filesystem` 的实例。`$config` 变量包含 `config/filesystems.php` 中为该磁盘定义的全部配置。
#### 授权

MIT

#### 感谢

1. thinkphp
2. yuanzhihai/think-filesystem
3. league/flysystem
4. overtrue/flysystem-cos
5. hulang/think-filesystem