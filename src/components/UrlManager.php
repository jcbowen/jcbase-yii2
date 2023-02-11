<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\BaseObject;
use yii\web\UrlRuleInterface;

/**
 *
 * Class urlManager
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lastTime 2021/12/17 11:26 下午
 * @package common\components
 */
class UrlManager extends BaseObject implements UrlRuleInterface
{

    public $isModule = false;
    public $moduleName = '';

    /**
     * {@inheritdoc}
     */
    public function parseRequest($manager, $request)
    {
        global $_GPC;

        $pathInfo = trim($request->getPathInfo(), '/');

        // 客户端应用采用history模式，此处拦截所有非客户端的请求
        if (IN_CLIENT) {
            $clientWhiteRoute = Yii::$app->params['clientWhiteRoute'] ?? [];
            // 匹配$pathInfo是否在白名单中(白名单为正则表达式)
            $pass = false;
            foreach ($clientWhiteRoute as $route) {
                if (preg_match('/' . $route . '/', $pathInfo)) {
                    $pass = true;
                    break;
                }
            }

            if (!$pass) {
                if (empty(Yii::$app->request->headers->get('JcClient'))) {
                    if (!YII_DEBUG)
                        return ['index/index', $_GPC];
                    elseif (empty($_GPC['JcClient']))
                        return ['index/index', $_GPC];

                }
            }
        }

        $pathInfo_arr = explode('/', $pathInfo);
        if (Yii::$app->hasModule($pathInfo_arr[0])) {
            $this->isModule   = true;
            $this->moduleName = array_shift($pathInfo_arr);
            $defaultRoute     = self::getDefaultRoute();
            if (empty($pathInfo_arr)) $pathInfo_arr = [$defaultRoute, $defaultRoute];
        }

        $action = array_pop($pathInfo_arr);
        $method = $this->makeAction($action);
        $path   = implode('\\', $pathInfo_arr);
        // 常规
        $controller = $this->makeController($path);
        if (count($pathInfo_arr) > 0 && method_exists($controller, $method)) {
            $pathInfo_arr[] = $action;
            $route          = $this->isModule ? $this->moduleName . '/' . implode('/', $pathInfo_arr) : $pathInfo;
            return [$route, $_GPC];
        }
        // 将接口写到IndexController中时，隐藏index
        $controller = $this->makeController($path, true, $pathInfo);
        if (method_exists($controller, $method)) {
            $pathInfo_arr[] = $action;
            $route          = $pathInfo;
//            $route          = $this->isModule ? $this->moduleName . '/' . implode('/', $pathInfo_arr) : $pathInfo;
            return [$route, $_GPC];
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function createUrl($manager, $route, $params)
    {
        return false;
    }

    /**
     * Function makeController
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @TIMESTAMP: 2021/4/16 11:37 下午
     * @param bool $hasIndex
     * @param string $pathInfo
     * @param $path
     * @return string
     */
    private function makeController($path, bool $hasIndex = false, string &$pathInfo = ''): string
    {
        $defaultRoute = self::getDefaultRoute();
        $nameSpace    = !$this->isModule ?
            Yii::$app->controllerNamespace :
            Yii::$app->getModule($this->moduleName)->controllerNamespace;

        if ($hasIndex) {
            $controller     = rtrim($nameSpace . '\\' . $path, '\\') . '\\' . ucfirst($defaultRoute) . 'Controller';
            $pathInfo_arr   = explode('/', $pathInfo);
            $action         = array_splice($pathInfo_arr, -1)[0];
            $pathInfo_arr[] = 'index';
            $pathInfo_arr[] = $action;
            $pathInfo       = implode('/', $pathInfo_arr);
            return $controller;

        }
        if (empty($path)) $path = $defaultRoute;
        $path_arr        = explode('\\', $path);
        $action          = array_splice($path_arr, -1)[0];
        $path_arr[]      = ucfirst($action);
        $path            = implode('\\', $path_arr);
        $controller_name = $path . 'Controller';
        return $nameSpace . '\\' . $controller_name;
    }

    /**
     * Function makeAction
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $action
     * @return string
     */
    private function makeAction($action): string
    {
        if (empty($action)) $action = self::getDefaultRoute();
        $route_str = 'action';
        if (Util::strExists($action, '-')) {
            $route_arr = explode('-', $action);
            foreach ($route_arr as $item) {
                $route_str .= ucfirst($item);
            }
        } else {
            $route_str .= ucfirst($action);
        }
        return $route_str;
    }

    /**
     * @return string
     */
    private static function getDefaultRoute(): string
    {
        return Yii::$app->defaultRoute;
    }
}