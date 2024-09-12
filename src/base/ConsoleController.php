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
 * @author  Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/8/28 23:13
 * @package Jcbowen\JcbaseYii2\base
 */
class ConsoleController extends Controller
{
    use BaseControllerTrait;
    use ModelHelper;

    /** @var array 命令行参数 */
    protected $CommandLineParams;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        global $_B;

        $this->CommandLineParams = $this->getCommandLineParams();

        parent::init();

        //----- 默认参数，及将参数配置写到全局变量中 -----/
        $_B['page']   = ['title' => 'jcbase'];
        $_B['params'] = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
    }

    /**
     * 获取命令行参数
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array
     */
    public function getCommandLineParams(): array
    {
        $rawParams = [];
        if (isset($_SERVER['argv'])) {
            $rawParams = $_SERVER['argv'];
            array_shift($rawParams);
        }

        $params = [];
        foreach ($rawParams as $param) {
            if (preg_match('/^--([\w-]*\w)(=(.*))?$/', $param, $matches)) {
                $name          = $matches[1];
                $params[$name] = $matches[3] ?? true;
            } else {
                $params[] = $param;
            }
        }
        return $params;
    }
}
