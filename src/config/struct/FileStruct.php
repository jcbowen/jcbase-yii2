<?php

namespace Jcbowen\JcbaseYii2\config\struct;

use Jcbowen\JcbaseYii2\base\ComponentArrayAccess;

/**
 * Class FileStruct
 * 文件组件配置结构
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2023/4/27 8:52 AM
 * @package Jcbowen\JcbaseYii2\config\struct
 */
class FileStruct extends ComponentArrayAccess
{
    /** @var int 附件类型 */
    const ATTACH_TYPE_IMAGE  = 1;
    const ATTACH_TYPE_VOICE  = 2;
    const ATTACH_TYPE_VIDEO  = 3;
    const ATTACH_TYPE_NEWS   = 4;
    const ATTACH_TYPE_OFFICE = 5;
    const ATTACH_TYPE_ZIP    = 6;

    /** @var string 本地存储附件根目录 */
    public $root = '@webroot';

    /** @var string 附件目录名 */
    public $dirname = 'attachment';

    /** @var string 附件数据模型 */
    public $model = \Jcbowen\JcbaseYii2\models\AttachmentModel::class;

    /** @var string|false 远程类型：oss|cos|false */
    public $remoteType = false;

    /** @var array 远程附件配置 */
    public $remoteConfig = [
        'cos' => [
            'secretId'  => '', // 腾讯云cos secretId
            'secretKey' => '', // 腾讯云cos secretKey
            'bucket'    => '', // 腾讯云cos bucket
            'region'    => '', // 腾讯云cos region
            'url'       => '', // 腾讯云cos url
        ],
        'oss' => [
            'AccessKeyId'     => '', // 阿里云oss AccessKeyId
            'AccessKeySecret' => '', // 阿里云oss AccessKeySecret
            'endpoint'        => '', // 阿里云oss Server
            'bucket'          => '', // 阿里云oss Bucket
        ],
    ];

    /** @var array[] 附件上传配置 */
    public $upload = [
        // 图片上传配置
        'image'  => [
            'extensions'     => ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'ico'], // 允许上传的文件后缀
            'limit'          => 1024 * 1, // 上传的文件大小限制，单位：kB，0为不限制

            // ---------- 图片特有配置 ----------/
            'thumb'          => 0, // 是否开启图片压缩功能 0不开启 1开启
            'width'          => 150, // 图片压缩后的宽度
            'zip_percentage' => 100, // 图片压缩比例
        ],
        // 音频上传配置
        'voice'  => [
            'extensions' => ['mp3', 'wma', 'wav', 'amr'],
            'limit'      => 1024 * 30,
        ],
        // 视频上传配置
        'video'  => [
            'extensions' => ['rm', 'rmvb', 'wmv', 'avi', 'mpg', 'mpeg', 'mp4'],
            'limit'      => 1024 * 50,
        ],
        // 办公文件上传配置
        'office' => [
            'extensions' => [
                // Word
                'wps', 'wpt', 'doc', 'dot', 'docx', 'docm', 'dotm',
                // Excel
                'et', 'ett', 'xls', 'xlt', 'xlsx', 'xlsm', 'xltx', 'xltm', 'xlsb', 'csv',
                // PPT
                'dps', 'dpt', 'ppt', 'pps', 'pot', 'pptx', 'ppsx', 'potx',
                // 其他
                'txt', 'prn', 'pdf', 'xml'
            ],
            'limit'      => 1024 * 30,
        ],
        // 压缩包上传配置
        'zip'    => [
            'extensions' => ['zip', 'rar'],
            'limit'      => 1024 * 100,
        ]
    ];
}

