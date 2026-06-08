# jcbase-yii2

yii2基础扩展库，提供一些常用公共方法、基础控制器、常用工具的封装，节约yii2项目的开发时间。

[![Latest Stable Version](https://img.shields.io/packagist/v/jcbowen/jcbase-yii2.svg)](https://packagist.org/packages/jcbowen/jcbase-yii2)
[![Total Downloads](https://img.shields.io/packagist/dt/jcbowen/jcbase-yii2.svg)](https://packagist.org/packages/jcbowen/jcbase-yii2)

### 已实现功能（未全部展示，更多功能请查看源码）

* 全局变量$_GPC，合并存放了web请求中传递给服务器的get、post、json数据
* 全局变量$_B，系统中的公共变量都会存到其中
* web基础控制器
* 控制台基础控制器
* 数据模型基础活动类，实现update/insert时自动更新updated_at字段和created_at字段等功能
* 错误码定义类
* 验证码显示、扩展
* AES加解密
* SM4加解密
* 基础工具包
* CRUD类封装
* 默认附件表数据模型
* 文件上传类封装（本地/腾讯云cos/阿里云oss）
* get/post/socket请求封装
* 图片操作类封装
* 安全过滤类封装
* 模版引擎封装
* 路由优化封装
* 多平台短信发送（云通讯等）
* 支付通知数据处理
* 拼音转换功能
* 二维码生成
* Redis操作封装
* 地区代码处理
* 日历功能
* 字段过滤
* 内容处理
* 通信相关功能
* Excel处理
* 天气相关功能
* 缓存操作封装
* 支持配置字段类型，在toSave以及不asArray的actionDetail中自动转换字段类型（支持json/json&base64/serialize/round_money/rich_text类型）
* 组件数组访问支持

### 安装

```shell
composer require jcbowen/jcbase-yii2
```

#### 环境要求

- PHP版本：>=7.2.0 <8.0.0
- 需安装的PHP扩展：
  - openssl
  - curl
  - json
  - mbstring
  - simplexml
  - bcmath
  - libxml
  - pdo
  - iconv
  - gd
  - imagick

#### 依赖说明

安装后会自动安装以下依赖：
- yiisoft/yii2-redis ^2.0.18
- guzzlehttp/guzzle ^6.2 || ^7.0
- phpoffice/phpspreadsheet ^1.29.1
- aliyuncs/oss-sdk-php ~2.4
- qcloud/cos-sdk-v5 ^2.5
- wechatpay/wechatpay ^1.4
- tencentcloud/tencentcloud-sdk-php ^3.0

### 配置说明

加入到params.php或者params-local.php中

```php
return [
    // 基础设置
    'setting'    => [
        'fileMode' => 755 // 文件权限
    ],
    
    // 域名配置
    'domain'     => [
        'frontend'         => 'https://frontend.domain.cn/', // 业务端域名，又称前端域名
        'backend'          => 'https://backend.domain.cn/', // 管理端域名
        'attachment_local' => 'https://frontend.domain.cn/', // 本地附件访问域名，一般推荐为业务端域名
        'attachment'       => 'https://frontend.domain.cn/', // 远程附件访问域名，为空时等于本地附件访问域名
    ],
    
    // 文件上传配置
    'jcFile' => [
        'attachmentRoot'      => '@frontend/web', // 附件本地存储根目录(不包含附件目录名，默认为：@webroot)
        'attachmentModel'     => 'Jcbowen\JcbaseYii2\models\AttachmentModel', // 附件表模型，表中字段与默认字段名称不一致时需配置attachmentFieldsMap
        'attachmentFieldsMap' => [ // 附件表字段映射，默认为空，表示使用默认字段名称
            'id'         => 'id', // 主键，递增ID
            'sid'        => 'sid', // 附件所属站点
            'sid_sub'    => 'sid_sub', // 附件所属子站点
            'group_id'   => 'group_id', // 分组ID
            'appid'      => 'appid', // 应用id
            'uid'        => 'uid', // 上传用户
            'mid'        => 'mid', // 上传会员
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
    ],
    
    // 附件配置
    'attachment' => [
        'dir'        => 'attachment', // 本地附件目录名，默认为attachment
        'isRemote'   => 0, // 是否启用远程附件 0不启用 1启用
        'remoteType' => 'cos', // 远程附件类型，cos代表腾讯云cos，qiniu代表七牛云存储，oss代表阿里云oss
    ],
    
    // 上传配置
    'upload'     => [
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
    
    // 短信配置
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
    
    // 微信小程序配置
    'WeChatMiniProgramConfig' => [
        'app_id'                  => '', // 微信小程序AppID
        'mchId'                   => '', // 微信商户号
        'merchantCertificateSerial' => '', // 微信商户证书序列号
        'apiKey'                  => '', // 微信支付API v3密钥
        'notifyUrl'               => '', // 微信支付回调地址
    ],
    
    // 支付宝配置
    'alipay' => [
        'app_id'                   => '', // 支付宝AppID
        'rsaPrivateKeyFile'        => '@cert/alipay/rsa_private_key.txt', // 商户私钥文件路径
        'alipayRsaPublicKeyFile'   => '@cert/alipay/alipay_rsa_public_key.txt', // 支付宝公钥文件路径
        'notifyUrl'                => '', // 支付宝回调地址
    ],
    
    // 客户端白名单路由配置（用于客户端history模式）
    'clientWhiteRoute' => [
        '^api/', // API路由白名单
        '^admin/', // 管理端路由白名单
        '^login$', // 登录页白名单
    ],
]
```

### 使用示例

#### 1. Web基础控制器使用

```php
<?php

namespace app\controllers;

use Jcbowen\JcbaseYii2\base\WebController;
use Yii;

class SiteController extends WebController
{
    public function actionIndex()
    {
        // 可以直接使用 $_GPC 获取所有请求参数
        $id = $_GPC['id'];
        
        // 返回JSON响应
        return Yii::$app->response->data = [
            'id' => $id,
            'message' => 'Hello World',
        ];
    }
}
```

#### 2. CRUD类封装使用

```php
<?php

namespace app\controllers;

use Jcbowen\JcbaseYii2\base\WebController;
use Jcbowen\JcbaseYii2\components\CurdActionTrait;

class UserController extends WebController
{
    use CurdActionTrait;
    
    // 配置模型类
    public $modelClass = 'app\models\User';
    
    // 配置搜索模型类
    public $searchModelClass = 'app\models\UserSearch';
    
    // 自定义列表页字段
    public $listFields = ['id', 'username', 'email', 'created_at'];
    
    // 自定义详情页字段
    public $detailFields = ['id', 'username', 'email', 'created_at', 'updated_at'];
}
```

#### 3. 文件上传使用

```php
<?php

namespace app\controllers;

use Jcbowen\JcbaseYii2\base\WebController;
use Jcbowen\JcbaseYii2\components\File;

class UploadController extends WebController
{
    public function actionImage()
    {
        $file = new File();
        $result = $file->upload('image');
        
        if ($result['code'] == 0) {
            return $this->jsonReturn([
                'url' => $result['data']['url'],
                'message' => '上传成功',
            ]);
        } else {
            return $this->jsonReturn([], $result['code'], $result['message']);
        }
    }
}
```

#### 4. AES加解密使用

```php
<?php

use Jcbowen\JcbaseYii2\components\AES;

// 加密
$key = 'your-aes-key';
$iv = 'your-aes-iv';
$text = '需要加密的内容';
$encrypted = AES::encrypt($text, $key, $iv);

// 解密
$decrypted = AES::decrypt($encrypted, $key, $iv);
```

#### 5. Redis操作使用

```php
<?php

use Jcbowen\JcbaseYii2\components\Redis;

// 获取Redis实例
$redis = new Redis();

// 设置值
$redis->set('key', 'value');

// 获取值
$value = $redis->get('key');

// 设置带过期时间的值
$redis->setex('key', 3600, 'value');

// 哈希表操作
$redis->hset('hash', 'field', 'value');
$hashValue = $redis->hget('hash', 'field');
```

#### 6. Excel处理使用

```php
<?php

use Jcbowen\JcbaseYii2\components\Excel;

// 导出Excel
$excel = new Excel();
$data = [
    ['id' => 1, 'name' => '张三', 'email' => 'zhangsan@example.com'],
    ['id' => 2, 'name' => '李四', 'email' => 'lisi@example.com'],
];
$headers = ['ID', '姓名', '邮箱'];
$excel->export($data, $headers, '用户列表');

// 导入Excel
$excel = new Excel();
$filePath = 'path/to/excel.xlsx';
$data = $excel->import($filePath);
```
