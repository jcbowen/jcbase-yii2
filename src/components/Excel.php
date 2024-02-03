<?php

namespace Jcbowen\JcbaseYii2\components;

use Jcbowen\JcbaseYii2\base\Component;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Yii;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\helpers\FileHelper;

/**
 * Class Excel
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2024/1/30 12:59 PM
 * @package Jcbowen\JcbaseYii2\components
 */
class Excel extends Component
{
    /** @var array 字段名称（第一行） */
    public $headers = [];

    /** @var array 数据内容（从第二行开始，一个元素为一行） */
    public $content = [];

    /** @var string 数组类型值转换方式，候选值有：json、implode、callback */
    public $arrayToFormat = 'json';

    /** @var string 数组类型值转换方式为implode时的分隔符 */
    public $arrayToFormatImplode = ',';

    /** @var callable|null 数组类型值转换方式为callback时的回调函数 */
    public $arrayToFormatCallback = null;

    /** @var mixed 是否补充序号 */
    public $withIndex = false;

    /** @var string 文件存储位置 */
    public $filePath = '@console/runtime/export/';

    /** @var string 文件名称 */
    public $fileName = '';

    /** @var string 完整路径（存储位置+文件名） */
    protected $fullPath = '';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // 转换为布尔值
        $this->withIndex = !empty($this->withIndex);

        // 转换文件路径
        $this->filePath = Yii::getAlias($this->filePath);
    }

    /**
     * 初始化导出
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @lasttime: 2024/1/30 12:40 PM
     */
    protected function initExport()
    {
        if (empty($this->headers) || empty($this->content))
            throw new InvalidArgumentException('表格头部数据以及表格内容不能为空');

        // 补充序号
        if ($this->withIndex)
            array_unshift($this->headers, '序号');

        $index = 0;
        // 对内容数据进行转换整理
        foreach ($this->content as &$row) {
            $row = array_values($row);
            // 补充序号
            if ($this->withIndex)
                array_unshift($row, ++$index);
            foreach ($row as &$item) {
                if (is_array($item)) {
                    switch ($this->arrayToFormat) {
                        case 'json':
                            $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                            break;
                        case 'implode':
                            $item = implode($this->arrayToFormatImplode, $item);
                            break;
                        case 'callback':
                            if (!is_callable($this->arrayToFormatCallback))
                                throw new InvalidArgumentException('回调函数不存在');
                            $item = call_user_func($this->arrayToFormatCallback, $item);
                            break;
                        default:
                            throw new InvalidArgumentException('不支持的数组类型值转换方式');
                    }
                }
            }
        }

        // 如果文件名为空，填充时间戳+随机字符作为文件名
        if (empty($this->fileName))
            $this->fileName = 'export' . date('YmdHis') . Util::random(5) . '.xlsx';

        // 整合文件路径
        $this->fullPath = $this->filePath . $this->fileName;
    }

    /**
     * 导出Excel文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return false|string
     * @throws Exception
     * @lasttime: 2024/1/30 2:45 PM
     */
    public function export()
    {
        $this->initExport();

        $spreadsheet = new Spreadsheet();

        // 获取当前活动的sheet
        $sheet = $spreadsheet->getActiveSheet();

        // 遍历第一行的字段名称
        $sheet->fromArray($this->headers);

        // 从第二行开始迭代数据并填充到工作表中
        foreach ($this->content as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                // 设置单元格值
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 2), $value);
            }
        }

        // 如果文件路径不存在，则创建文件夹
        FileHelper::createDirectory($this->filePath);

        // 保存文件
        $writer = new Xls($spreadsheet);
        $writer->save($this->fullPath);

        // 如果文件导出成功，返回文件路径，如果导出失败，返回false
        return file_exists($this->fullPath) ? $this->fullPath : false;
    }
}
