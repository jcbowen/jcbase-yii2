<?php

namespace Jcbowen\JcbaseYii2\models;

use Jcbowen\JcbaseYii2\components\ActiveRecord;

/**
 * This is the model class for table "{{%attachment}}".
 *
 * @property int $id
 * @property int|null $group_id 分组ID
 * @property int $uid 上传用户
 * @property int $type 附件类型
 * @property int $size 附件尺寸
 * @property int $width 图片宽度(像素)
 * @property int $height 图片高度(像素)
 * @property string|null $md5 文件md5
 * @property string|null $filename 附件名
 * @property string|null $attachment 附件相对路径
 * @property int $is_display 是否在选择器中显示
 * @property string $deleted_at 删除时间
 * @property string $updated_at 更新时间
 * @property string $created_at 上传时间
 */
class Attachment extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%attachment}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['group_id', 'uid', 'type', 'size', 'width', 'height', 'is_display'], 'integer'],
            [['deleted_at', 'updated_at', 'created_at'], 'safe'],
            [['md5'], 'string', 'max' => 32],
            [['filename', 'attachment'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'group_id'   => '分组ID',
            'uid'        => '上传用户',
            'type'       => '附件类型',
            'size'       => '附件尺寸',
            'width'      => '图片宽度(像素)',
            'height'     => '图片高度(像素)',
            'md5'        => '文件md5',
            'filename'   => '附件名',
            'attachment' => '附件相对路径',
            'is_display' => '是否在选择器中显示',
            'deleted_at' => '删除时间',
            'updated_at' => '更新时间',
            'created_at' => '上传时间',
        ];
    }
}
