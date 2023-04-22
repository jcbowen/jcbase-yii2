<?php

namespace Jcbowen\JcbaseYii2\base;

use Yii;

class Component extends \yii\base\Component
{
    /**
     * 配置组件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $config
     * @return $this
     * @lasttime: 2023/4/22 12:03 PM
     */
    public function config(array $config = []): Component
    {
        if (!empty($config))
            Yii::configure($this, $config);

        return $this;
    }
}
