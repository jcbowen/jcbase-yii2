<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\Exception;
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
class Template
{
    public $appPath = '';
    public $jcbaseSrcPath = '';

    /**
     * Template constructor.
     */
    public function __construct()
    {
        $this->appPath       = Yii::getAlias('@app');
        $this->jcbaseSrcPath = Yii::getAlias('@vendor/jcbowen/jcbase-yii2/src');
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $filename 模板文件名（含路径，不含后缀，模板文件只能为html文件）
     * @param int $flag 使用标识
     * @param array $variables 当前作用域的变量，一般通过get_defined_vars()获取
     * @return false|string|void
     * @lasttime: 2022/9/27 21:14
     */
    public function template(?string $filename = null, int $flag = TEMPLATE_DISPLAY, array $variables = [])
    {
        global $_B;

        $filename       = $filename ?? Yii::$app->controller->route;
        $_B['template'] = $_B['template'] ?: 'default';
        $doesNotExist   = [];

        // 根据当前模版的指定文件名查找
        $source  = $this->appPath . "/views/{$_B['template']}/$filename.html";
        $compile = $this->appPath . "/runtime/tpl/{$_B['template']}/$filename.tpl.php";

        // 根据当前模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->appPath . "/views/{$_B['template']}/$filename/index.html";
            $compile        = $this->appPath . "/runtime/tpl/{$_B['template']}/$filename/index.tpl.php";
        }

        // 根据默认模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->appPath . "/views/default/$filename.html";
            $compile        = $this->appPath . "/runtime/tpl/default/$filename.tpl.php";
        }

        // 根据默认模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->appPath . "/views/default/$filename/index.html";
            $compile        = $this->appPath . "/runtime/tpl/default/$filename/index.tpl.php";
        }

        // ----- 查找jcbase中是否有默认模板，Begin ----- /
        // 根据当前模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/{$_B['template']}/$filename.html";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/{$_B['template']}/$filename.tpl.php";
        }

        // 根据当前模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/{$_B['template']}/$filename/index.html";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/{$_B['template']}/$filename/index.tpl.php";
        }

        // 根据默认模版的指定文件名查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/default/$filename.html";
            $compile        = $this->appPath . "/runtime/jcbase/tpl/default/$filename.tpl.php";
        }

        // 根据默认模版的index文件查找
        if (!is_file($source)) {
            $doesNotExist[] = $source;
            $source         = $this->jcbaseSrcPath . "/views/default/$filename/index.html";
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

        $variables = !empty($variables) && is_array($variables) ? $variables : [];
        switch ($flag) {
            case TEMPLATE_DISPLAY:
            default:
                extract($variables + $GLOBALS, EXTR_SKIP);
                include $compile;
                return $compile;
            case TEMPLATE_FETCH:
                extract($variables + $GLOBALS, EXTR_SKIP);
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
     * @param array $variables 当前作用域的变量，一般通过get_defined_vars()获取
     * @return false|string|void
     * @lasttime: 2022/9/27 21:10
     */
    public function vTpl(?string $filename = 'index', int $flag = TEMPLATE_DISPLAY, array $variables = [])
    {
        $filename = $filename ?: 'index';

        $source  = $this->appPath . "/web/$filename.html";
        $compile = $this->appPath . "/runtime/vtpl/$filename.tpl.php";

        // if (!is_file($source)) $source = $this->appPath . "/web/$filename/index.html";
        // if (!is_file($source)) $source = $this->appPath . "/web/dist/$filename/index.html";
        if (!is_file($source)) $source = $this->appPath . "/web/dist/$filename.html";

        if (!is_file($source)) {
            if (YII_DEBUG) exit("template source '$source' is not exist!");
            exit("template source '$filename' is not exist!");
        }
        if (YII_DEBUG || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            $this->template_compile($source, $compile);
        }

        $variables = !empty($variables) && is_array($variables) ? $variables : [];
        switch ($flag) {
            case TEMPLATE_DISPLAY:
            default:
                extract($variables + $GLOBALS, EXTR_SKIP);
                include $compile;
                return $compile;
            case TEMPLATE_FETCH:
                extract($variables + $GLOBALS, EXTR_SKIP);
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

    public function template_compile($from, $to)
    {
        $path = dirname($to);
        if (!is_dir($path)) {
            try {
                FileHelper::createDirectory($path);
            } catch (Exception $e) {
            }
        }
        $content = $this->template_parse(file_get_contents($from));
        file_put_contents($to, $content);
    }

    public function template_parse($str): string
    {
        $str = preg_replace('/<!--{(.+?)}-->/s', '{$1}', $str);
        $str = preg_replace('/{php\s+(.+?)}/', '<?php $1 ?>', $str);
        $str = preg_replace('/{if\s+(.+?)}/', '<?php if($1) { ?>', $str);
        $str = preg_replace('/{else}/', '<?php } else { ?>', $str);
        $str = preg_replace('/{else ?if\s+(.+?)}/', '<?php } else if($1) { ?>', $str);
        $str = preg_replace('/{\/if}/', '<?php } ?>', $str);
        $str = preg_replace('/{loop\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2) { ?>', $str);
        $str = preg_replace('/{loop\s+(\S+)\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2 => $3) { ?>', $str);
        $str = preg_replace('/{\/loop}/', '<?php } } ?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\[\]\'\"\$]*)}/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/{media\s+(\S+)}/', '<?php echo tomedia($1); ?>', $str);
        $str = preg_replace_callback('/<\?php([^?]+)?>/', "\Jcbowen\JcbaseYii2\components\Template::templateAddQuote", $str);
        $str = preg_replace('/<jc_tpl_php>(.+?)<\/jc_tpl_php>/', '<?php include (new \Jcbowen\JcbaseYii2\components\Template)->template($1, TEMPLATE_INCLUDEPATH); ?>', $str);
        $str = preg_replace('/{template\s+(.+?)}/', '<?php include (new \Jcbowen\JcbaseYii2\components\Template)->template($1, TEMPLATE_INCLUDEPATH); ?>', $str);
        $str = preg_replace('/{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)}/', '<?php echo $1; ?>', $str);
        $str = str_replace('{##', '{', $str);
        return /*"<?php defined('IN_JC') or exit('Access Denied');?>" .*/ str_replace('##}', '}', $str);
    }

    public static function templateAddQuote($matches)
    {
        $code = "<?php $matches[1] ?>";
        $code = preg_replace('/\[([a-zA-Z0-9_\-.\x7f-\xff]+)](?![a-zA-Z0-9_\-.\x7f-\xff\[\]]*[\'"])/', "['$1']", $code);
        return str_replace('\\\"', '\"', $code);
    }
}
