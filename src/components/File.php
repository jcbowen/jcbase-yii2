<?php

namespace Jcbowen\JcbaseYii2\components;

use Jcbowen\JcbaseYii2\components\jobs\FileRemoteUpload;
use Jcbowen\JcbaseYii2\config\struct\FileStruct;
use OSS\Http\RequestCore_Exception;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\helpers\FileHelper;
use Qcloud\Cos\Client;
use OSS\OssClient;
use OSS\Core\OssException;

// 提供魔术方法
use Jcbowen\JcbaseYii2\models\AttachmentModel;

class File extends Model
{
    // 文件上传支持的类型
    public static $fileTypes = ['image', 'thumb', 'voice', 'video', 'audio', 'office', 'zip'];

    //----------- 储存配置参数 ---------/

    /**
     * @var AttachmentModel 远程附件数据模型类
     */
    public $attachmentModel = 'Jcbowen\JcbaseYii2\models\AttachmentModel';

    /**
     * @var array 远程附件表数据模型字段变更模型（传递过来的配置）
     */
    public $attachmentFieldsMap = [];

    /**
     * @var array 远程附件表数据模型字段变更模型（默认）
     */
    private $attachmentFieldsMapDefault = [
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
    ];

    /**
     * @var string 附件本地根目录(不包含附件目录名，默认为：@webroot)
     */
    public $attachmentRoot;

    /**
     * @var array 远程附件配置
     */
    public $remoteConfig = [];

    //----------- 正文 ---------/

    /**
     * @var string 上传图片名
     */
    public $name;

    /**
     * @var string 临时文件
     */
    public $tmp_name;

    /**
     * @var string 上传图片类型
     */
    public $type;

    /**
     * @var int 上传文件尺寸（单位：Bytes）
     */
    public $size;

    /**
     * @var int|string 错误代码
     */
    public $error;

    /**
     * @var string
     */
    public $base64;

    /**
     * @var string 图片扩展名
     */
    private $_extension = '';

    /**
     * @var string 图片MD5
     */
    private $_md5 = '';

    /**
     * @var string 上传保存路径
     */
    public $_savePath;

    /**
     * @var array 所有上传的文件数据
     */
    private static $_files = [];

    public function init()
    {
        parent::init();

        // 初始化远程附件数据模型类
        $this->attachmentModel = Yii::$app->params['jcFile']['attachmentModel'];

        // 初始化远程附件表数据模型字段变更模型
        $this->attachmentFieldsMap = array_merge($this->attachmentFieldsMapDefault, (array)Yii::$app->params['jcFile']['attachmentFieldsMap']);

        // 初始化附件本地根目录
        $attachmentRoot       = rtrim(Yii::$app->params['jcFile']['attachmentRoot'] ?: '@webroot', '/') . '/';
        $attachmentRoot       .= Yii::$app->params['attachment']['dir'] ?: 'attachment';
        $this->attachmentRoot = rtrim(Yii::getAlias($attachmentRoot), '/');

        // 初始化远程附件配置
        $this->remoteConfig = Yii::$app->params['jcFile']['remoteConfig'];
    }

    /**
     * 根据文件流字段名获取实例
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $name
     *
     * @return File|null
     * @lasttime: 2023/2/12 4:57 PM
     */
    public static function getInstanceByName(string $name): ?File
    {
        $files = self::loadFiles();
        return isset($files[$name]) ? new static([
            'name'                => $files[$name]['name'],
            'type'                => $files[$name]['type'],
            'tmp_name'            => $files[$name]['tmp_name'],
            'error'               => $files[$name]['error'],
            'size'                => $files[$name]['size'],
            'attachmentModel'     => Yii::$app->params['jcFile']['attachmentModel'],
            'attachmentFieldsMap' => (array)Yii::$app->params['jcFile']['attachmentFieldsMap'],
            'attachmentRoot'      => Yii::$app->params['jcFile']['attachmentRoot'],
            'remoteConfig'        => Yii::$app->params['jcFile']['remoteConfig'],
        ]) : null;
    }

    /**
     * 根据base64字符串获取实例
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $base64
     *
     * @return File
     * @lasttime: 2023/2/12 4:57 PM
     */
    public static function getInstanceByBase64(string $base64): File
    {
        if (!preg_match('/^data:(.*);base64,(.*)/', $base64, $matches)) {
            $matches[1] = 'image/jpg';
            $matches[2] = $base64;
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'php');
        $size     = file_put_contents($tempFile, base64_decode($matches[2]));
        return new static([
            'name'                => basename($tempFile) . str_replace('/', '.', $matches[1]),
            'type'                => $matches[1],
            'tmp_name'            => $tempFile,
            'error'               => 0,
            'size'                => $size,
            'base64'              => $base64,
            'attachmentModel'     => Yii::$app->params['jcFile']['attachmentModel'],
            'attachmentFieldsMap' => (array)Yii::$app->params['jcFile']['attachmentFieldsMap'],
            'attachmentRoot'      => Yii::$app->params['jcFile']['attachmentRoot'],
            'remoteConfig'        => Yii::$app->params['jcFile']['remoteConfig'],
        ]);
    }

