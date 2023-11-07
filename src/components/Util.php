<?php

namespace Jcbowen\JcbaseYii2\components;

use SimpleXMLElement;
use Yii;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\captcha\CaptchaAction;
use yii\helpers\ArrayHelper;
use yii\helpers\ReplaceArrayValue;
use yii\helpers\UnsetArrayValue;
use yii\redis\Connection;
use yii\web\Controller;
use yii\web\Response;

/**
 * Class Util
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/9/19 2:50 PM
 * @package Jcbowen\JcbaseYii2\components
 */
class Util
{
    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return string|null
     * @lasttime: 2023/2/1 3:38 PM
     */
    public static function getHostName(): ?string
    {
        return Yii::$app->request->getHostName();
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return string|null
     * @lasttime: 2023/2/1 3:38 PM
     */
    public static function getHostInfo(): ?string
    {
        return Yii::$app->request->getHostInfo();
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return string|null
     * @lasttime: 2023/2/1 3:38 PM
     */
    public static function getSiteRoot(): ?string
    {
        global $_B;
        if (!empty($_B['siteRoot'])) return $_B['siteRoot'];

        $_B['siteRoot'] = static::getHostInfo();
        if (!static::endsWith($_B['siteRoot'], '/')) $_B['siteRoot'] .= '/';

        return $_B['siteRoot'];
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $url
     * @param bool|int $format
     * @return array|false
     * @lasttime: 2023/2/1 3:45 PM
     */
    public static function getHeaders($url, $format = false)
    {
        $result = @get_headers($url, $format);
        if (empty($result)) {
            stream_context_set_default([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $result = get_headers($url, $format);
        }
        return $result;
    }

    /**
     * 获取Referer
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $needle 需要排除的特征字符串
     * @param string $default 当referer中包含$needle时，返回的默认值
     * @return string
     * @lasttime: 2023/2/1 3:32 PM
     */
    public static function getReferer(string $needle = '', string $default = ''): string
    {
        global $_GPC, $_B;

        $_SERVER['HTTP_REFERER'] = $_SERVER['HTTP_REFERER'] ?: '';

        $_B['referer'] = !empty($_GPC['referer']) ? $_GPC['referer'] : $_SERVER['HTTP_REFERER'];
        $_B['referer'] = static::endsWith($_B['referer'], '?') ? substr($_B['referer'], 0, -1) : $_B['referer'];

        if (!empty($needle) && strpos($_B['referer'], $needle)) {
            $_B['referer'] = $default;
        }

        $_B['referer'] = str_replace('&amp;', '&', $_B['referer']);
        $reUrl         = parse_url($_B['referer']);

        $_B['siteRoot'] = static::getSiteRoot();
        if (
            !empty($reUrl['host'])
            && !in_array($reUrl['host'], [$_SERVER['HTTP_HOST'], 'www.' . $_SERVER['HTTP_HOST']])
            && !in_array($_SERVER['HTTP_HOST'], [$reUrl['host'], 'www.' . $reUrl['host']])
        )
            $_B['referer'] = $_B['siteRoot'];
        elseif (empty($reUrl['host']))
            $_B['referer'] = $_B['siteRoot'] . './' . $_B['referer'];

        return strip_tags($_B['referer']);
    }

    /**
     * 将附件路径转换为附件链接
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $src 附件路径
     * @param bool $local_path 本地附件路径
     * @param bool $is_cache 是否缓存
     * @return string
     * @lasttime: 2022/3/19 5:45 下午
     */
    public static function toMedia(?string $src, bool $local_path = false, bool $is_cache = true): string
    {
        $src = trim($src);

        if (empty($src)) return '';
        if (!$is_cache) $src .= "?v=" . time();

        if (substr($src, 0, 2) == '//') {
            return 'http:' . $src;
        }
        if ((substr($src, 0, 7) == 'http://') || (substr($src, 0, 8) == 'https://')) {
            return $src;
        }

        // 如果存在资源目录，转换为本地资源目录
        if (static::strExists($src, 'static/')) {
            return Yii::$app->params['domain']['attachment_local'] . substr($src, strpos($src, 'static/'));
        }

        // 移除资源链接中的本地附件域名
        if (static::startsWith($src, Yii::$app->params['domain']['attachment_local']) && !static::strExists($src, '/static/')) {
            $urls = parse_url($src);
            $src  = substr($urls['path'], strpos($urls['path'], 'images'));
        }

        // 输出本地附件链接
        if ($local_path || empty(Yii::$app->params['attachment']['isRemote'])) {
            $src =
                Yii::$app->params['domain']['attachment_local'] . Yii::$app->params['attachment']['dir'] . '/' . $src;
        } else {
            $src = Yii::$app->params['domain']['attachment'] . $src;
        }
        return $src;
    }

    /**
     * 去除附件链接中的附件域名
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $src 附件路径
     * @return false|mixed|string
     * @lasttime: 2022/9/12 17:52
     */
    public static function removeMediaDomain(?string $src)
    {
        if (empty($src)) return '';
        if (static::startsWith($src, Yii::$app->params['domain']['attachment_local']) || static::startsWith($src, Yii::$app->params['domain']['attachment'])) {
            $type = 'images';
            foreach (File::$fileTypes as $item) {
                if (static::strExists($src, $item)) {
                    $type = $item . 's';
                    break;
                }
            }
            $urls = parse_url($src);
            $src  = substr($urls['path'], strpos($urls['path'], $type));
        }
        return $src;
    }

    /**
     * 路径安全解析
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $path
     * @return false|string
     * @lasttime: 2022/3/19 5:46 下午
     */
    public static function parsePath(string $path)
    {
        $danger_char = ['../', '{php', '<?php', '<%', '<?', '..\\', '\\\\', '\\', '..\\\\', '%00', '\0', '\r'];
        foreach ($danger_char as $char) if (static::strExists($path, $char)) return false;
        return $path;
    }

    /**
     * 是否为https通讯
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return bool
     * @lasttime: 2021/12/19 11:42 上午
     */
    public static function isSecureConnection(): bool
    {
        if (isset($_SERVER['HTTPS']) && (('1' == $_SERVER['HTTPS']) || ('on' == strtolower($_SERVER['HTTPS']))))
            return true;
        if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT']))
            return true;
        // 使用了阿里云CDN的情况下判断
        if ($_SERVER['HTTP_X_CLIENT_SCHEME'] == 'https')
            return true;
        // 反向代理的情况下判断，需要在nginx反向代理中配置【proxy_set_header   X-Forwarded-Proto $scheme;】
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ('https' == $_SERVER['HTTP_X_FORWARDED_PROTO']))
            return true;
        return false;
    }

    /**
     * 获取一个随机数
     * @param int $length 要获取随机数的长度
     * @param bool $numeric 返回纯数字随机数
     * @return string
     */
    public static function random(int $length, bool $numeric = false): string
    {
        $seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            --$length;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 取出数组中指定部分
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $arr
     * @param mixed $default
     * @param string|array $keys
     * @return array
     * @lasttime: 2022/3/19 6:12 下午
     */
    public static function arrayElements($keys, array $arr, $default = FALSE): array
    {
        $return = [];
        $keys   = (array)$keys;
        foreach ($keys as $key) {
            $return[$key] = $arr[$key] ?? $default;
        }
        return $return;
    }

    /**
     * 获取数组中指定key的
     *
     *
     * For example:
     *
     * ```php
     * $array = [
     *     'A' => [1, 2],
     *     'B' => [
     *         'C' => 1,
     *         'D' => 2,
     *     ],
     *     'E' => 1,
     * ];
     *
     * $result = \common\components\Util::getArrValueByKey($array, 'B.C');
     * // $result will be:
     * // 1
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param array $arr
     * @return mixed
     * @lasttime: 2022/3/26 10:05 下午
     */
    public static function getArrValueByKey(array $arr = [], string $key = '')
    {
        $key = trim($key, '.');
        if (empty($arr)) return null;
        if (empty($key)) return $arr;

        $nodeArr = $arr;
        $keyArr  = explode('.', $key);
        foreach ($keyArr as $item) {
            if (!array_key_exists($item, $nodeArr)) {
                $nodeArr = null;
                break;
            }
            $nodeArr = $nodeArr[$item];
        }

        return $nodeArr;
    }

    /**
     * 根据指定的parentIdKey将数组转树形结构
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $arr 二维数组
     * @param int|string $parentId 父级id
     * @param string $parentIdKey 父级id的key
     * @param string $childrenKey 将子级放入的key
     * @return array
     * @lasttime: 2023/5/14 5:59 PM
     */
    public static function arrayToTree(array $arr = [], $parentId = 0, string $parentIdKey = 'parent_id', string $childrenKey = 'children'): array
    {
        if (empty($arr))
            return [];

        $tree = [];
        foreach ($arr as $item) {
            // 非二维数组或为空，直接跳过
            if (!is_array($item) || empty($item)) continue;
            // 匹配父级id
            if ($item[$parentIdKey] == $parentId) {
                $children = self::arrayToTree($arr, $item['id'], $parentIdKey, $childrenKey);
                if (!empty($children)) {
                    $item[$childrenKey] = $children;
                }
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * 根据指定的parentIdKey将数组转树形结构
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $tree 树形结构的多维数组
     * @param string $sortKey 根据指定的key进行排序
     * @param string $childrenKey 子级所在的key
     * @return array
     * @lasttime: 2023/5/14 5:59 PM
     */
    public static function treeToArray(array $tree = [], string $sortKey = '', string $childrenKey = 'children'): array
    {
        if (empty($tree))
            return [];

        $arr = [];
        foreach ($tree as $item) {
            $children = $item[$childrenKey] ?? [];
            if (isset($item[$childrenKey])) unset($item[$childrenKey]);
            $arr[] = $item;
            if (!empty($children)) {
                $arr = array_merge($arr, self::treeToArray($children, $sortKey, $childrenKey));
            }
        }

        if (!empty($sortKey)) {
            ArrayHelper::multisort($arr, $sortKey);
        }

        return $arr;
    }

    /**
     * 遍历树形结构
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $tree 树形结构的多维数组
     * @param callable $callback 回调函数，示例：function(&$node):void
     * @lasttime: 2023/11/7 11:16 AM
     */
    public static function treeEach(array &$tree, callable $callback)
    {
        foreach ($tree as &$node) {
            // 调用回调函数处理节点数据
            $callback($node);

            // 如果节点包含子节点，则递归调用
            if (isset($node['children']) && is_array($node['children'])) {
                self::treeEach($node['children'], $callback);
            }
        }
    }

    /**
     * 根据指定的key查找树形结构中的节点
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|null $tree 树形结构的多维数组
     * @param mixed $value 要查找的值
     * @param string $key 要查找的key
     * @param string $childrenKey 子级所在的key
     * @return array
     * @lasttime: 2023/6/23 3:38 PM
     */
    public static function findNodeInTree(?array $tree = [], $value = '', string $key = 'id', string $childrenKey = 'children'): array
    {
        if (empty($tree))
            return [];

        $node = [];
        foreach ($tree as $item) {
            if ($item[$key] == $value) {
                $node = $item;
                break;
            }
            $children = $item[$childrenKey] ?? [];
            if (!empty($children)) {
                $node = self::findNodeInTree($children, $value, $key, $childrenKey);
                if (!empty($node))
                    break;
            }
        }

        return $node;
    }

    /**
     * 根据指定的key获取该节点所在的分支
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|null $tree 树形结构的多维数组
     * @param mixed $value 要查找的值
     * @param bool $needChildren 是否需要子级
     * @param string $key 要查找的key
     * @param string $childrenKey 子级所在的key
     * @return array
     * @lasttime: 2023/10/30 11:27 PM
     */
    public static function getBranchInTree(?array $tree = [], $value = '', bool $needChildren = true, string $key = 'id', string $childrenKey = 'children'): array
    {
        if (empty($tree))
            return [];

        $branch = [];
        foreach ($tree as $item) {
            if ($item[$key] == $value) {
                // 不需要子级
                if (!$needChildren) unset($item[$childrenKey]);
                $branch = $item;
                break;
            }
            $children = $item[$childrenKey] ?? [];
            if (!empty($children)) {
                $subBranch = self::getBranchInTree($children, $value, $needChildren, $key, $childrenKey);
                if (!empty($subBranch)) {
                    $branch               = $item;
                    $branch[$childrenKey] = [$subBranch];
                    break;
                }
            }
        }

        return $branch;
    }

    /**
     * 将树形结构的最底层返回给回调函数
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $tree 树形结构的多维数组
     * @param callable|null $fn 匿名回调函数
     * @param string $childrenKey 子级所在的key
     * @lasttime: 2023/10/9 9:40 PM
     */
    public static function treeLast(array &$tree = [], callable $fn = null, string $childrenKey = 'children')
    {
        if (empty($tree) || !is_callable($fn)) return;

        foreach ($tree as &$item)
            if (!empty($item[$childrenKey]))
                self::treeLast($item[$childrenKey], $fn, $childrenKey);
            else {
                $item = $fn($item);
            }
    }

    /**
     * 根据回调函数移除树形结构节点
     *
     * 函数会遍历每个节点，并执行回调函数。如果回调函数返回 true，则从树形结构中移除该节点。一旦执行了一次移除，函数将从头开始遍历树形结构，以便处理可能被移除的节点的父节点
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $tree 树形结构的多维数组
     * @param callable|null $fn 匿名回调函数
     * @param string $childrenKey 子级所在的key
     * @lasttime: 2023/10/10 11:18 PM
     */
    public static function removeTreeNode(array &$tree = [], callable $fn = null, string $childrenKey = 'children')
    {
        if (empty($tree) || !is_callable($fn)) return;
        // 遍历每个节点
        foreach ($tree as $key => &$node) {
            // 检查回调函数的返回值
            if ($fn && $fn($node)) {
                // 从原树形结构中移除该节点
                unset($tree[$key]);
                // 重新从头开始遍历
                self::removeTreeNode($tree, $fn, $childrenKey);
                break;
            }

            // 递归遍历子节点
            if (isset($node[$childrenKey]) && is_array($node[$childrenKey]))
                self::removeTreeNode($node[$childrenKey], $fn, $childrenKey);
        }
    }

    /**
     * 递归合并数组，与yii2的ArrayHelper::merge()不同的是，如果遇到int类型的key，会将后面的值覆盖前面的值
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $a
     * @param $b
     * @return mixed|null
     * @lasttime: 2023/5/24 11:08
     */
    public static function merge($a, $b)
    {
        $args = func_get_args();
        $res  = array_shift($args);
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if ($v instanceof UnsetArrayValue) {
                    unset($res[$k]);
                } elseif ($v instanceof ReplaceArrayValue) {
                    $res[$k] = $v->value;
                } elseif (is_int($k)) {
                    if (array_key_exists($k, $res)) {
                        $res[$k] = $v;
                    } else {
                        $res[] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::merge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    /**
     * 将 XML 字符串解释为对象
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $string
     * @param string $class_name
     * @param int $options
     * @param string $ns
     * @param bool $is_prefix
     * @return false|SimpleXMLElement|string
     * @lasttime: 2023/3/14 23:54
     */
    public static function simplexml_load_string($string, string $class_name = 'SimpleXMLElement', int $options = 0, string $ns = '', bool $is_prefix = false)
    {
        libxml_disable_entity_loader();
        if (preg_match('/(<!DOCTYPE|<!ENTITY)/i', $string))
            return false;

        $string = preg_replace('/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f\\x7f]/', '', $string);
        return simplexml_load_string($string, $class_name, $options, $ns, $is_prefix);
    }

    /**
     * 将数组转换为xml
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $arr
     * @param int $level
     * @return array|string|string[]|null
     * @lasttime: 2023/3/14 23:57
     */
    public static function arrayToXml($arr, int $level = 1)
    {
        $s = 1 == $level ? '<xml>' : '';
        foreach ($arr as $tagName => $value) {
            if (is_numeric($tagName)) {
                $tagName = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value))
                $s .= "<$tagName>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</$tagName>";
            else
                $s .= "<$tagName>" . self::arrayToXml($value, $level + 1) . "</$tagName>";
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);

        return 1 == $level ? $s . '</xml>' : $s;
    }

    /**
     * 将xml转换为数组
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $xml
     * @return array|string
     * @lasttime: 2023/3/14 23:58
     */
    public static function xmlToArray($xml)
    {
        if (empty($xml)) return [];

        $result = [];
        $xmlObj = static::simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xmlObj instanceof SimpleXMLElement) {
            $result = json_decode(json_encode($xmlObj), true);
            if (is_array($result))
                return $result;
            else
                return '';
        } else
            return $result;
    }

    /**
     * 反序列化
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 10:51 下午
     * @param $value
     *
     * @return mixed|array
     */
    public static function unserializer($value)
    {
        if (empty($value)) return [];
        if (!static::is_serialized($value)) {
            return $value;
        }
        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            $result = unserialize($value, ['allowed_classes' => false]);
        } else {
            if (preg_match('/[oc]:[^:]*\d+:/i', $value)) {
                return [];
            }
            $result = unserialize($value);
        }
        if (false === $result) {
            $temp = preg_replace_callback('!s:(\d+):"(.*?)";!s', function ($matches) {
                return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
            }, $value);

            return unserialize($temp);
        } else {
            return $result;
        }
    }

    /**
     * 是否为序列化字符串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 10:52 下午
     * @param bool $strict
     *
     * @param $data
     * @return bool
     */
    public static function is_serialized($data, bool $strict = true): bool
    {
        if (!is_string($data)) return false;
        $data = trim($data);
        if ('N;' == $data) return true;
        if (strlen($data) < 4) return false;
        if (':' !== $data[1]) return false;
        if ($strict) {
            $lastC = substr($data, -1);
            if (';' !== $lastC && '}' !== $lastC) return false;
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            if (false === $semicolon && false === $brace) return false;
            if (false !== $semicolon && $semicolon < 3) return false;
            if (false !== $brace && $brace < 4) return false;
        }
        $token = $data[0];
        switch ($token) {
            case 's' :
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) return false;
                } elseif (false === strpos($data, '"')) return false;
            case 'a' :
                return (bool)preg_match("/^$token:[0-9]+:/s", $data);
            case 'O' :
                return false;
            case 'b' :
            case 'i' :
            case 'd' :
                $end = $strict ? '$' : '';
                return (bool)preg_match("/^$token:[0-9.E-]+;$end/", $data);
        }
        return false;
    }

    /**
     * 判断字符串是否为json
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param mixed $string
     * @return bool
     * @lasttime: 2023/10/9 12:26 AM
     */
    public static function is_json($string)
    {
        if (!is_string($string)) return false;
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * 判断字符串是否包含字串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:26 上午
     * @param string $find 需要查找的字串
     *
     * @param string $string 在该字符串中查找
     * @return bool
     */
    public static function strExists(string $string, string $find): bool
    {
        return !(strpos($string, $find) === FALSE);
    }

    /**
     * 获取字符串长度
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $string 字符串
     * @param string $charset 字符集
     * @return false|int|mixed
     * @lasttime: 2023/2/13 2:06 PM
     */
    public static function strLen(string $string, string $charset = 'utf8')
    {
        $charset = 'gbk' === strtolower($charset) ? 'gbk' : 'utf8';

        if (function_exists('mb_strlen') && extension_loaded('mbstring')) {
            return mb_strlen($string, $charset);
        } else {
            $n      = $noc = 0;
            $strlen = strlen($string);

            if ('utf8' == $charset) {
                while ($n < $strlen) {
                    $t = ord($string[$n]);
                    if (9 == $t || 10 == $t || (32 <= $t && $t <= 126)) {
                        ++$n;
                        ++$noc;
                    } elseif (194 <= $t && $t <= 223) {
                        $n += 2;
                        ++$noc;
                    } elseif (224 <= $t && $t <= 239) {
                        $n += 3;
                        ++$noc;
                    } elseif (240 <= $t && $t <= 247) {
                        $n += 4;
                        ++$noc;
                    } elseif (248 <= $t && $t <= 251) {
                        $n += 5;
                        ++$noc;
                    } elseif (252 == $t || 253 == $t) {
                        $n += 6;
                        ++$noc;
                    } else {
                        ++$n;
                    }
                }
            } else {
                while ($n < $strlen) {
                    $t = ord($string[$n]);
                    if ($t > 127) {
                        $n += 2;
                    } else {
                        ++$n;
                    }
                    ++$noc;
                }
            }

            return $noc;
        }
    }

    /**
     * 截取字符串
     *
     * @param string $string 字符串
     * @param int $length 截取长度
     * @param bool $haveDot 是否有省略号
     * @param string $charset 字符集
     * @return string
     */
    public static function substr(string $string, int $length, bool $haveDot = false, string $charset = 'utf8'): string
    {
        $charset = 'gbk' === strtolower($charset) ? 'gbk' : 'utf8';
        if (static::strLen($string, $charset) <= $length) return $string;

        if (function_exists('mb_strcut')) {
            $string = mb_substr($string, 0, $length, $charset);
        } else {
            $pre    = '{%';
            $end    = '%}';
            $string = str_replace([
                '&amp;', '&quot;', '&lt;', '&gt;'
            ], [
                $pre . '&' . $end, $pre . '"' . $end, $pre . '<' . $end, $pre . '>' . $end
            ], $string);

            $strlen = strlen($string);
            $n      = $tn = $noc = 0;
            if ('utf8' == $charset) {
                while ($n < $strlen) {
                    $t = ord($string[$n]);
                    if (9 == $t || 10 == $t || (32 <= $t && $t <= 126)) {
                        $tn = 1;
                        ++$n;
                        ++$noc;
                    } elseif (194 <= $t && $t <= 223) {
                        $tn = 2;
                        $n  += 2;
                        ++$noc;
                    } elseif (224 <= $t && $t <= 239) {
                        $tn = 3;
                        $n  += 3;
                        ++$noc;
                    } elseif (240 <= $t && $t <= 247) {
                        $tn = 4;
                        $n  += 4;
                        ++$noc;
                    } elseif (248 <= $t && $t <= 251) {
                        $tn = 5;
                        $n  += 5;
                        ++$noc;
                    } elseif (252 == $t || 253 == $t) {
                        $tn = 6;
                        $n  += 6;
                        ++$noc;
                    } else {
                        ++$n;
                    }
                    if ($noc >= $length)
                        break;
                }
            } else {
                while ($n < $strlen) {
                    $t = ord($string[$n]);
                    if ($t > 127) {
                        $tn = 2;
                        $n  += 2;
                    } else {
                        $tn = 1;
                        ++$n;
                    }
                    ++$noc;
                    if ($noc >= $length) {
                        break;
                    }
                }
            }
            if ($noc > $length) {
                $n -= $tn;
            }
            $strCut = substr($string, 0, $n);
            $string = str_replace([
                $pre . '&' . $end, $pre . '"' . $end, $pre . '<' . $end, $pre . '>' . $end
            ], [
                '&amp;', '&quot;', '&lt;', '&gt;'
            ], $strCut);
        }

        if (!empty($string) && $haveDot)
            $string .= '...';

        return $string;
    }

    /**
     * 是否以指定字符开始
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 11:19 下午
     * @param string $needle
     *
     * @param string $haystack
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    /**
     * 是否以指定字符结束
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 11:19 下午
     * @param string $needle
     *
     * @param string $haystack
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    /**
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param int|string $errno
     * @param string $message
     * @param mixed $data
     * @param array $params
     * @return array
     * @lasttime 2022/9/28 16:01
     */
    public static function error($errno, string $message = '', $data = [], array $params = []): array
    {
        $error = [
            'errcode' => (int)$errno,
            'errmsg'  => $message,
            'data'    => $data
        ];
        if (!empty($params)) $error = ArrayHelper::merge($error, $params);
        return $error;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $data
     * @return bool
     * @lasttime: 2023/2/1 3:47 PM
     */
    public static function isError($data): bool
    {
        if (empty($data) || (is_array($data) && array_key_exists('errcode', $data) && $data['errcode'] != ErrCode::SUCCESS))
            return true;
        else
            return false;
    }

    /**
     * 获取指定目录下的文件夹
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $path
     * @return array
     * @lasttime: 2022/1/14 12:31 上午
     */
    public static function getDirByPath(string $path): array
    {
        $path     .= substr($path, -1) == '/' ? '' : '/';
        $template = [];
        if (is_dir($path)) {
            if ($handle = opendir($path)) {
                while (false !== ($templatePath = readdir($handle))) {
                    if ($templatePath != '.' && $templatePath != '..') {
                        if (is_dir($path . $templatePath)) {
                            $template[] = $templatePath;
                        }
                    }
                }
            }
        }
        return $template;
    }

    /**
     * 获取指定目录下所有文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $dir 目录路径
     * @return array
     * @lasttime: 2022/1/14 12:26 上午
     */
    public static function getFilesByDir(string $dir): array
    {
        $dir     .= substr($dir, -1) == '/' ? '' : '/';
        $dirInfo = [];
        foreach (glob($dir . '*') as $v) {
            $dirInfo[] = $v;
            if (is_dir($v)) {
                $dirInfo = array_merge($dirInfo, self::getFilesByDir($v));
            }
        }
        return $dirInfo;
    }

    /**
     * 对数据进行AES加密
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param string $iv
     * @param string $data
     * @return string
     * @lasttime: 2021/12/28 1:24 下午
     */
    public static function myEncrypt(string $data, string $key = 'jcbase.aes_key__', string $iv = 'jcbase.aes_iv___'): string
    {
        return base64_encode(AES::encrypt($data, $key, $iv));
    }

    /**
     * 对数据进行AES解密
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param string $iv
     * @param string $encode
     * @return string
     * @lasttime: 2021/12/28 1:24 下午
     */
    public static function myDecrypt(string $encode, string $key = 'jcbase.aes_key__', string $iv = 'jcbase.aes_iv___'): string
    {
        return AES::decrypt(base64_decode($encode), $key, $iv);
    }

    /**
     * 获取redis
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/5/24 8:53 下午
     * @param string $connectionName
     * @return Connection
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis
     */
    public static function getRedis(string $connectionName = 'redis'): Connection
    {
        static $redis = [];
        if (empty($redis[$connectionName]))
            $redis[$connectionName] = Yii::$app->$connectionName;

        return $redis[$connectionName];
    }

    /**
     * redis设置数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|array $value
     * @param int|string $expire
     * @param mixed ...$options
     * @param $key
     * @return mixed
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis set()
     */
    public static function redisSet($key, $value, $expire = 0, ...$options)
    {
        $expire = intval($expire);

        $redis = static::getRedis();
        if (is_array($value)) $value = serialize($value);
        $result = $redis->set($key, $value, ...$options);
        if (!empty($expire)) $redis->expire($key, $expire);
        return $result;
    }

    /**
     * redis根据key获取数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @return string|array|mixed
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis get()
     */
    public static function redisGet($key)
    {
        $redis = static::getRedis();

        $value = $redis->get($key);
        return static::unserializer($value);
    }

    /**
     * redis根据key获取数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param mixed ...$key
     * @return array
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis mget()
     */
    public static function redisMget(...$key): array
    {
        if (empty($key)) return [];

        $redis = static::getRedis();

        $list = (array)$redis->mget(...$key);
        foreach ($list as &$item) $item = static::unserializer($item);
        return $list;
    }

    /**
     * redis根据key删除数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param ...$keys
     * @return mixed
     * @lasttime: 2022/9/8 2:04 PM
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis del()
     */
    public static function redisDel(...$keys)
    {
        $redis = static::getRedis();
        return $redis->del(...$keys);
    }

    /**
     * redis确定key是否存在
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param ...$keys
     * @return mixed
     * @lasttime: 2022/10/6 18:54
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis exists()
     */
    public static function redisExists(...$keys)
    {
        $redis = static::getRedis();
        return $redis->exists(...$keys);
    }

    /**
     * redis根据key延长过期时间
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @param $expire
     * @return mixed
     * @lasttime: 2022/9/8 2:05 PM
     * @deprecated use Jcbowen\JcbaseYii2\components\Redis expire()
     */
    public static function redisExpire($key, $expire)
    {
        $redis = static::getRedis();
        return $redis->expire($key, $expire);
    }

    /**
     * 将static::error的数据转换为result输出
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array|Response $error
     * @return string|Response
     * @lasttime 2022/9/28 15:49
     */
    public function resultError($error = [])
    {
        if (empty($error))
            return $this->result(ErrCode::UNKNOWN, '数据不存在或已被删除');

        if ($error instanceof Response)
            return $error;

        $params = array_filter($error, function ($key) {
            return !in_array($key, ['errcode', 'errmsg', 'data']);
        }, ARRAY_FILTER_USE_KEY);
        return $this->result($error['errcode'], $error['errmsg'], $error['data'], $params);
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
    public function result($errCode = ErrCode::UNKNOWN, string $errmsg = '', $data = [], array $params = [], string $returnType = 'exit')
    {
        global $_GPC;
        $data  = (array)$data;
        $count = count($data);

        $errCode = isset($errCode) ? (int)$this->getResponseCode($errCode) : ErrCode::UNKNOWN;
        $errmsg  = $this->getResponseMsg($errmsg);
        $data    = $this->getResponseData($data);

        $result = [
            'errcode' => $errCode,
            'code'    => $errCode,
            'errmsg'  => $errmsg,
            'msg'     => $errmsg,
            'count'   => $count,
            'data'    => $data
        ];
        if (!empty($params) && is_array($params)) {
            $result = array_merge($result, $params);
        }
        $result['totalCount'] = $result['count'];
        if ($_GPC['print_result'] == 1) {
            print_r($result);
            $this->_end();
        }
        if ($returnType == 'exit') {
            //  返回封装后的json格式数据
            $response             = Yii::$app->getResponse();
            $response->format     = Response::FORMAT_JSON;
            $response->data       = $result;
            $response->statusCode = 200;

            if ($errCode != 0 && $errCode != 200) {
                $response->send();
                $this->_end(0, $response);
            }

            return $response;
        } else {
            return stripslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $html
     * @return Response
     * @lasttime: 2022/10/6 13:05
     */
    public function resultHtml(?string $html = ''): Response
    {
        $response             = Yii::$app->getResponse();
        $response->format     = Response::FORMAT_HTML;
        $response->data       = $html;
        $response->statusCode = 200;
        return $response;
    }

    /**
     * 获取接口返回的数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $data
     *
     * @return array|int|mixed|string
     */
    private function getResponseData($data)
    {
        if (is_array($data) || is_string($data) || is_numeric($data)) return $data;
        if (is_object($data)) {
            if ($data instanceof Response) {
                $this->_end(0, $data);
                return [];
            }
            if (method_exists($data, 'toArray')) return $data->toArray();
        }
        return [];
    }

    /**
     * 获取接口返回的状态码
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $code
     *
     * @return float|int|string
     */
    private function getResponseCode($code)
    {
        if (is_numeric($code)) return $code;
        if ($code instanceof Response) {
            $this->_end(0, $code);
            return intval($code);
        }
        return 0;
    }

    /**
     * 获取接口返回的消息
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $msg
     *
     * @return string
     */
    private function getResponseMsg($msg): string
    {
        if (is_string($msg)) return $msg;

        if ($msg instanceof Response) {
            $this->_end(0, $msg);
            return '';
        }

        return 'ok';
    }

    /**
     * 结束程序
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|int $status
     * @param null $response
     * @lastTime 2021/12/18 12:22 上午
     */
    private function _end($status = '0', $response = null)
    {
        try {
            $status = intval($status);
            Yii::$app->end($status, $response);
        } catch (ExitException $e) {
        }
        exit;
    }

    /** 获取图片大小
     * @param string $filename
     * @param array $imageInfo
     * @return array|false
     */
    public function getImageSize(string $filename, array $imageInfo = [])
    {
        $result = @getimagesize($filename, $imageInfo);
        if (empty($result)) {
            $file_content = Communication::get($filename);
            $content      = $file_content['content'];
            $result       = getimagesize('data://image/jpeg;base64,' . base64_encode($content), $imageInfo);
        }
        return $result;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param Controller $controller
     * @return array|string|Response
     * @throws InvalidConfigException
     * @lasttime: 2021/12/20 10:00 上午
     */
    public function showCaptcha(Controller $controller)
    {
        global $_GPC;
        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) {
            return $this->result(ErrCode::PARAMETER_ERROR, '验证码类型不能为空');
        }
        $c            = Yii::createObject(\Jcbowen\JcbaseYii2\components\captcha\CaptchaAction::class, [
            '__' . $type,
            $controller
        ]);
        $c->maxLength = $_GPC['maxLength'] ? intval($_GPC['maxLength']) : 5;
        $c->minLength = $_GPC['minLength'] ? intval($_GPC['minLength']) : 5;
        $c->height    = $_GPC['height'] ? intval($_GPC['height']) : 40;
        $c->width     = $_GPC['width'] ? intval($_GPC['width']) : 120;
        $c->offset    = $_GPC['offset'] ? intval($_GPC['offset']) : 9;
        //$c->backColor = 0x000000;
        $c->getVerifyCode(true);
        return $c->run();
    }

    /**
     * 验证传入的验证码是否正确
     * @param string $code 传入的验证码
     * @param Controller $controller
     * @return bool
     * @throws InvalidConfigException 控制器中的使用示例 verifyCaptcha($code, new \backend\controllers\utility\CaptchaController('utility/captcha', $this->module));
     */
    public function verifyCaptcha(string $code, Controller $controller)
    {
        global $_GPC;

        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) return $this->result(ErrCode::PARAMETER_ERROR, '验证码类型不能为空');

        $code = trim($code);
        $code = Safe::gpcString($code);
        $code = strtolower($code);
        if (empty($code)) return $this->result(ErrCode::PARAMETER_ERROR, '验证码不能为空');
        $verifyCode = $this->getCaptcha($controller);
        if ($verifyCode == $code) {
            return true;
        }
        return false;
    }

    /**
     * 获取图形验证码
     * @param Controller $controller
     * @return string
     * @throws InvalidConfigException 控制器中的使用示例 getCaptcha(new \backend\controllers\utility\CaptchaController('utility/captcha', $this->module));
     */
    public function getCaptcha(Controller $controller)
    {
        global $_GPC;
        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) return $this->result(ErrCode::PARAMETER_ERROR, '验证码类型不能为空');

        $c = Yii::createObject(CaptchaAction::class, ['__' . $type, $controller]);
        return $c->getVerifyCode();
    }

    /**
     * 测试代码运行时长
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $tag
     * @return float
     * @lasttime: 2022/9/6 11:37 AM
     */
    public static function test_code_time(int $tag = 0): float
    {
        static $logs = [];
        $time = microtime(true);
        if (empty($logs[$tag])) {
            $logs[$tag] = $time;
            return $time;
        }
        return round($time - $logs[$tag], 3);
    }

    /**
     * 四舍五入金额
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param mixed $money 金额
     * @param int $decimals 小数位数
     * @return float
     * @lasttime: 2022/9/19 2:48 PM
     */
    public static function round_money($money, int $decimals = 2): float
    {
        return round(floatval($money), $decimals);
    }

    // ----- 弃用方法 ----- //

    /**
     * @deprecated use Util::treeLast
     */
    public static function treeLastCallback(array $tree = [], callable $fn = null, string $childrenKey = 'children')
    {
        static::treeLast($tree, $fn, $childrenKey);
    }
}
