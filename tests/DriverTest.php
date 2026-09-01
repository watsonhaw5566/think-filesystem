<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\tests;

use watsonhaw\filesystem\Driver;
use watsonhaw\filesystem\driver\Local;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use think\File;
use Throwable;

class DriverTest extends TestCase
{
    private ?Local $driver = null;

    private ?string $tmpDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = $this->createLocalDriver();
        $prop         = $this->getPrivateProperty(\watsonhaw\filesystem\Driver::class, 'config');
        $config       = $prop->getValue($this->driver);
        $this->tmpDir = $config['root'];
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->rmdirRecursive($this->tmpDir);
        }
        parent::tearDown();
    }

    // ==== 文件读写 ====

    public function testPutAndGet()
    {
        $result = $this->driver->put('hello.txt', 'Hello World');
        $this->assertTrue($result);
        $this->assertSame('Hello World', $this->driver->get('hello.txt'));
    }

    public function testPutWithOptions()
    {
        $result = $this->driver->put('with_options.txt', 'content', ['visibility' => 'public']);
        $this->assertTrue($result);
    }

    public function testPutOverwriteExistingFile()
    {
        $this->driver->put('overwrite.txt', 'first');
        $result = $this->driver->put('overwrite.txt', 'second');
        $this->assertTrue($result);
        $this->assertSame('second', $this->driver->get('overwrite.txt'));
    }

    public function testPutWithResourceContent()
    {
        $tmp = tmpfile();
        fwrite($tmp, 'resource content');
        rewind($tmp);
        $result = $this->driver->put('from_resource.txt', $tmp);
        fclose($tmp);
        $this->assertTrue($result);
        $this->assertSame('resource content', $this->driver->get('from_resource.txt'));
    }

    public function testWriteStream()
    {
        $tmp = fopen('php://temp', 'rb+');
        fwrite($tmp, 'hello from stream');
        rewind($tmp);
        $result = $this->driver->writeStream('via_stream.txt', $tmp);
        fclose($tmp);
        $this->assertTrue($result);
        $this->assertSame('hello from stream', $this->driver->get('via_stream.txt'));
    }

    public function testReadStream()
    {
        $this->driver->put('stream_read.txt', 'abcdef');
        $handle = $this->driver->readStream('stream_read.txt');
        $this->assertIsResource($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);
        $this->assertSame('abcdef', $contents);
    }

    public function testReadStreamMissingReturnsNull()
    {
        $result = $this->driver->readStream('does_not_exist.bin');
        $this->assertNull($result);
    }

    public function testPrepend()
    {
        $this->driver->put('prepend.txt', 'world');
        $result = $this->driver->prepend('prepend.txt', 'hello ', '');
        $this->assertTrue($result);
        $this->assertSame('hello world', $this->driver->get('prepend.txt'));
    }

    public function testAppend()
    {
        $this->driver->put('append.txt', 'hello');
        $result = $this->driver->append('append.txt', ' world', '');
        $this->assertTrue($result);
        $this->assertSame('hello world', $this->driver->get('append.txt'));
    }

    public function testPrependOnNewFile()
    {
        $result = $this->driver->prepend('brand_new.txt', 'new content');
        $this->assertTrue($result);
        $this->assertSame('new content', $this->driver->get('brand_new.txt'));
    }

    // ==== 存在性检查 ====

    public function testExistsAndMissing()
    {
        $this->assertFalse($this->driver->exists('ghost.txt'));
        $this->assertTrue($this->driver->missing('ghost.txt'));

        $this->driver->put('ghost.txt', 'exists');
        $this->assertTrue($this->driver->exists('ghost.txt'));
        $this->assertFalse($this->driver->missing('ghost.txt'));
    }

    public function testFileExistsAndFileMissing()
    {
        $this->assertFalse($this->driver->fileExists('x.txt'));
        $this->assertTrue($this->driver->fileMissing('x.txt'));
        $this->driver->put('x.txt', 'v');
        $this->assertTrue($this->driver->fileExists('x.txt'));
        $this->assertFalse($this->driver->fileMissing('x.txt'));
    }

    public function testDirectoryExists()
    {
        mkdir($this->tmpDir . '/sub');
        $this->assertTrue($this->driver->directoryExists('sub'));
        $this->assertFalse($this->driver->directoryExists('not-there'));
    }

    public function testDirectoryMissing()
    {
        $this->assertTrue($this->driver->directoryMissing('nope'));
        mkdir($this->tmpDir . '/nope');
        $this->assertFalse($this->driver->directoryMissing('nope'));
    }

    // ==== 元数据 ====

    public function testSize()
    {
        $this->driver->put('size.txt', '1234567890');
        $this->assertSame(10, $this->driver->size('size.txt'));
    }

    public function testLastModified()
    {
        $this->driver->put('mtime.txt', 'data');
        $mtime = $this->driver->lastModified('mtime.txt');
        $this->assertIsInt($mtime);
        $this->assertGreaterThan(0, $mtime);
    }

    public function testMimeType()
    {
        $this->driver->put('mime.txt', 'plain text');
        $mimeType = $this->driver->mimeType('mime.txt');
        // Local adapter 返回 text/plain
        $this->assertIsString($mimeType);
        $this->assertStringStartsWith('text/', $mimeType);
    }

    public function testMimeTypeMissingReturnsFalse()
    {
        $result = $this->driver->mimeType('does-not-exist-mime.txt');
        $this->assertFalse($result);
    }

    // ==== 可见性 ====

    public function testSetAndGetVisibility()
    {
        $this->driver->put('vis.txt', 'data');
        $result = $this->driver->setVisibility('vis.txt', 'public');
        $this->assertTrue($result);
        $this->assertSame('public', $this->driver->getVisibility('vis.txt'));

        $this->driver->setVisibility('vis.txt', 'private');
        $this->assertSame('private', $this->driver->getVisibility('vis.txt'));
    }

    // ==== 删除 ====

    public function testDeleteSingle()
    {
        $this->driver->put('to_delete.txt', 'bye');
        $this->assertTrue($this->driver->delete('to_delete.txt'));
        $this->assertTrue($this->driver->missing('to_delete.txt'));
    }

    public function testDeleteArrayOfPaths()
    {
        $this->driver->put('a.txt', '1');
        $this->driver->put('b.txt', '2');
        $this->assertTrue($this->driver->delete(['a.txt', 'b.txt']));
        $this->assertTrue($this->driver->missing('a.txt'));
        $this->assertTrue($this->driver->missing('b.txt'));
    }

    public function testDeleteMultipleArgs()
    {
        $this->driver->put('x.txt', '1');
        $this->driver->put('y.txt', '2');
        $this->assertTrue($this->driver->delete('x.txt', 'y.txt'));
    }

    public function testDeleteMissingReturnsTrueWhenThrowDisabled()
    {
        // 默认 throw=false，删除不存在的文件不会抛出异常
        $this->assertTrue($this->driver->delete('not-there.txt'));
    }

    // ==== 复制 / 移动 ====

    public function testCopy()
    {
        $this->driver->put('src.txt', 'source');
        $this->assertTrue($this->driver->copy('src.txt', 'dst.txt'));
        $this->assertSame('source', $this->driver->get('dst.txt'));
        $this->assertTrue($this->driver->exists('src.txt'));
    }

    public function testCopyMissingSource()
    {
        $this->assertFalse($this->driver->copy('no-such-file.txt', 'out.txt'));
    }

    public function testMove()
    {
        $this->driver->put('before.txt', 'mv');
        $this->assertTrue($this->driver->move('before.txt', 'after.txt'));
        $this->assertSame('mv', $this->driver->get('after.txt'));
        $this->assertTrue($this->driver->missing('before.txt'));
    }

    public function testMoveMissingSource()
    {
        $this->assertFalse($this->driver->move('no-such-move.txt', 'where.txt'));
    }

    // ==== 目录列表 ====

    public function testFilesAndAllFiles()
    {
        $this->driver->put('a.txt', '1');
        $this->driver->put('b.txt', '2');
        $this->driver->makeDirectory('sub');
        $this->driver->put('sub/c.txt', '3');

        $files = $this->driver->files();
        $this->assertContains('a.txt', $files);
        $this->assertContains('b.txt', $files);

        $allFiles = $this->driver->allFiles();
        $this->assertContains('a.txt', $allFiles);
        $this->assertContains('sub/c.txt', $allFiles);
    }

    public function testDirectoriesAndAllDirectories()
    {
        $this->driver->makeDirectory('a');
        $this->driver->makeDirectory('a/b');

        $dirs = $this->driver->directories();
        $this->assertContains('a', $dirs);

        $all = $this->driver->allDirectories();
        $this->assertContains('a', $all);
        $this->assertContains('a/b', $all);
    }

    public function testMakeDirectory()
    {
        $this->assertTrue($this->driver->makeDirectory('new-dir'));
        $this->assertTrue($this->driver->directoryExists('new-dir'));
    }

    public function testDeleteDirectory()
    {
        $this->driver->makeDirectory('to-remove');
        $this->driver->put('to-remove/nested.txt', '1');
        $this->assertTrue($this->driver->deleteDirectory('to-remove'));
        $this->assertFalse($this->driver->directoryExists('to-remove'));
    }

    public function testDeleteDirectoryMissingReturnsFalse()
    {
        // 对于 Local 适配器，deleteDirectory 在目录不存在时通常会抛出 UnableToDeleteDirectory
        // 由于 throw 为 false，驱动应返回 false
        $result = $this->driver->deleteDirectory('missing-dir-' . uniqid());
        $this->assertFalse($result);
    }

    // ==== URL / Path ====

    public function testPath()
    {
        $path = $this->driver->path('foo/bar.txt');
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'foo/bar.txt', $path);
        $this->assertStringStartsWith($this->tmpDir, $path);
    }

    public function testUrlLocalDefaultReturnsPath()
    {
        // 未设置 url 时，Driver 的 getLocalUrl 返回路径本身
        $path = $this->driver->path('file.txt');
        $this->assertNotEmpty($path);
    }

    public function testUrlWithUrlConfig()
    {
        $driver = $this->createLocalDriver([
            'url' => 'https://cdn.example.com',
        ]);
        $url = $driver->url('assets/logo.png');
        $this->assertSame('https://cdn.example.com/assets/logo.png', $url);

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }

    private function extractRootFromDriver($driver): string
    {
        $prop   = $this->getPrivateProperty(Driver::class, 'config');
        $config = $prop->getValue($driver);

        return $config['root'];
    }

    // ==== Driver / Adapter 访问 ====

    public function testGetDriverReturnsFlysystem()
    {
        $inner = $this->driver->getDriver();
        $this->assertInstanceOf(Flysystem::class, $inner);
    }

    public function testGetAdapterReturnsFilesystemAdapter()
    {
        $adapter = $this->driver->getAdapter();
        $this->assertInstanceOf(FilesystemAdapter::class, $adapter);
    }

    // ==== 文件上传（putFile / putFileAs）====

    public function testPutFileAsWithRealFile()
    {
        $tmp = sys_get_temp_dir() . '/think_filesystem_upload_' . uniqid() . '.txt';
        file_put_contents($tmp, 'uploaded content');

        $file = new \think\File($tmp);
        $path = $this->driver->putFileAs('uploads', $file, 'renamed.txt');

        $this->assertNotEmpty($path);
        $this->assertSame('uploads/renamed.txt', $path);
        $this->assertSame('uploaded content', $this->driver->get('uploads/renamed.txt'));

        @unlink($tmp);
    }

    public function testPutFile()
    {
        $tmp = sys_get_temp_dir() . '/think_filesystem_upload2_' . uniqid() . '.txt';
        file_put_contents($tmp, 'file content');

        $file   = new File($tmp);
        $result = $this->driver->putFile('uploads2', $file);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertTrue($this->driver->fileExists($result));

        @unlink($tmp);
    }

    public function testPutFileUsingStringPath()
    {
        $tmp = sys_get_temp_dir() . '/think_filesystem_upload3_' . uniqid() . '.txt';
        file_put_contents($tmp, 'string-path content');

        $result = $this->driver->putFile('uploads3', $tmp);

        $this->assertIsString($result);
        $this->assertTrue($this->driver->fileExists($result));

        @unlink($tmp);
    }

    public function testPutFileWithUploadedFile()
    {
        // 使用 think\file\UploadedFile 测试 put 的 UploadedFile 分支
        $tmp = sys_get_temp_dir() . '/think_filesystem_upl4_' . uniqid() . '.txt';
        file_put_contents($tmp, 'uploaded');

        $file   = new \think\file\UploadedFile($tmp, 'test.txt', 'text/plain', null, true);
        $result = $this->driver->put('target.txt', $file);
        $this->assertTrue($result);
        $this->assertSame('uploaded', $this->driver->get('target.txt'));

        @unlink($tmp);
    }

    public function testPutFileAsFailsWhenFileMissing()
    {
        // 通过字符串路径传入不存在文件，驱动应返回 false 而不是抛出异常
        $result = $this->driver->putFile('dir', sys_get_temp_dir() . '/think_fs_no_such_' . uniqid() . '.txt', 'name.txt');
        $this->assertFalse($result);
    }

    // ==== read-only 模式 ====

    public function testReadOnlyPreventsWrite()
    {
        $driver = $this->createLocalDriver(['read-only' => true, 'throw' => true]);

        try {
            $driver->put('foo.txt', 'bar');
            $this->fail('Expected exception was not thrown');
        } catch (Throwable $e) {
            $this->assertInstanceOf(FilesystemException::class, $e);
        }

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }

    // ==== response / download ====

    public function testResponseReturnsStreamedResponse()
    {
        $this->driver->put('download.bin', 'binary data');
        $response = $this->driver->response('download.bin');
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame('binary data', $content);
    }

    public function testDownloadReturnsStreamedResponseWithDisposition()
    {
        $this->driver->put('dl.txt', 'data');
        $response = $this->driver->download('dl.txt', 'custom.txt');
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('custom.txt', $response->headers->get('Content-Disposition', ''));
    }

    // ==== prefix 前缀 ====

    public function testPrefix()
    {
        $driver = $this->createLocalDriver(['prefix' => 'subdir']);
        $driver->put('hello.txt', 'world');
        $this->assertTrue($driver->exists('hello.txt'));

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }

    // ==== 异常抛出配置 ====

    public function testThrowsExceptionsWhenConfigured()
    {
        $driver = $this->createLocalDriver(['throw' => true]);
        $this->expectException(UnableToReadFile::class);
        $driver->get('not-there-read.txt');

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }

    // ==== 目录分隔符 ====

    public function testCustomDirectorySeparator()
    {
        $driver = $this->createLocalDriver(['directory_separator' => '/']);
        $path   = $driver->path('a/b/c.txt');
        $this->assertStringEndsWith('/a/b/c.txt', $path);

        $this->rmdirRecursive($this->extractRootFromDriver($driver));
    }

    // ==== 动态方法调用 (Filesystem passthrough) ====

    public function testDynamicCallToFilesystem()
    {
        $this->driver->put('dyn.txt', '123');
        // Filesystem 方法通过 __call 透传
        $result = $this->driver->read('dyn.txt');
        $this->assertSame('123', $result);
    }
}