    /**
     * 初始化上传文件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array
     * @lasttime: 2023/2/12 4:58 PM
     */
    private static function loadFiles(): array
    {
        if (empty(self::$_files)) {
            self::$_files = [];
            if (isset($_FILES) && is_array($_FILES)) {
                foreach ($_FILES as $class => $info) {
                    self::loadFilesRecursive($class, $info['name'], $info['type'], $info['tmp_name'], $info['error'], $info['size']);
                }
            }
        }
        return self::$_files;
    }

    /**
     * 递归加载文件
     *
     * @param $key
     * @param $names
     * @param $types
     * @param $tmp_names
     * @param $errors
     * @param $sizes
     */
    private static function loadFilesRecursive($key, $names, $types, $tmp_names, $errors, $sizes)
    {
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                self::loadFilesRecursive($key . '[' . $i . ']', $name, $types[$i], $tmp_names[$i], $errors[$i], $sizes[$i]);
            }
        } elseif ((int)$errors !== UPLOAD_ERR_NO_FILE) {
            self::$_files[$key] = [
                'name'     => $names,
                'type'     => $types,
                'tmp_name' => $tmp_names,
                'error'    => $errors,
                'size'     => $sizes,
            ];
        }
    }

    /**
     * 获取上传文件去掉扩展名的名字
     * @return string
     */
    public function getBaseName(): string
    {
        $pathInfo = pathinfo('_' . $this->name, PATHINFO_FILENAME);
        return mb_substr($pathInfo, 1, mb_strlen($pathInfo, '8bit'), '8bit');
    }

    /**
     * 获取上传文件扩展名
     *
     * @param string $name
     *
     * @return string
     */
    public function getExtension(string $name = ''): string
    {
        $name = $name ?: $this->name;
        if (!$this->_extension) {
            $this->_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        }
        return $this->_extension;
    }

    /**
     * 判断上传是否错误
     * @return bool
     */
    public function hasError(): bool
    {
        //error == UPLOAD_ERR_OK  其值为 0，没有错误发生，文件上传成功。
        return $this->error !== UPLOAD_ERR_OK;
    }

    /**
     * 计算临时文件md5
     * @return string
     */
    public function md5(): string
    {
        if (!$this->_md5) {
            $this->_md5 = md5_file($this->tmp_name);
        }
        return $this->_md5;
    }

    /**
     * 储存单位换算
     *
     * @param $fileSize
     *
     * @return string
     */
    public function storageUnitConversion($fileSize): string
    {
        $size = sprintf('%u', $fileSize);
        if ($size == 0) {
            return ('0 Bytes');
        }
        $sizeName = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        return round($size / (1024 ** ($i = (int)log($size, 1024))), 2) . $sizeName[$i];
    }

    /**
     * 文件上传
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $type     文件类型['image', 'thumb', 'voice', 'video', 'audio', 'office', 'zip']
     * @param string $name     指定文件名
     * @param bool   $compress 是否压缩
     *
     * @return array|null
     * @throws RequestCore_Exception
     * @throws \yii\db\Exception
     * @lasttime: 2023/2/12 11:53 AM
     */
    public function file_upload(string $type = 'image', string $name = '', bool $compress = false): ?array
    {
        global $_GPC;
        if (empty($this->size))
            return Util::error(ErrCode::PARAMETER_ERROR, '没有上传内容');

        $group_id = intval($_GPC['group_id']);
        $group_id = $group_id === 0 ? '-1' : $group_id;

        $type_setting = 'image';

        // 根据要上传的类型，输出默认配置
        switch ($type) {
            case 'image':
            case 'thumb':
                $type     = 'image'; // 重置类型为image
                $allowExt = ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'ico'];
                $limit    = 3 * 1024;// 单位KB
                // $type_setting = 'image';
                break;
            case 'voice':
            case 'audio':
                $type         = 'voice'; // 重置类型为voice
                $allowExt     = ['mp3', 'wma', 'wav', 'amr'];
                $limit        = 50 * 1024;
                $type_setting = 'voice';
                break;
            case 'video':
                $allowExt     = ['rm', 'rmvb', 'wmv', 'avi', 'mpg', 'mpeg', 'mp4'];
                $limit        = 300 * 1024;
                $type_setting = 'video';
                break;
            case 'office':
                $allowExt     = [
                    'wps',
                    'wpt',
                    'doc',
                    'dot',
                    'docx',
                    'docm',
                    'dotm', // 文字
                    'et',
                    'ett',
                    'xls',
                    'xlt',
                    'xlsx',
                    'xlsm',
                    'xltx',
                    'xltm',
                    'xlsb', // 表格
                    'dps',
                    'dpt',
                    'ppt',
                    'pps',
                    'pot',
                    'pptx',
                    'ppsx',
                    'potx',// 演示
                    'txt',
                    'csv',
                    'prn',// 文本文件
                    'pdf',// PDF
                    'xml'// XML
                ];
                $limit        = 50 * 1024;
                $type_setting = 'office';
                break;
            case 'zip':
                $allowExt     = ['zip', 'rar'];
                $limit        = 500 * 1024;
                $type_setting = 'zip';
                break;
            default:
                return Util::error(ErrCode::ILLEGAL_TYPE, '不合法的文件类型');
        }

        // 读取配置和设置
        $setting = Yii::$app->params['upload'][$type_setting] ?: [];

        // 如果已经配置过，以配置过的为准
        $allowExt = !empty($setting['extensions']) ? $setting['extensions'] : $allowExt; // 允许的文件后缀
        $limit    = !empty($setting['limit']) ? $setting['limit'] : $limit; // 允许的文件大小

        // 获取文件后缀名
        $ext = $this->getExtension();

        // 禁止上传的文件后缀
        $harmType = ['asp', 'php', 'jsp', 'js', 'css', 'php3', 'php4', 'php5', 'ashx', 'aspx', 'exe', 'cgi', 'py',
                     'sh'];

        // 验证后缀是否被上传的文件类型所允许
        if (!in_array(strtolower($ext), $allowExt) || in_array(strtolower($ext), $harmType))
            return Util::error(ErrCode::ILLEGAL_FORMAT, '不允许上传此类文件');

        // 计算文件大小是否符合规范
        if (!empty($limit) && $limit * 1024 < $this->size) {
            $maxSize = $this->storageUnitConversion($limit * 1024);
            return Util::error(ErrCode::ILLEGAL_SIZE, "上传的文件超过大小限制，请上传小于 $maxSize 的文件");
        }

        // 如果已经上传过了，就直接通过数据库里的数据进行返回
        if ($row = $this->attachmentModel::find()->where([
            $this->attachmentFieldsMap['md5']        => $this->md5(),
            $this->attachmentFieldsMap['deleted_at'] => NO_TIME
        ])->one()) {
            // 更新修改时间，以便排序需要
            $row->setAttributes([$this->attachmentFieldsMap['updated_at'] => date('Y-m-d H:i:s')]);
            // $row->updated_at = date('Y-m-d H:i:s');
            $row->save();

            return [
                'attach_id'  => $row['id'] ?? '0',
                'name'       => $row['filename'] ?? '',
                'type'       => $row['type'] ?? '',
                'data_type'  => $this->type ?? '',
                'ext'        => $ext ?? '',
                'filename'   => $row['filename'] ?? '',
                'attachment' => $row['attachment'] ?? '',
                'url'        => Util::toMedia($row['attachment']) ?? '',
                'is_image'   => $row['type'] == FileStruct::ATTACH_TYPE_IMAGE ? '1' : '0',
                'size'       => $row['size'] ?? 0,
                'filesize'   => $this->storageUnitConversion($row['size']) ?? '0',
                'width'      => $row['width'] ?? '0',
                'height'     => $row['height'] ?? '0',
                'group_id'   => $row['group_id'] ?? '-1',
                'state'      => 'SUCCESS'
            ];
        }

        if (empty($name) || 'auto' == $name) {
            $path            = "{$type}s/" . date('Y/m/');
            $this->_savePath = FileHelper::normalizePath($this->attachmentRoot . '/' . $path, '/');
            try {
                FileHelper::createDirectory($this->_savePath);
            } catch (Exception $e) {
                return Util::error(ErrCode::UNKNOWN, '创建存储目录失败', [
                    'errCode' => $e->getCode(),
                    'errmsg'  => $e->getMessage(),
                ]);
            }
            $fileName = $this->file_random_name($this->_savePath, $ext);

            $path = $path . $fileName;
        } else {
            $this->_savePath = FileHelper::normalizePath(dirname($this->attachmentRoot . '/' . $name), '/');
            try {
                FileHelper::createDirectory($this->_savePath);
            } catch (Exception $e) {
                return Util::error(ErrCode::UNKNOWN, '创建存储目录失败', [
                    'errCode' => $e->getCode(),
                    'errmsg'  => $e->getMessage(),
                ]);
            }
            if (!Util::strExists($name, $ext)) {
                $name .= '.' . $ext;
            }
            $path = $name;
        }

        // 带了文件名的保存路径
        $savePathFile = FileHelper::normalizePath($this->attachmentRoot . '/' . $path);

        // 如果图片被旋转过，将图片旋转回来
        $image = '';
        if (isset($setting['zip_percentage']) && $setting['zip_percentage'] == 100 && extension_loaded('exif')) {
            $exif = exif_read_data($this->tmp_name);
            if (!empty($exif['THUMBNAIL']['Orientation'])) {
                $image = imagecreatefromstring(file_get_contents($this->tmp_name));
                switch ($exif['THUMBNAIL']['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    default:
                        $image = imagerotate($image, 0, 0);
                        break;
                }
            }
        }
        if (empty($image)) {
            // 将上传文件移动到附件目录
            try {
                $newImage = $this->unlinkFile($this->tmp_name, $savePathFile);
            } catch (Exception $e) {
                return Util::error(ErrCode::UNKNOWN, '文件移动到附件目录失败', [
                    'errCode' => $e->getCode(),
                    'errmsg'  => $e->getMessage(),
                ]);
            }
        } else {
            // 生成旋转好的图片
            $newImage = imagejpeg($image, $savePathFile);
            imagedestroy($image);
        }
        if (empty($newImage)) {
            return Util::error(ErrCode::NO_PERMISSION, '文件上传失败, 请将 附件目录 及其子目录的权限设为777 <br> (如果777上传失败,可尝试将目录设置为755)');
        }

        // 根据后台设置对图片进行压缩
        if ('image' == $type && $compress) {
            $this->file_image_quality($savePathFile, $savePathFile, $ext);
        }

        $option          = Util::arrayElements(['uploadtype', 'dest_dir', 'width'], $_POST);
        $option['width'] = intval($option['width']);

        $fullName = $this->attachmentRoot . '/' . $path;

        // 图片缩略
        if ($type == 'image') {
            $thumb = empty($setting['thumb']) ? 0 : 1;
            $width = intval($setting['width']);
            if (isset($option['thumb'])) {
                $thumb = empty($option['thumb']) ? 0 : 1;
            }
            if (!empty($option['width'])) {
                $width = $option['width'];
            }
            if ($thumb == 1 && $width > 0) {
                try {
                    $thumbnail = $this->file_image_thumb($fullName, '', $width);
                } catch (Exception $e) {
                    return Util::error(ErrCode::UNKNOWN, '图片压缩失败', [
                        'errCode' => $e->getCode(),
                        'errmsg'  => $e->getMessage(),
                    ]);
                }
                @FileHelper::unlink($fullName);
                if (Util::isError($thumbnail)) {
                    return Util::error(ErrCode::UNKNOWN, $thumbnail['errmsg']);
                } else {
                    // $fileName = pathinfo($thumbnail, PATHINFO_BASENAME);
                    $pathname = $thumbnail;
                    $fullName = $this->attachmentRoot . '/' . $pathname;
                }
            }
        }

        $int_type = 0;
        switch ($type) {
            case 'image':
            case 'thumb':
                $int_type = FileStruct::ATTACH_TYPE_IMAGE;
                break;
            case 'audio':
            case 'voice':
                $int_type = FileStruct::ATTACH_TYPE_VOICE;
                break;
            case 'office':
                $int_type = FileStruct::ATTACH_TYPE_OFFICE;
                break;
            case 'zip':
                $int_type = FileStruct::ATTACH_TYPE_ZIP;
                break;
            case 'video':
                $int_type = FileStruct::ATTACH_TYPE_VIDEO;
                break;
        }

        $info = [
            'name'       => $this->name,
            'type'       => $int_type,
            'data_type'  => $this->type,
            'ext'        => $ext,
            'filename'   => $this->name,
            'attachment' => $path,
            'url'        => Util::toMedia($path),
            'is_image'   => $int_type == FileStruct::ATTACH_TYPE_IMAGE ? 1 : 0,
            'size'       => $this->size,
            'width'      => 0,
            'height'     => 0,
            'group_id'   => $group_id,
            'state'      => 'SUCCESS'
        ];

        $size             = filesize($fullName);
        $info['filesize'] = $this->storageUnitConversion($size);

        if ($int_type == FileStruct::ATTACH_TYPE_IMAGE) {
            $size           = getimagesize($fullName);
            $info['width']  = $size[0];
            $info['height'] = $size[1];
        }

        // 开启远程附件，并配置了远程附件类型的情况下才执行远程附件上传
        if (!empty(Yii::$app->params['attachment']['isRemote']) && !empty(Yii::$app->params['attachment']['remoteType'])) {
            // 没有安装队列扩展或小于5M的，直接上传
            if (empty(Yii::$app->components['queue']) || $this->size < 5 * 1024 * 1024) {
                $remoteResult = $this->file_remote_upload($path);
                if (Util::isError($remoteResult)) {
                    $result['message'] = '远程附件上传失败，请检查配置并重新上传';
                    @FileHelper::unlink($fullName);
                    return Util::error(ErrCode::UNKNOWN, $result['message']);
                }
            } else {
                $queue = Yii::$app->queue;
                if (empty($queue->push(new FileRemoteUpload([
                    'fileInstance' => $this,
                    'filePath'     => $path,
                    'fullPath'     => $fullName
                ]))))
                    return Util::error(ErrCode::UNKNOWN, '远程附件上传任务添加失败，请检查队列配置并重新上传');
                $info['url_local'] = Util::toMedia($path, true);
            }
        }

        $info['url'] = Util::toMedia($path);

        $attach_id = $this->saveDb($info);
        if (Util::isError($attach_id)) {
            // 数据表插入失败的时候删除文件
            @FileHelper::unlink($fullName);
            return $attach_id;
        }

        $info['attach_id'] = $attach_id;

        return $info;
    }

    /**
     * 将文件上传到远程附件中
     * 目前仅支持腾讯云cos/阿里云oss
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param      $filename
     * @param bool $auto_delete_local
     *
     * @return array|bool
     * @throws RequestCore_Exception
     * @lasttime: 2022/8/18 2:58 PM
     */
    public function file_remote_upload($filename, bool $auto_delete_local = true)
    {
        if (empty(Yii::$app->params['attachment']['isRemote']) || empty(Yii::$app->params['attachment']['remoteType'])) return false;

        $fullPath = $this->attachmentRoot . '/' . $filename;
        $fullPath = FileHelper::normalizePath($fullPath);

        $run = false;

        if (Yii::$app->params['attachment']['remoteType'] === 'cos') { // 腾讯云cos
            try {
                $bucket = $this->remoteConfig['cos']['bucket'];

                $cosClient = new Client([
                    'region'      => $this->remoteConfig['cos']['region'],
                    'credentials' => [
                        'secretId'  => $this->remoteConfig['cos']['secretId'],
                        'secretKey' => $this->remoteConfig['cos']['secretKey'],
                    ],
                ]);
                $cosClient->Upload($bucket, $filename, fopen($fullPath, 'rb'));
                $run = true;
            } catch (\Exception $e) {
                return Util::error(ErrCode::UNKNOWN, '上传cos远程附件失败', [
                    'errCode' => $e->getCode(),
                    'errmsg'  => $e->getMessage(),
                ]);
            }
        } else if (Yii::$app->params['attachment']['remoteType'] === 'oss') { // 阿里云oss
            try {
                $ossClient = new OssClient($this->remoteConfig['oss']['AccessKeyId'], $this->remoteConfig['oss']['AccessKeySecret'], $this->remoteConfig['oss']['endpoint']);
                $ossClient->uploadFile($this->remoteConfig['oss']['bucket'], $filename, $fullPath);
                $run = true;
            } catch (OssException $e) {
                return Util::error(ErrCode::UNKNOWN, $e->getMessage(), ['from' => 'file_remote_upload->oss']);
            }
        }

        // 如果上传成功，且打开了自动删除本地文件，则删除本地文件
        if ($run && $auto_delete_local && file_exists($fullPath)) {
            @FileHelper::unlink($fullPath);
        }

        return true;
    }

    /**
     * 删除附件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $filePath
     *
     * @return array|bool
     * @throws RequestCore_Exception
     * @lasttime: 2023/2/12 23:21
     */
    public function file_delete($filePath)
    {
        if (empty($filePath)) return false;

        $filePath = Safe::gpcPath($filePath);

        // 查询附件记录
        $row    = call_user_func($this->attachmentModel . '::find');
        $record = $row->where([$this->attachmentFieldsMap['attachment'] => $filePath])->one();
        if (empty($record))
            return Util::error(ErrCode::NOT_EXIST, '附件记录不存在');

        $tr = Yii::$app->db->beginTransaction();

        // 删除记录
        $result = $record->delete();
        if (empty($result)) {
            $tr->rollBack();
            return Util::error(ErrCode::UNKNOWN, '删除附件记录失败');
        }

        // 初始化删除状态
        $delFileState = true;

        // 删除远程附件
        $result = $this->file_remote_delete($filePath);
        if (Util::isError($result)) {
            return $result;
        }

        // 删除本地附件(如果删除远程附件已经报错，就不再执行)
        if (!$delFileState && file_exists($this->attachmentRoot . '/' . $filePath)) {
            $unlinkResult = FileHelper::unlink($this->attachmentRoot . '/' . $filePath);
            if (!$unlinkResult) {
                $delFileState = false;
            }
        }

        // 如果删除失败，回滚
        if (!$delFileState) {
            $tr->rollBack();
            return Util::error(ErrCode::UNKNOWN, '删除附件失败');
        }

        try {
            $tr->commit();
            return true;
        } catch (\yii\db\Exception $e) {
            return Util::error(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，删除附件失败，请稍后再试', [
                'errCode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * 删除远程附件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $filePath 附件相对路径
     *
     * @return array|bool
     * @throws RequestCore_Exception
     * @lasttime: 2023/2/12 22:50
     */
    public function file_remote_delete(string $filePath)
    {
        if (empty($filePath) || empty(Yii::$app->params['attachment']['isRemote']) || empty(Yii::$app->params['attachment']['remoteType'])) return true;

        if (!empty(Yii::$app->params['attachment']['remoteType'])) {
            switch (Yii::$app->params['attachment']['remoteType']) {
                case ATTACH_COS:
                    try {
                        $bucket = $this->remoteConfig['cos']['bucket'];

                        $cosClient = new Client([
                            'region'      => $this->remoteConfig['cos']['region'],
                            'credentials' => [
                                'secretId'  => $this->remoteConfig['cos']['secretId'],
                                'secretKey' => $this->remoteConfig['cos']['secretKey'],
                            ],
                        ]);
                        $cosClient->deleteObjects(array(
                            'Bucket'  => $bucket,
                            'Objects' => [['Key' => $filePath]]
                        ));
                    } catch (\Exception $e) {
                        return Util::error(ErrCode::UNKNOWN, '删除cos远程文件失败', [
                            'errCode' => $e->getCode(),
                            'errmsg'  => $e->getMessage(),
                        ]);
                    }
                    break;
                case ATTACH_OSS:
                    try {
                        $ossClient = new OssClient($this->remoteConfig['oss']['AccessKeyId'], $this->remoteConfig['oss']['AccessKeySecret'], $this->remoteConfig['oss']['endpoint']);
                        $ossClient->deleteObject($this->remoteConfig['oss']['bucket'], $filePath);
                    } catch (OssException $e) {
                        return Util::error(ErrCode::UNKNOWN, '删除oss远程文件失败', [
                            'errCode' => $e->getCode(),
                            'errmsg'  => $e->getMessage(),
                        ]);
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * 保存附件记录到数据库
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $data
     *
     * @return array|string
     * @throws \yii\db\Exception
     * @lasttime: 2023/7/22 14:48
     */
    public function saveDb($data)
    {
        global $_B, $_GPC;

        $time = date('Y-m-d H:i:s');

        $newData = [
            $this->attachmentFieldsMap['sid']        => intval($data['sid']),
            $this->attachmentFieldsMap['sid_sub']    => intval($data['sid_sub']),
            $this->attachmentFieldsMap['group_id']   => intval($data['group_id']),
            $this->attachmentFieldsMap['appid']      => Yii::$app->id ?: '',
            $this->attachmentFieldsMap['uid']        => intval($_B['uid']),
            $this->attachmentFieldsMap['mid']        => intval($_B['mid']),
            $this->attachmentFieldsMap['type']       => $data['type'],
            $this->attachmentFieldsMap['size']       => $data['size'],
            $this->attachmentFieldsMap['width']      => $data['width'],
            $this->attachmentFieldsMap['height']     => $data['height'],
            $this->attachmentFieldsMap['md5']        => $this->_md5,
            $this->attachmentFieldsMap['filename']   => $this->name,
            $this->attachmentFieldsMap['attachment'] => $data['attachment'],
            $this->attachmentFieldsMap['is_display'] => intval($_GPC['is_display']) ? 1 : 0,
            $this->attachmentFieldsMap['updated_at'] => $time,
            $this->attachmentFieldsMap['created_at'] => $time
        ];

        /** @var ActiveRecord $model */
        $model = new $this->attachmentModel();

        // 根据$model的getAttributes获取$attachmentModel的所有字段
        $modelFields = $model->getAttributes();

        // 验证字段是否都存在，不存在就unset
        foreach ($newData as $key => $value) {
            if (!array_key_exists($key, $modelFields))
                unset($newData[$key]);
        }

        if ($model->load($newData, '') && $model->save())
            return Yii::$app->db->getLastInsertID();

        $errors = $model->errors;
        if (!empty($errors)) {
            $errmsg = 'errmsg:';
            foreach ($errors as $item) {
                $errmsg = "【" . implode('', $item) . "】";
            }
            return Util::error(ErrCode::UNKNOWN, $errmsg, $errors);
        }
        return Util::error(ErrCode::UNKNOWN, '附件入库失败，未知错误', $errors);
    }

    //--------------------------------------/


    /**
     * 附件进行缩略
     *
     * @param        $srcFile
     * @param string $desFile
     * @param int    $width
     *
     * @return array|bool|string|string[]
     * @throws Exception
     */
    public function file_image_thumb($srcFile, string $desFile = '', int $width = 0)
    {
        if (empty($desFile)) {
            $ext    = pathinfo($srcFile, PATHINFO_EXTENSION);
            $srcDir = dirname($srcFile);
            do {
                $desFile = $srcDir . '/' . Util::random(30) . ".$ext";
            } while (file_exists($desFile));
        }

        $des = dirname($desFile);
        if (!is_dir($des)) {
            if (!FileHelper::createDirectory($des)) {
                return Util::error(ErrCode::UNKNOWN, '创建目录失败');
            }
        } elseif (!is_writable($des)) {
            return Util::error(ErrCode::UNKNOWN, '目录无法写入');
        }
        $org_info = @getimagesize($srcFile);
        if ($org_info) {
            if (0 == $width || $width > $org_info[0]) {
                copy($srcFile, $desFile);

                return str_replace($this->attachmentRoot . '/', '', $desFile);
            }
        }
        $scale_org = $org_info[0] / $org_info[1];
        $height    = $width / $scale_org;
        $desFile   = Image::create($srcFile)->resize($width, $height)->saveTo($desFile);
        if (!$desFile) return false;

        return str_replace($this->attachmentRoot . '/', '', $desFile);
    }

    /**
     * 根据全局设置进行图片压缩
     *
     * @param $src
     * @param $to_path
     * @param $ext
     *
     * @return string|mixed
     */
    public function file_image_quality($src, $to_path, $ext)
    {
        if ('gif' == strtolower($ext)) return true;
        $quality = intval(Yii::$app->params['upload']['image']['zip_percentage']);// 百分比
        if ($quality <= 0 || $quality >= 100) return true;

        if (filesize($src) / 1024 > 5120) return true;

        return Image::create($src)->saveTo($to_path, $quality);
    }

    public function file_random_name($dir, $ext): string
    {
        do {
            $filename = date('dHis') . Util::random(22) . '.' . $ext;
        } while (file_exists($dir . $filename));

        return $filename;
    }

    public function file_remote_attach_fetch($url, $limit = 0, $path = '')
    {
        $url = trim($url);
        if (empty($url)) {
            return Util::error(ErrCode::PARAMETER_ERROR, '文件地址不存在');
        }
        $resp = Communication::get($url);

        if (Util::isError($resp)) {
            return Util::error(ErrCode::UNKNOWN, '提取文件失败, 错误信息: ' . $resp['message']);
        }
        if (200 != intval($resp['code'])) {
            return Util::error(ErrCode::NOT_EXIST, '提取文件失败: 未找到该资源文件.');
        }
        $get_headers = $this->file_media_content_type($url);
        if (empty($get_headers)) {
            return Util::error(ErrCode::ILLEGAL_TYPE, '提取资源失败, 资源文件类型错误.');
        } else {
            $ext  = $get_headers['ext'];
            $type = $get_headers['type'];
        }

        if (empty($path)) {
            $path = $type . "/" . date('Y/m/');
        } else {
            $path = Util::parsePath($path);
        }
        if (!$path) {
            return Util::error(ErrCode::PARAMETER_INVALID, '提取文件失败: 上传路径配置有误.');
        }

        if (!is_dir($this->attachmentRoot . '/' . $path)) {
            try {
                if (!FileHelper::createDirectory($this->attachmentRoot . '/' . $path)) {
                    return Util::error(ErrCode::UNAUTHORIZED, '提取文件失败: 权限不足.');
                }
            } catch (Exception $e) {
                return Util::error(ErrCode::UNAUTHORIZED, '提取文件失败: ' . $e->getMessage());
            }
        }

        if (!$limit) {// 调用时没有限制大小则读取配置中的限制大小
            if ('images' == $type) {
                $limit = intval(Yii::$app->params['upload']['image']['limit']) * 1024;
            } else {
                $limit = intval(Yii::$app->params['upload']['audio']['limit']) * 1024;
            }
        } else {
            $limit = $limit * 1024;
        }
        if (!empty($limit) && intval($resp['headers']['Content-Length']) > $limit) {
            return Util::error(ErrCode::ILLEGAL_SIZE, '上传的文件过大(' . $this->storageUnitConversion($resp['headers']['Content-Length']) . ' > ' . $this->storageUnitConversion($limit));
        }
        $filename = $this->file_random_name($this->attachmentRoot . '/' . $path, $ext);
        $pathname = $path . $filename;
        $fullName = $this->attachmentRoot . '/' . $pathname;
        if (!file_put_contents($fullName, $resp['content'])) {
            return Util::error(ErrCode::UNKNOWN, '提取失败.');
        }

        return $pathname;
    }

    /**
     * 媒体文件类型
     *
     * @param $url
     *
     * @return bool|string[]
     */
    public function file_media_content_type($url)
    {
        $file_header = Util::getHeaders($url, 1);
        if (empty($url) || !is_array($file_header)) {
            return false;
        }
        switch ($file_header['Content-Type']) {
            case 'application/x-jpg':
            case 'image/jpg':
            case 'image/jpeg':
                $ext  = 'jpg';
                $type = 'images';
                break;
            case 'image/png':
                $ext  = 'png';
                $type = 'images';
                break;
            case 'image/gif':
                $ext  = 'gif';
                $type = 'images';
                break;
            case 'video/mp4':
            case 'video/mpeg4':
                $ext  = 'mp4';
                $type = 'videos';
                break;
            case 'video/x-ms-wmv':
                $ext  = 'wmv';
                $type = 'videos';
                break;
            case 'audio/mpeg':
                $ext  = 'mp3';
                $type = 'audios';
                break;
            case 'audio/mp4':
                $ext  = 'mp4';
                $type = 'audios';
                break;
            case 'audio/x-ms-wma':
                $ext  = 'wma';
                $type = 'audios';
                break;
            default:
                return false;
        }

        return array('ext' => $ext, 'type' => $type);
    }

    public function file_allowed_media($type)
    {
        if (!in_array($type, ['image', 'audio', 'video'])) {
            return [];
        }
        return Yii::$app->params['upload'][$type]['extensions'];

    }

    /**
     * 检查文件是否为图片
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $url
     *
     * @return bool
     * @lasttime: 2022/3/19 7:02 下午
     */
    public function file_is_image($url): bool
    {
        $allowed_media = $this->file_allowed_media('image');

        if ('//' == substr($url, 0, 2)) {
            $url = 'http:' . $url;
        }
        if (0 == strpos($url, Yii::$app->params['domain']['attachment_local'] . 'attachment/')) {
            $url = str_replace(Yii::$app->params['domain']['attachment_local'] . 'attachment/', $this->attachmentRoot . '/', $url);
        }
        $lower_url = strtolower($url);
        if (('http://' == substr($lower_url, 0, 7)) || ('https://' == substr($lower_url, 0, 8))) {
            $analysis_url = parse_url($lower_url);
            $preg_str     = '/.*(\.' . implode('|\.', $allowed_media) . ')$/';
            if (!empty($analysis_url['query']) || !preg_match($preg_str, $lower_url) || !preg_match($preg_str, $analysis_url['path'])) {
                return false;
            }
            $img_headers = $this->file_media_content_type($url);
            if (empty($img_headers) || !in_array($img_headers['ext'], $allowed_media)) {
                return false;
            }
        }

        $info = (new Util)->getImageSize($url);
        return is_array($info);
    }

    /**
     * 将文件移动到回收站
     *
     * 也可以用作普通的文件移动方法
     *
     * @param string $aimUrl 文件所在目录
     * @param string $dest   回收站目录（没有则直接删除）
     *
     * @return array|bool
     * @throws Exception
     */
    public function unlinkFile(string $aimUrl, string $dest = '')
    {
        if (empty($aimUrl)) return false;
        $aimUrl = Safe::gpcString($aimUrl);

        $file_extension = pathinfo($aimUrl, PATHINFO_EXTENSION);
        if (in_array($file_extension, ['php', 'html', 'js', 'css', 'ttf', 'otf', 'eot', 'svg', 'woff', 'woff2'])) {
            return Util::error(ErrCode::NO_PERMISSION, '不允许删除该类文件');
        }

        FileHelper::createDirectory(dirname($dest));
        if (is_uploaded_file($aimUrl)) {
            move_uploaded_file($aimUrl, $dest);
        } else {
            rename($aimUrl, $dest);
        }
        @chmod($aimUrl, Yii::$app->params['setting']['fileMode'] ?: 755);

        return is_file($dest);
    }
}
