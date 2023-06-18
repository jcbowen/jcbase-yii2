<?php

namespace Jcbowen\JcbaseYii2\components;

use Jcbowen\JcbaseYii2\base\WebController;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;

/**
 * Class Template
 * 模版引擎
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/9/27 21:23
 * @package Jcbowen\JcbaseYii2\components
 */
class Template extends Component
{
    /** @var string 模板文件后缀 */
    public $suffix = 'html';
    /** @var string 应用路径 */
    public $appPath = '';
    /** @var string jcbase源码路径 */
    public $jcbaseSrcPath = '';
    /** @var string 模板文件根目录（模板目录的上级目录） */
    public $viewPath = '';
    /** @var string 编译文件根目录 */
    public $compilePath = '';

    /** @var array 传递的变量(需要作用到模板文件中的变量；一般通过get_defined_vars()获取) */
    public $variables = [];
    /** @var WebController web控制器 */
    public $controller;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $this->variables = !empty($this->variables) && is_array($this->variables) ? $this->variables : [];

        if (isset($this->controller) && !$this->controller instanceof WebController)
            throw new InvalidArgumentException('Template组件的controller属性必须是WebController的实例');

        // 初始化
        $this->appPath       = $this->appPath ? Yii::getAlias(rtrim($this->appPath, '/')) : Yii::getAlias('@app');
        $this->jcbaseSrcPath = $this->jcbaseSrcPath ? Yii::getAlias(rtrim($this->jcbaseSrcPath, '/')) : Yii::getAlias('@vendor/jcbowen/jcbase-yii2/src');
        $this->viewPath      = $this->viewPath ? Yii::getAlias(rtrim($this->viewPath, '/')) : $this->appPath . '/views';
        $this->compilePath   = $this->compilePath ? Yii::getAlias(rtrim($this->compilePath, '/')) : $this->appPath . '/runtime/tpl';
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $filename 模板文件名（含路径，不含后缀，模板文件只能为html文件）
     * @param int $flag 使用标识
     * @return false|string|void
     * @throws Exception
     * @lasttime: 2022/9/27 21:14
     */
    public function template(?string $filename = null, int $flag = TEMPLATE_DISPLAY)
    {
        global $_B;

        $filename       = $filename ?? Yii::$app->controller->route;
        $_B['template'] = $_B['template'] ?: 'default';
        $doesNotExist   = [];

        // 根据当前模版的指定文件名查找
        $source  = "$this->viewPath/{$_B['template']}/$filename.$this->suffix";
        $compile = "$this->compilePath/{$_B['template']}/$filename.tpl.php";

        // 根据当前模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = "$this->viewPath/{$_B['template']}/$filename/index.$this->suffix";
            $compile        = "$this->compilePath/{$_B['template']}/$filename/index.tpl.php";
        }

