# jcbase-yii2

<p>
  自用yii2基础扩展库，提供一些常用的封装，方便开发。
</p>

[![Latest Stable Version](https://img.shields.io/packagist/v/jcbowen/jcbase-yii2.svg)](https://packagist.org/packages/jcbowen/jcbase-yii2)
[![Total Downloads](https://img.shields.io/packagist/dt/jcbowen/jcbase-yii2.svg)](https://packagist.org/packages/jcbowen/jcbase-yii2)

### 已实现功能（未全部展示，更多功能请查看源码）

* 全局变量$_GPC，合并存放了web请求中传递给服务器的get、post、json数据
* 全局变量$_B，系统中的公共变量都会存到其中
* web基础控制器
* 数据模型基础活动类，实现update/insert时自动更新updated_at字段和created_at字段等功能
* 错误码定义类
* 验证码显示、扩展
* AES加解密
* 基础工具包
* CRUD类封装
* 默认附件表数据模型
* 文件上传类封装（本地/腾讯云cos/阿里云oss）
* get/post/socket请求封装
* 图片操作类封装
* 安全过滤类封装
* 模版引擎封装
* 路由优化封装
* 多平台短信发送（暂时只支持云通讯）

### 安装

```shell
composer require jcbowen/jcbase-yii2
```

### 配置说明

加入到params.php或者params-local.php中

```php
return [
    'setting'    => [
        'fileMode' => 755 // 文件权限
    ],
    'domain'     => [
        'frontend'         => 'https://frontend.domain.cn/', // 业务端域名，又称前端域名
        'backend'          => 'https://backend.domain.cn/', // 管理端域名
        'attachment_local' => 'https://frontend.domain.cn/', // 本地附件访问域名，一般推荐为业务端域名
        'attachment'       => 'https://frontend.domain.cn/', // 远程附件访问域名，为空时等于本地附件访问域名
    ],
    'jcFile' => [
        'attachmentRoot'      => '@frontend/web', // 附件本地存储根目录(不包含附件目录名，默认为：@webroot)
        'attachmentModel'     => 'Jcbowen\JcbaseYii2\models\Attachment', // 附件表模型，表中字段与默认字段名称不一致时需配置attachmentFieldsMap
        'attachmentFieldsMap' => [ // 附件表字段映射，默认为空，表示使用默认字段名称
            'id'         => 'id', // 主键，递增ID
            'group_id'   => 'group_id', // 分组ID
            'uid'        => 'uid', // 上传用户
            'type'       => 'type', // 附件类型
            'size'       => 'size', // 附件尺寸
            'width'      => 'width', // 图片宽度(像素)
            'height'     => 'height', // 图片高度(像素)
            'md5'        => 'md5', // 文件md5
            'filename'   => 'filename', // 附件上传时的文件名
            'attachment' => 'attachment', // 附件相对路径
            'is_display' => 'is_display', // 是否在选择器中显示
            'deleted_at' => 'deleted_at', // 删除时间
            'updated_at' => 'updated_at', // 更新时间
            'created_at' => 'created_at', // 上传时间
        ],
        'remoteConfig'        => [
            'cos' => [
                'secretId'  => '', // 腾讯云cos secretId
                'secretKey' => '', // 腾讯云cos secretKey
                'bucket'    => '', // 腾讯云cos bucket
                'region'    => '', // 腾讯云cos region
                'url'       => '' // 腾讯云cos url
            ],
            'oss' => [
                'AccessKeyId'     => '', // 阿里云oss AccessKeyId
                'AccessKeySecret' => '', // 阿里云oss AccessKeySecret
                'endpoint'        => '', // 阿里云oss Server
                'bucket'          => '', // 阿里云oss Bucket
            ],
        ]
    ]
    // 附件配置
    'attachment' => [
        'dir'        => 'attachment', // 本地附件目录名，默认为attachment
        'isRemote'   => 0, // 是否启用远程附件 0不启用 1启用
        'remoteType' => 'cos', // 远程附件类型，cos代表腾讯云cos，qiniu代表七牛云存储，oss代表阿里云oss
    ],
    'upload'     => [// 上传配置
        'image' => [// 图片上传配置
            'extensions' => [// 允许上传的文件后缀
                'gif',
                'jpg',
                'jpeg',
                'png',
                'bmp',
                'ico'
            ],
            'limit'      => 1024, // 上传的文件大小限制，单位：kB，0为不限制

            // ---------- 图片特有配置 ----------/
            'thumb'      => 0, // 是否开启图片压缩功能 0不开启 1开启
            'width'      => 150, // 图片压缩后的宽度
        ]
    ],
    'SmsConfig'  => [
        'type'       => 'YunTongXun', // 短信类型，aliyun代表阿里云短信，qcloud代表腾讯云短信，YunTongXun代表云通讯短信
        'YunTongXun' => [
            'AccountSid'   => '',
            'AccountToken' => '',
            'AppId'        => '',
            'ServerIP'     => 'app.cloopen.com',
            'ServerPort'   => '8883',
            'SoftVersion'  => '2013-12-26',
            'BodyType'     => 'json',
            'enableLog'    => true,
            'logPath'      => '@runtime/log/sms-ytx.txt',
        ]
    ],
]
```

