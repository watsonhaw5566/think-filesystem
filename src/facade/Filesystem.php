<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\facade;

use think\Facade;
use watsonhaw\filesystem\Driver;

/**
 * Class Filesystem
 * @package think\facade
 * @mixin \watsonhaw\filesystem\Filesystem
 * @method static Driver disk(?string $name = null)
 * @method static Driver cloud(?string $name = null)
 * @method static mixed getConfig(?string $name = null, mixed $default = null)
 * @method static mixed getDiskConfig(string $disk, ?string $name = null, mixed $default = null)
 * @method static mixed getDefaultDriver()
 */
class Filesystem extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'filesystem';
    }
}