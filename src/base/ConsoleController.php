<?php

namespace Jcbowen\JcbaseYii2\base;

use Jcbowen\JcbaseYii2\components\BaseControllerTrait;
use Jcbowen\JcbaseYii2\components\ModelHelper;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;

/**
 * Class ConsoleController
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/8/28 23:13
 * @package Jcbowen\JcbaseYii2\base
 */
class ConsoleController extends Controller
{
    use BaseControllerTrait;
    use ModelHelper;

    public function init()
    {
        global $_B;

        parent::init();

        //----- 默认参数，及将参数配置写到全局变量中 -----/
        $_B['page']   = ['title' => 'jcsoft'];
        $_B['params'] = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
    }
}
