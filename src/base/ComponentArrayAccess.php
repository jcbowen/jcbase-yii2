<?php

namespace Jcbowen\JcbaseYii2\base;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use ReflectionClass;
use ReflectionProperty;
use yii\base\ArrayableTrait;

class ComponentArrayAccess extends Component implements ArrayAccess, IteratorAggregate
{
    use ArrayableTrait;

    // ----- 辅助使用方法 ----- /

    /**
     * 以数组形式加载数据到属性
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     * @param string $formName
     * @return bool
     * @lasttime: 2024/6/26 11:42
     */
    public function load(array $data, string $formName = ''): bool
    {
        $scope = $formName ?? '';
        if ($scope === '' && !empty($data)) {
            $this->setAttributes($data);
            return true;
        } elseif (isset($data[$scope])) {
            $this->setAttributes($data[$scope]);
            return true;
        }
        return false;
    }

    /**
     * 批量设置属性值
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $values 格式：[name => value]
     * @lasttime: 2024/6/26 11:40
     */
    public function setAttributes(array $values)
    {
        $attributes = array_flip($this->attributes());
        foreach ($values as $name => $value)
            if (isset($attributes[$name]))
                $this->$name = $value;
    }

    /**
     * 返回属性名称列表（只返回非静态的公共属性）
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return string[]
     * @lasttime: 2024/6/26 11:38
     */
    public function attributes(): array
    {
        $names = [];
        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
            if (!$property->isStatic())
                $names[] = $property->getName();
        return $names;
    }

    // ----- ArrayAccess 必要方法 ----- /

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        return $this->$offset = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }
}
