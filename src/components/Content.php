<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * 内容处理器
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2023/1/17 9:45 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Content
{
    /**
     * 对html内容进行转换便于存库
     * 会将其中的图片链接全部去除域名，只保留路径
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $html html内容
     * @param bool $entities 是否转换html实体
     * @return string
     * @lasttime: 2022/9/19 4:43 PM
     */
    public static function toSave(?string $html = '', bool $entities = true): string
    {
        if (empty($html)) return '';

        self::html_entity_decode($html);

        // 将富文本中的附件链接转换为相对路径
        $html = preg_replace_callback('/<img.*?src=[\\\\\'| \\"](.*?(?:[\\.gif|\\.jpg|\\.png|\\.jpeg|\\.bmp]?))[\\\\\'|\\"].*?[\\/]?>/', function ($matches) {
            $imageTag    = $matches[0];
            $imageSrc    = $matches[1];
            $resultImage = Util::removeMediaDomain($imageSrc);

            if (!Util::startsWith($imageSrc, '.')) {
                $imageTag = str_replace($imageSrc, $resultImage, $imageTag);
            } else {
                $imageTag = str_replace([
                    '.._attachment/',
                    '._attachment/',
                    '../attachment/',
                    './attachment/',
                ], '', $imageTag);
            }

            return $imageTag;
        }, $html);

        return $entities ? htmlentities($html) : $html;
    }

    /**
     * 对html内容进行转换便于显示
     * 会将其中的图片链接全部加上域名
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $html html内容
     * @param bool $entities 是否转换html实体
     * @return string
     * @lasttime: 2022/9/19 5:06 PM
     */
    public static function toShow(?string $html = '', bool $entities = true): string
    {
        if (empty($html)) return '';

        self::html_entity_decode($html);

        // 将富文本中的附件链接转换为相对路径
        $html = preg_replace_callback('/<img.*?src=[\\\\\'| \\"](.*?(?:[\\.gif|\\.jpg|\\.png|\\.jpeg|\\.bmp]?))[\\\\\'|\\"].*?[\\/]?>/', function ($matches) {
            $imageTag    = $matches[0];
            $imageSrc    = $matches[1];
            $resultImage = Util::toMedia($imageSrc);

            return str_replace($imageSrc, $resultImage, $imageTag);
        }, $html);

        return $entities ? htmlentities($html) : $html;
    }

    /**
     * 避免重复执行html_entity_decode
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $html
     * @param int $quote_style
     * @return string
     * @lasttime: 2022/9/19 4:03 PM
     */
    public static function html_entity_decode(string &$html = '', int $quote_style = ENT_QUOTES): string
    {
        // $debug_backtrace = debug_backtrace();
        // if ($debug_backtrace[2]['class'] !== 'Content') {
        if (Util::strExists($html, '&lt;') || Util::strExists($html, '&gt;')) {
            return $html = html_entity_decode($html, $quote_style);
        }
        return $html;
    }

    /**
     * 分离html中的图片和内容
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $html
     * @param bool $unique 图片是否去重
     * @return array
     * @lasttime 2022/10/22 16:16
     */
    public static function separationImg(string $html = '', bool $unique = false): array
    {
        self::html_entity_decode($html);

        /** 提取图片 */
        $pattern = "/<[img|IMG].*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.png|\.jpeg|\.bmp]))[\'|\"].*?[\/]?>/";
        preg_match_all($pattern, $html, $match);
        $images = $unique ? array_unique($match[1]) : $match[1];

        /** 去除内容中的图片 */
        $content = preg_replace("/(<img.*?>)/is", '', $html);

        return ['images' => $images, 'content' => $content, 'html' => $html];
    }

    /**
     * 替换文本中的换行符及空格为p标签或br标签
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $content 文本内容
     * @param string $tag 转换为的标签名，只能为p或br
     * @return string|null
     * @lasttime: 2023/1/17 10:08 AM
     */
    public static function replaceRN(string $content, string $tag = 'p'): ?string
    {
        $pattern = [
            '/ /', // 半角下空格
            '/　/', // 全角下空格
            '/\r\n/', // window 换行符
            '/\n/', // Linux && Unix 换行符
        ];
        if ($tag == 'br') {
            $replace = ['&nbsp;', '&nbsp;', '<br/>', '<br/>'];
            $content = preg_replace($pattern, $replace, $content);
        } else { // p标签
            $replace = ['&nbsp;', '&nbsp;', '</p><p>', '</p><p>'];
            $content = preg_replace($pattern, $replace, $content);
            $content = '<p>' . $content . '</p>';
        }
        return $content;
    }

    /**
     * 移除换行、空格符
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $content
     * @param bool $is_en 是否使用转义符&nbsp;
     * @param string $type 移除类型，all为全部移除，space为只移除空格，rn为只移除换行符
     * @return string|null
     * @lasttime: 2023/1/17 10:04 AM
     */
    public static function removeRN(string $content, bool $is_en = false, string $type = 'all'): ?string
    {
        $pattern = [
            '/ /', // 半角下空格
            '/　/', // 全角下空格
            '/&nbsp;/', // html里的空格占位符
            '/\r\n/', // window 换行符
            '/\n/', // Linux && Unix 换行符
        ];
        if ($is_en)
            $replace = ['&nbsp;', '&nbsp;', '&nbsp;', '&nbsp;', '&nbsp;'];
        else
            $replace = ["", "", "", "", ""];

        switch ($type) {
            case 'rn': // 只移除换行符
                $pattern = ['/\r\n/', '/\n/',];
                if ($is_en)
                    $replace = ['&nbsp;', '&nbsp;'];
                else
                    $replace = ["", ""];
                break;
            case 'space': // 只移除空格
                $pattern = ['/ /', '/　/', '/&nbsp;/',];
                if ($is_en)
                    $replace = ['&nbsp;', '&nbsp;', '&nbsp;'];
                else
                    $replace = ["", "", ""];
                break;
        }

        return preg_replace($pattern, $replace, $content);
    }

    /**
     * 获取html中的纯文本(没有换行和空格)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $html
     * @return string
     * @lasttime: 2023/1/17 9:47 AM
     */
    public static function html2text(string $html): string
    {
        self::html_entity_decode($html);
        $html = strip_tags($html);
        return self::removeRN($html);
    }
}

