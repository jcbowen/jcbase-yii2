<?php

namespace Jcbowen\JcbaseYii2\base;

use Jcbowen\JcbaseYii2\components\BaseControllerTrait;
use Jcbowen\JcbaseYii2\components\CurdActionTrait;
use Jcbowen\JcbaseYii2\components\Template;
use Jcbowen\JcbaseYii2\components\Util;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use yii\web\Response;

/**
 * Class WebController
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2021/12/18 1:15 上午
 * @package Jcbowen\JcbaseYii2\base
 */
class WebController extends Controller
{
    use BaseControllerTrait;
    use CurdActionTrait;

    public function init()
    {
        global $_B;

        parent::init();

        define('MTIME', microtime());
        define('TIMESTAMP', time());
        define('TIME', date('Y-m-d H:i:s', TIMESTAMP));

        //----- 初始化附件域名配置 -----/
        if (empty(Yii::$app->params['domain']['attachment_local'])) {
            Yii::$app->params['domain']['attachment_local'] = Util::getSiteRoot();
        }
        if (empty(Yii::$app->params['domain']['attachment'])) {
            Yii::$app->params['domain']['attachment'] = Yii::$app->params['domain']['attachment_local'];
        }

        //----- 默认参数，及将参数配置写到全局变量中 -----/
        $_B['page']   = ['title' => 'jcsoft'];
        $_B['params'] = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
    }

    public function vTpl($filename, $flag = TEMPLATE_DISPLAY)
    {
        return (new Template)->vTpl($filename, $flag);
    }
}
