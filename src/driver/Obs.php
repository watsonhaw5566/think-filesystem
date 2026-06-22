<?php

declare(strict_types=1);

namespace watsonhaw\filesystem\driver;

use watsonhaw\filesystem\Driver;
use yzh52521\Flysystem\Obs\ObsAdapter;

class Obs extends Driver
{
    /**
     * 创建 OBS 适配器实例
     *
     * 该方法负责实例化 ObsAdapter，并将当前类的配置信息传递给它
     * 主要用于内部实现存储或处理文件操作的适配层创建
     *
     * @return ObsAdapter 返回一个使用当前配置初始化的 ObsAdapter 实例
     */
    protected function createAdapter(): ObsAdapter
    {
        return new ObsAdapter($this->config);
    }
}