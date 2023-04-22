<?php

namespace Jcbowen\JcbaseYii2\base;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;

class ComponentArrayAccess extends Component implements ArrayAccess, IteratorAggregate
{
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
