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

        $_B['page'] = ['title' => 'jcsoft'];

        $_B['params'] = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
    }

    public function vTpl($filename, $flag = TEMPLATE_DISPLAY)
    {
        return (new Template)->vTpl($filename, $flag);
    }

    /**
     * 输出json结构数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:18 上午
     * @param string $errmsg 错误信息
     * @param mixed $data 返回内容
     * @param array $params 补充参数
     * @param string $returnType
     *
     * @param string|int $errCode 错误码，其中0为正确
     * @return string|Response
     */
    public function result($errCode = '0', $errmsg = '', $data = [], $params = [], $returnType = 'exit')
    {
        return (new Util)->result($errCode, $errmsg, $data, $params, $returnType);
    }

    public function result_r($errcode = '0', $errmsg = '', $data = [], $params = [])
    {
        return $this->result($errcode, $errmsg, $data, $params, 'return');
    }
}
