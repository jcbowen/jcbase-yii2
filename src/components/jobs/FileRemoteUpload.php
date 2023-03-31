<?php

namespace Jcbowen\JcbaseYii2\components\jobs;

use Jcbowen\JcbaseYii2\components\File;
use Jcbowen\JcbaseYii2\components\Util;
use yii\base\ErrorException;
use yii\helpers\FileHelper;

/**
 * Class FileRemoteUpload.
 */
class FileRemoteUpload extends \yii\base\BaseObject implements \yii\queue\RetryableJobInterface
{
    public $fileInstance;

    public $filePath;

    public $fullPath;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // 判断文件操作实例是否正确
        if (empty($this->fileInstance) || !$this->fileInstance instanceof File)
            $this->fileInstance = new File();
    }

    /**
     * {@inheritdoc}
     * @throws ErrorException
     */
    public function execute($queue)
    {
        $remoteResult = $this->fileInstance->file_remote_upload($this->filePath);
        if (Util::isError($remoteResult)) {
            @FileHelper::unlink($this->fullPath);
            throw new ErrorException('队列上传远程附件失败' . json_encode($remoteResult));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTtr()
    {
        return 60;
    }

    /**
     * {@inheritdoc}
     */
    public function canRetry($attempt, $error)
    {
        return $attempt < 3;
    }
}
