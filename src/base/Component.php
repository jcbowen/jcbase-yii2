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
        static::setProperties($this, $config);

        return $this;
    }

    /**
     * 批量设置对象属性
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param object $object 要设置的对象
     * @param array $properties 要设置的属性数组
     * @return object 设置后的对象
     *
     * @lasttime: 2024/7/24 下午3:22
     */
    public static function setProperties(object $object, array $properties): object
    {
        foreach ($properties as $name => $value) {
            // 检查属性是否存在
            if (property_exists($object, $name) || method_exists($object, '__set')) {
                $object->$name = $value;
            } else {
                Yii::error('Property "' . $name . '" does not exist in class "' . get_class($object) . '"', __METHOD__);
            }
        }

        return $object;
    }
}
