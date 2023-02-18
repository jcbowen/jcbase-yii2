<?php

namespace Jcbowen\JcbaseYii2\base;

use Jcbowen\JcbaseYii2\components\BaseControllerTrait;
use Jcbowen\JcbaseYii2\components\CurdActionTrait;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Safe;
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
    public $allowAction = [];

    public function init()
    {
        global $_B, $_GPC;

        parent::init();

        define('MTIME', microtime());
        define('TIMESTAMP', time());
        define('TIME', date('Y-m-d H:i:s', TIMESTAMP));
        define('TODAY', date('Y-m-d', TIMESTAMP));

        //----- 初始化附件域名配置 -----/
        if (empty(Yii::$app->params['domain']['attachment_local']))
            Yii::$app->params['domain']['attachment_local'] = Util::getSiteRoot();

        if (empty(Yii::$app->params['domain']['attachment']))
            Yii::$app->params['domain']['attachment'] = Yii::$app->params['domain']['attachment_local'];

        //----- 默认参数，及将参数配置写到全局变量中 -----/
        $_B['page']       = ['title' => 'jcsoft'];
        $_B['params']     = ArrayHelper::merge((array)$_B['params'], Yii::$app->params);
        $_B['JcClient']   = Yii::$app->request->headers->get('JcClient') ? Yii::$app->request->headers->get('jcclient') : 1; // 客户端类型
        $_B['EnvVersion'] = Yii::$app->request->headers->get('EnvVersion') ? Yii::$app->request->headers->get('envversion') : 'development'; // 开发环境 development, production
        $_B['RELEASE']    = Yii::$app->request->headers->get('RELEASE') ? Yii::$app->request->headers->get('release') : '1.0.0'; // 版本号

        // ----- 全局变量$_GPC赋值 -----/
        $_GPC     = $_GPC ?? [];
        $res      = new yii\web\Request();
        $getData  = (array)$res->get();
        $postData = (array)$res->post();
        if (!empty($getData)) $_GPC = array_merge($_GPC, $getData);
        if (!empty($postData)) $_GPC = array_merge($_GPC, $postData);
        if (strpos($res->getContentType(), 'application/json') !== false)
            $_GPC = array_merge($_GPC, (array)json_decode($res->getRawBody(), true));
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $msg
     * @param string $redirect
     * @param string $type
     * @return string|Response
     * @lasttime: 2023/2/1 4:04 PM
     */
    public function resultMessage($msg, string $redirect = '', string $type = '')
    {
        global $_B;
        if ($redirect == 'refresh') {
            $_B['siteRoot'] = Util::getSiteRoot();
            $redirect       = $_B['siteRoot'] . '.' . $_B['script_name'] . '?' . $_SERVER['QUERY_STRING'];
        }
        if ($redirect == 'referer') {
            $redirect = Util::getReferer();
        }
        // $redirect = Safe::gpcUrl($redirect);

        if ($redirect == '')
            $type = Safe::gpcBelong($type, ['success', 'error', 'info', 'warning', 'ajax', 'sql'], 'info');
        else
            $type = Safe::gpcBelong($type, ['success', 'error', 'info', 'warning', 'ajax', 'sql'], 'success');

        if (Yii::$app->getRequest()->getIsAjax() || !empty($_GET['isAjax']) || $type == 'ajax') {
            $r_errno = ($type == 'success') ? ErrCode::SUCCESS : ErrCode::UNKNOWN;
            $r_data  = ($redirect) ? ['re_url' => $redirect] : '';
            return $this->result($r_errno, $msg, $r_data);
        }

        if (empty($msg) && !empty($redirect))
            return $this->redirect($redirect);

        $label = $type;
        if ($type == 'error') $label = 'danger';

        if ($type == 'sql') $label = 'warning';

        include (new Template)->template('common/message', TEMPLATE_INCLUDEPATH);
        return (new Util)->resultHtml();
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

        if (!empty($this->denyAction) && in_array($action->id, $this->denyAction))
            throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));

        return true;
    }

    /**
     * @Deprecated 已废弃
     */
    public function vTpl(?string $filename = 'index'): Response
    {
        (new Template)->vTpl($filename);
        return (new Util)->resultHtml();
    }

    /**
     * @Deprecated 已废弃
     */
    public function template(?string $filename = null): Response
    {
        (new Template)->template($filename);
        return (new Util)->resultHtml();
    }

    /**
     * 输出json结构数据到Response中
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

    /**
     * 输出json字符串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|integer $errCode
     * @param string $errmsg
     * @param mixed $data
     * @param array $params
     * @return string|Response
     * @lasttime: 2023/1/13 1:33 PM
     */
    public function result_r($errCode = '0', string $errmsg = '', $data = [], array $params = [])
    {
        return $this->result($errCode, $errmsg, $data, $params, 'return');
    }

    /**
     * 将error数组转换Response输出
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param mixed $error
     * @return string|Response
     * @lasttime: 2023/1/13 1:35 PM
     */
    public function resultError($error = [])
    {
        return (new Util)->resultError($error);
    }
}
