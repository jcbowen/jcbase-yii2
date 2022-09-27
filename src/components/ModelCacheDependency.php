<?php

namespace Jcbowen\JcbaseYii2\components;

use Exception;
use Yii;
use yii\caching\FileDependency;
use yii\helpers\FileHelper;

/**
 * Class ModelCacheDependency
 * 模型缓存
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:19 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class ModelCacheDependency
{

    private static $_filenames = [];

    /**
     * 创建缓存依赖
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $modelClass
     * @return FileDependency
     * @lasttime: 2022/1/5 3:28 下午
     */
    public static function create($modelClass): FileDependency
    {
        return new FileDependency([
            'fileName' => static::buildDependencyFilename($modelClass),
            'reusable' => true
        ]);
    }

    /**
     * 生成缓存依赖的文件名
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $modelClass
     * @return mixed
     * @lasttime: 2022/1/5 3:28 下午
     */
    public static function buildDependencyFilename($modelClass)
    {
        if (empty(static::$_filenames[$modelClass])) {
            $path     = Yii::$app->runtimePath . '/model-dependency/';
            $filename = $path . basename($modelClass) . '.log';
            try {
                if (!is_file($filename)) {
                    FileHelper::createDirectory($path);
                    file_put_contents($filename, date('Y-m-d H:i:s'));
                }
            } catch (Exception $exception) {
            }
            static::$_filenames[$modelClass] = $filename;
        }
        return static::$_filenames[$modelClass];
    }

    /**
     * 清理缓存依赖(修改依赖文件)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $modelClass
     * @return false|int
     * @lasttime: 2022/1/5 3:28 下午
     */
    public static function clear($modelClass)
    {
        return file_put_contents(static::buildDependencyFilename($modelClass), date('Y-m-d H:i:s'));
    }

    /**
     * 清理全部缓存依赖(删除全部依赖文件)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @lasttime: 2022/1/5 3:28 下午
     */
    public static function clearAll()
    {
        try {
            FileHelper::removeDirectory(Yii::$app->runtimePath . '/model-dependency/');
        } catch (Exception $exception) {
        }
    }
}
