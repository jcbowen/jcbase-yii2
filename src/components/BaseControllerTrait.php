<?php

namespace Jcbowen\JcbaseYii2\components;

trait BaseControllerTrait
{
    /** @var array 每个控制器禁止访问的action */
    public $denyAction = [];
}
