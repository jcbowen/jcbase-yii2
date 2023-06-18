<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * Class Safe
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:37 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Safe
{
    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $default
     * @param $value
     * @return float|int
     * @lasttime: 2021/12/19 11:51 下午
     */
    public static function gpcInt($value, $default = 0)
    {
        if (strpos($value, '.') !== false) {
            $value   = floatval($value);
            $default = floatval($default);
        } else {
            $value   = intval($value);
            $default = intval($default);
        }

        if (empty($value) && $default != $value) $value = $default;
        return $value;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param mixed $value
     * @param array $allow 允许的值
     * @param mixed $default 默认值
     * @param bool $strict 是否严格模式
     * @return mixed|string
     * @lasttime: 2021/12/19 11:49 下午
     */
    public static function gpcBelong($value, array $allow = [], $default = '', bool $strict = true)
    {
        if (empty($allow)) return $default;
        if (in_array($value, $allow, $strict)) {
            return $value;
        } else {
            return $default;
        }
    }

    public static function gpcString($value, $default = '')
    {
        $value = self::badStrReplace($value);
        $value = preg_replace('/&((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $value);

        if (empty($value) && $default != $value) $value = $default;
        return $value;
    }

    public static function gpcPath($value, $default = '')
    {
        $path = self::gpcString($value);
        $path = str_replace(['..', '..\\', '\\\\', '\\', '..\\\\'], '', $path);

        if (empty($path) || $path != $value) $path = $default;

        return $path;
    }

    public static function gpcArray($value, $default = []): array
    {
        if (empty($value) || !is_array($value)) return $default;
        foreach ($value as &$row) {
            if (is_numeric($row)) {
                $row = self::gpcInt($row);
            } elseif (is_array($row)) {
                $row = self::gpcArray($row, $default);
            } else {
                $row = self::gpcString($row);
            }
        }
        return $value;
    }

    public static function gpcBoolean($value): bool
    {
        return boolval($value);
    }

    public static function gpcHtml($value, $default = '')
    {
        if (empty($value) || !is_string($value)) return $default;
        $value = self::badStrReplace($value);

        $value = self::removeXss($value);
        if (empty($value) && $value != $default) $value = $default;
        return $value;
    }

    public static function gpcSql($value, $operator = 'ENCODE', $default = '')
    {
        if (empty($value) || !is_string($value)) return $default;
        $value = trim(strtolower($value));

        $badstr = [
            '_', '%', "'", chr(39),
            'select', 'join', 'union',
            'where', 'insert', 'delete',
            'update', 'like', 'drop',
            'create', 'modify', 'rename',
            'alter', 'cast',
        ];
        $newstr = [
            '\_', '\%', "''", '&#39;',
            'sel&#101;ct"', 'jo&#105;n', 'un&#105;on',
            'wh&#101;re', 'ins&#101;rt', 'del&#101;te',
            'up&#100;ate', 'lik&#101;', 'dro&#112',
            'cr&#101;ate', 'mod&#105;fy', 'ren&#097;me"',
            'alt&#101;r', 'ca&#115;',
        ];

        if ($operator == 'ENCODE') {
            $value = str_replace($badstr, $newstr, $value);
        } else {
            $value = str_replace($newstr, $badstr, $value);
        }
        return $value;
    }

    public static function gpcUrl($value, $strict_domain = true, $default = ''): string
    {
        global $_B;

        if (empty($value) || !is_string($value)) return $default;
        $value = urldecode($value);
        if (Util::startsWith($value, './') && Util::endsWith($value, '../')) {
            return $value;
        }

        if ($strict_domain) {
            $_B['siteRoot'] = Util::getSiteRoot();
            if (Util::startsWith($value, $_B['siteRoot'])) {
                return $value;
            }
            return $default;
        }

        if (Util::startsWith($value, 'http') || Util::startsWith($value, '//')) {
            return $value;
        }

        return $default;
    }

    public static function gpcVersion($value, $default = ''): string
    {
        $arr = explode('.', $value);
        if (!is_array($arr) || count($arr) != 3) return $default;
        foreach ($arr as &$v) $v = intval($v);
        return implode('.', $arr);
    }

    public static function removeXss($val)
    {
        $val    = preg_replace('/([\x0e-\x19])/', '', $val);
        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';

        for ($i = 0; $i < strlen($search); $i++) {
            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val);
            $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val);
        }
        preg_match_all('/href=[\'|\"](.*?)[\'|\"]|src=[\'|\"](.*?)[\'|\"]/i', $val, $matches);
        $url_list        = array_merge($matches[1], $matches[2]);
        $encode_url_list = [];
        if (!empty($url_list)) {
            foreach ($url_list as $key => $url) {
                $val               = str_replace($url, 'jc_' . $key . '_jcplaceholder', $val);
                $encode_url_list[] = $url;
            }
        }
        $ra1   = [
            'javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'script', 'embed',
            'object', 'frameset', 'ilayer', 'bgsound', 'base'
        ];
        $ra2   = [
            'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut',
            'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload',
            'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu',
            'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete',
            'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover',
            'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin',
            'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture',
            'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup',
            'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange',
            'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete',
            'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop',
            'onsubmit', 'onunload', '@import'
        ];
        $ra    = array_merge($ra1, $ra2);
        $found = true;
        while ($found == true) {
            $val_before = $val;
            for ($i = 0; $i < sizeof($ra); $i++) {
                $pattern = '/';
                for ($j = 0; $j < strlen($ra[$i]); $j++) {
                    if ($j > 0) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(&#0{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern     .= '/i';
                $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2);
                $val         = preg_replace($pattern, $replacement, $val);
                if ($val_before == $val) $found = false;
            }
        }
        if (!empty($encode_url_list) && is_array($encode_url_list)) {
            foreach ($encode_url_list as $key => $url) {
                $val = str_replace('jc_' . $key . '_jcplaceholder', $url, $val);
            }
        }
        return $val;
    }

    public static function badStrReplace($string)
    {
        if (empty($string)) return '';
        $badstr = ["\0", "%00", "%3C", "%3E", '<?', '<%', '<?php', '{php', '{if', '{loop', '{for', '../'];
        $newstr = ['_', '_', '&lt;', '&gt;', '_', '_', '_', '_', '_', '_', '_', '.._'];
        $string = str_replace($badstr, $newstr, $string);

        return $string;
    }

    public static function checkPassword($password)
    {
        preg_match(PASSWORD_STRONG_REGULAR, $password, $out);
        if (empty($out)) {
            return Util::error(-1, PASSWORD_STRONG_STATE);
        } else {
            return true;
        }
    }

}