        // 根据默认模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = "$this->viewPath/default/$filename.$this->suffix";
            $compile        = "$this->compilePath/default/$filename.tpl.php";
        }

        // 根据默认模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = "$this->viewPath/default/$filename/index.$this->suffix";
            $compile        = "$this->compilePath/default/$filename/index.tpl.php";
        }

        // ----- 查找jcbase中是否有默认模板，Begin ----- /
        // 根据当前模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/{$_B['template']}/$filename.$this->suffix";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/{$_B['template']}/$filename.tpl.php";
        }

        // 根据当前模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/{$_B['template']}/$filename/index.$this->suffix";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/{$_B['template']}/$filename/index.tpl.php";
        }

        // 根据默认模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/default/$filename.$this->suffix";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/default/$filename.tpl.php";
        }

        // 根据默认模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/default/$filename/index.$this->suffix";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/default/$filename/index.tpl.php";
        }
        // ----- 查找jcbase中是否有默认模板，End ----- /

        if (!is_file($source)) {
            $doesNotExist[] = $source;
            if (YII_DEBUG) {
                echo PHP_EOL;
                foreach ($doesNotExist as $item) echo "template source '$item' is not exist!" . PHP_EOL;
                die;
            }
            exit("template source '$filename' is not exist!");
        }

        if (YII_DEBUG || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            $this->template_compile($source, $compile);
        }

        switch ($flag) {
            case TEMPLATE_DISPLAY:
            default:
                extract($this->variables + $GLOBALS, EXTR_SKIP);
                include $compile;
                return $compile;
            case TEMPLATE_FETCH:
                extract($this->variables + $GLOBALS, EXTR_SKIP);
                ob_flush();
                ob_clean();
                ob_start();
                include $compile;
                $contents = ob_get_contents();
                ob_clean();
                return $contents;
            case TEMPLATE_INCLUDEPATH:
                return $compile;
        }
    }

    /**
     * 渲染前端编译后文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $filename 模板文件名（含路径，不含后缀，模板文件只能为html文件）
     * @param int $flag 使用标识
     * @return false|string|void
     * @throws Exception
     * @lasttime: 2022/9/27 21:10
     */
    public function vTpl(?string $filename = 'index', int $flag = TEMPLATE_DISPLAY)
    {
        $filename = $filename ?: 'index';

        $source  = $this->appPath . "/web/$filename.$this->suffix";
        $compile = $this->appPath . "/runtime/vtpl/$filename.tpl.php";

        // if (!is_file($source)) $source = $this->appPath . "/web/$filename/index.$this->suffix";
        // if (!is_file($source)) $source = $this->appPath . "/web/dist/$filename/index.$this->suffix";
        if (!is_file($source)) $source = $this->appPath . "/web/dist/$filename.$this->suffix";

        if (!is_file($source)) {
            if (YII_DEBUG) exit("template source '$source' is not exist!");
            exit("template source '$filename' is not exist!");
        }
        if (YII_DEBUG || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            $this->template_compile($source, $compile);
        }

        switch ($flag) {
            case TEMPLATE_DISPLAY:
            default:
                extract($this->variables + $GLOBALS, EXTR_SKIP);
                include $compile;
                return $compile;
            case TEMPLATE_FETCH:
                extract($this->variables + $GLOBALS, EXTR_SKIP);
                ob_flush();
                ob_clean();
                ob_start();
                include $compile;
                $contents = ob_get_contents();
                ob_clean();
                return $contents;
            case TEMPLATE_INCLUDEPATH:
                return $compile;
        }
    }

    /**
     * 模板编译
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $from 模板文件
     * @param string $to 编译文件
     * @throws Exception
     * @lasttime: 2023/4/15 9:50 AM
     */
    public function template_compile(string $from, string $to)
    {
        $path = dirname($to);
        if (!is_dir($path))
            FileHelper::createDirectory($path);

        $content = $this->template_parse(file_get_contents($from));
        file_put_contents($to, $content);
    }

    /**
     * 模板解析
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $str
     * @return string
     * @lasttime: 2023/4/15 9:50 AM
     */
    public function template_parse($str): string
    {
        $str = preg_replace('/<!--{(.+?)}-->/s', '{$1}', $str);
        $str = preg_replace('/{php\s+(.+?)}/', '<?php $1 ?>', $str);
        $str = preg_replace('/{if\s+(.+?)}/', '<?php if($1) { ?>', $str);
        $str = preg_replace('/{else}/', '<?php } else { ?>', $str);
        $str = preg_replace('/{else ?if\s+(.+?)}/', '<?php } else if($1) { ?>', $str);
        $str = preg_replace('/{\/if}/', '<?php } ?>', $str);
        $str = preg_replace('/{foreach\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2) { ?>', $str);
        $str = preg_replace('/{foreach\s+(\S+)\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2 => $3) { ?>', $str);
        $str = preg_replace('/{\/foreach}/', '<?php } } ?>', $str);
        $str = preg_replace('/{for\s+(.+?)}/', '<?php for($1) { ?>', $str);
        $str = preg_replace('/{\/for}/', '<?php } ?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\[\]\'\"\$]*)}/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/{toMedia\s+(\S+)}/', '<?php echo \Jcbowen\JcbaseYii2\components\Util::toMedia($1); ?>', $str);
        $str = preg_replace_callback('/<\?php([^\?]+)\?>/s', "\Jcbowen\JcbaseYii2\components\Template::templateAddQuote", $str);
        $str = preg_replace(
            '/<jc_tpl_php>(.+?)<\/jc_tpl_php>/',
            '<?php include (new \Jcbowen\JcbaseYii2\components\Template(["controller" => $this->controller, "variables" => $this->variables, "viewPath" => $this->viewPath, "compilePath" => $this->compilePath, "suffix" => $this->suffix]))->template($1, TEMPLATE_INCLUDEPATH); ?>',
            $str
        );
        $str = preg_replace(
            '/{template\s+(.+?)}/',
            '<?php include (new \Jcbowen\JcbaseYii2\components\Template(["controller" => $this->controller, "variables" => $this->variables, "viewPath" => $this->viewPath, "compilePath" => $this->compilePath, "suffix" => $this->suffix]))->template($1, TEMPLATE_INCLUDEPATH); ?>',
            $str
        );
        $str = preg_replace('/{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)}/s', '<?php echo $1; ?>', $str);
        $str = str_replace('{##', '{', $str);
        $str = str_replace('##}', '}', $str);

        // 如果初始化的时候传递了controller，那么就可以使用controller的方法
        if ($this->controller instanceof WebController) {
            if (Util::strExists($str, '{controllerCall')) {
                $str = preg_replace('/{controllerCall\s+(\S+)}/',
                    '<?php call_user_func([$this->controller, \'$1\']); ?>',
                    $str);
                $str = preg_replace('/{controllerCall\s+(\S+)\s+(\S+)}/',
                    '<?php call_user_func_array([$this->controller, \'$1\'], [$2]); ?>',
                    $str);
                $str = preg_replace('/{controllerCall\s+(\S+)\s+(\S+)\s+(\S+)}/',
                    '<?php call_user_func_array([$this->controller, \'$1\'], [$2, $3]); ?>',
                    $str);
            }
            if (Util::strExists($str, '{controller')) {
                $str = preg_replace('/{controller\s+(\S+)}/',
                    '<?php echo call_user_func([$this->controller, \'$1\']); ?>',
                    $str);
                $str = preg_replace('/{controller\s+(\S+)\s+(\S+)}/',
                    '<?php echo call_user_func_array([$this->controller, \'$1\'], [$2]); ?>',
                    $str);
                $str = preg_replace('/{controller\s+(\S+)\s+(\S+)\s+(\S+)}/',
                    '<?php echo call_user_func_array([$this->controller, \'$1\'], [$2, $3]); ?>',
                    $str);
            }
        }

        return /*"<?php defined('IN_JC') or exit('Access Denied');?>" .*/ $str;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $matches
     * @return array|string|string[]
     * @lasttime: 2023/4/15 9:49 AM
     */
    public static function templateAddQuote($matches)
    {
        $code = "<?php $matches[1] ?>";
        $code = preg_replace('/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\](?![a-zA-Z0-9_\-\.\x7f-\xff\[\]]*[\'"])/s', "['$1']", $code);

        return str_replace('\\\"', '\"', $code);
    }
}