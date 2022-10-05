<?php

namespace Jcbowen\JcbaseYii2\components;

class Component extends \yii\base\Component implements \ArrayAccess
{
    /**
     * 实现ArrayAccess接口offsetExists
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $offset
     * @return bool
     * @lasttime: 2022/10/5 11:06
     */
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * 实现ArrayAccess接口offsetGet
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $offset
     * @return mixed
     * @lasttime: 2022/10/5 11:06
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * 实现ArrayAccess接口offsetSet
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $offset
     * @param mixed $value
     * @lasttime: 2022/10/5 11:07
     */
    public function offsetSet($offset, $value)
    {
        return $this->$offset = $value;
    }

    /**
     * 实现ArrayAccess接口offsetUnset
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $offset
     * @lasttime: 2022/10/5 11:07
     */
    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }
}
