<?php

namespace Jcbowen\JcbaseYii2\base;

use Jcbowen\JcbaseYii2\components\BaseControllerTrait;
use Jcbowen\JcbaseYii2\components\CurdActionTrait;
use Jcbowen\JcbaseYii2\components\Template;
use Jcbowen\JcbaseYii2\components\Util;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
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

    /**
     * 只允许访问allowAction中的action，其他的将报错404
     * 如果设置了denyAction，则allowAction无效
     * @var array 允许访问的action
     */
    protected $allowAction = [];

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
        $_B['page']     = ['title' => 'jcsoft'];
        $_B['params']   = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
        $_B['JcClient'] = Yii::$app->request->headers->get('JcClient', 1);
    }

    /**
     * {@inheritdoc}
     * @throws NotFoundHttpException|BadRequestHttpException
     */
    public function beforeAction($action)
    {
        $beforeAction = parent::beforeAction($action);

        if (!$beforeAction) return false;

        if (empty($this->denyAction) && !empty($this->allowAction)) {
            $actions          = [
                'list',
                'detail',
                'loader',
                'create',
                'update',
                'set-value',
                'save',
                'delete',
                'restore',
                'remove',
            ];
            $this->denyAction = array_filter($actions, function ($item) {
                return !in_array($item, $this->allowAction);
            });
        }

        if (!empty($this->denyAction) && in_array($action->id, $this->denyAction)) {
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
        }

        return true;
    }

    public function vTpl(?string $filename = 'index'): Response
    {
        (new Template)->vTpl($filename);
        return (new Util)->resultHtml();
    }

    public function template(?string $filename = null): Response
    {
        (new Template)->template($filename);
        return (new Util)->resultHtml();
    }

    /**
     * 输出json结构数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|integer $errCode
     * @param string $errmsg
     * @param mixed $data
     * @param array $params
     * @param string $returnType
     * @return string|Response
     * @lasttime: 2022/8/28 23:17
     */
    public function result($errCode = '0', string $errmsg = '', $data = [], array $params = [], string $returnType = 'exit')
    {
        return (new Util)->result($errCode, $errmsg, $data, $params, $returnType);
    }

    public function result_r($errcode = '0', $errmsg = '', $data = [], $params = [])
    {
        return $this->result($errcode, $errmsg, $data, $params, 'return');
    }

    public function resultError($error = [])
    {
        return (new Util)->resultError($error);
    }
}
