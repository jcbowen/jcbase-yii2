<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * Class Pinyin
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2023/11/12 11:14 PM
 * @package Jcbowen\JcbaseYii2\components
 */
class Pinyin
{
    /**
     * 获取拼音
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $inputString 输入的字符串
     * @param string $prefix 前缀
     * @param bool $flag 标志，指定字符编码是否为UTF-8，默认为true
     * @return string|false 拼音字符串
     * @lasttime: 2023/11/12 11:13 PM
     */
    public static function get(string $inputString, string $prefix = '', bool $flag = true)
    {
        $encoding = ($flag) ? 'utf-8' : 'gb2312';

        // 添加前缀
        if (!empty($prefix))
            $inputString = $prefix . $inputString;

        // 拼音数据
        $dataKey    = 'a|ai|an|ang|ao|ba|bai|ban|bang|bao|bei|ben|beng|bi|bian|biao|bie|bin|bing|bo|bu|ca|cai|can|cang|cao|ce|ceng|cha|chai|chan|chang|chao|che|chen|cheng|chi|chong|chou|chu|chuai|chuan|chuang|chui|chun|chuo|ci|cong|cou|cu|cuan|cui|cun|cuo|da|dai|dan|dang|dao|de|deng|di|dian|diao|die|ding|diu|dong|dou|du|duan|dui|dun|duo|e|en|er|fa|fan|fang|fei|fen|feng|fo|fou|fu|ga|gai|gan|gang|gao|ge|gei|gen|geng|gong|gou|gu|gua|guai|guan|guang|gui|gun|guo|ha|hai|han|hang|hao|he|hei|hen|heng|hong|hou|hu|hua|huai|huan|huang|hui|hun|huo|ji|jia|jian|jiang|jiao|jie|jin|jing|jiong|jiu|ju|juan|jue|jun|ka|kai|kan|kang|kao|ke|ken|keng|kong|kou|ku|kua|kuai|kuan|kuang|kui|kun|kuo|la|lai|lan|lang|lao|le|lei|leng|li|lia|lian|liang|liao|lie|lin|ling|liu|long|lou|lu|lv|luan|lue|lun|luo|ma|mai|man|mang|mao|me|mei|men|meng|mi|mian|miao|mie|min|ming|miu|mo|mou|mu|na|nai|nan|nang|nao|ne|nei|nen|neng|ni|nian|niang|niao|nie|nin|ning|niu|nong|nu|nv|nuan|nue|nuo|o|ou|pa|pai|pan|pang|pao|pei|pen|peng|pi|pian|piao|pie|pin|ping|po|pu|qi|qia|qian|qiang|qiao|qie|qin|qing|qiong|qiu|qu|quan|que|qun|ran|rang|rao|re|ren|reng|ri|rong|rou|ru|ruan|rui|run|ruo|sa|sai|san|sang|sao|se|sen|seng|sha|shai|shan|shang|shao|she|shen|sheng|shi|shou|shu|shua|shuai|shuan|shuang|shui|shun|shuo|si|song|sou|su|suan|sui|sun|suo|ta|tai|tan|tang|tao|te|teng|ti|tian|tiao|tie|ting|tong|tou|tu|tuan|tui|tun|tuo|wa|wai|wan|wang|wei|wen|weng|wo|wu|xi|xia|xian|xiang|xiao|xie|xin|xing|xiong|xiu|xu|xuan|xue|xun|ya|yan|yang|yao|ye|yi|yin|ying|yo|yong|you|yu|yuan|yue|yun|za|zai|zan|zang|zao|ze|zei|zen|zeng|zha|zhai|zhan|zhang|zhao|zhe|zhen|zheng|zhi|zhong|zhou|zhu|zhua|zhuai|zhuan|zhuang|zhui|zhun|zhuo|zi|zong|zou|zu|zuan|zui|zun|zuo';
        $dataValue  = '-20319|-20317|-20304|-20295|-20292|-20283|-20265|-20257|-20242|-20230|-20051|-20036|-20032|-20026|-20002|-19990' . '|-19986|-19982|-19976|-19805|-19784|-19775|-19774|-19763|-19756|-19751|-19746|-19741|-19739|-19728|-19725' . '|-19715|-19540|-19531|-19525|-19515|-19500|-19484|-19479|-19467|-19289|-19288|-19281|-19275|-19270|-19263' . '|-19261|-19249|-19243|-19242|-19238|-19235|-19227|-19224|-19218|-19212|-19038|-19023|-19018|-19006|-19003' . '|-18996|-18977|-18961|-18952|-18783|-18774|-18773|-18763|-18756|-18741|-18735|-18731|-18722|-18710|-18697' . '|-18696|-18526|-18518|-18501|-18490|-18478|-18463|-18448|-18447|-18446|-18239|-18237|-18231|-18220|-18211' . '|-18201|-18184|-18183|-18181|-18012|-17997|-17988|-17970|-17964|-17961|-17950|-17947|-17931|-17928|-17922' . '|-17759|-17752|-17733|-17730|-17721|-17703|-17701|-17697|-17692|-17683|-17676|-17496|-17487|-17482|-17468' . '|-17454|-17433|-17427|-17417|-17202|-17185|-16983|-16970|-16942|-16915|-16733|-16708|-16706|-16689|-16664' . '|-16657|-16647|-16474|-16470|-16465|-16459|-16452|-16448|-16433|-16429|-16427|-16423|-16419|-16412|-16407' . '|-16403|-16401|-16393|-16220|-16216|-16212|-16205|-16202|-16187|-16180|-16171|-16169|-16158|-16155|-15959' . '|-15958|-15944|-15933|-15920|-15915|-15903|-15889|-15878|-15707|-15701|-15681|-15667|-15661|-15659|-15652' . '|-15640|-15631|-15625|-15454|-15448|-15436|-15435|-15419|-15416|-15408|-15394|-15385|-15377|-15375|-15369' . '|-15363|-15362|-15183|-15180|-15165|-15158|-15153|-15150|-15149|-15144|-15143|-15141|-15140|-15139|-15128' . '|-15121|-15119|-15117|-15110|-15109|-14941|-14937|-14933|-14930|-14929|-14928|-14926|-14922|-14921|-14914' . '|-14908|-14902|-14894|-14889|-14882|-14873|-14871|-14857|-14678|-14674|-14670|-14668|-14663|-14654|-14645' . '|-14630|-14594|-14429|-14407|-14399|-14384|-14379|-14368|-14355|-14353|-14345|-14170|-14159|-14151|-14149' . '|-14145|-14140|-14137|-14135|-14125|-14123|-14122|-14112|-14109|-14099|-14097|-14094|-14092|-14090|-14087' . '|-14083|-13917|-13914|-13910|-13907|-13906|-13905|-13896|-13894|-13878|-13870|-13859|-13847|-13831|-13658' . '|-13611|-13601|-13406|-13404|-13400|-13398|-13395|-13391|-13387|-13383|-13367|-13359|-13356|-13343|-13340' . '|-13329|-13326|-13318|-13147|-13138|-13120|-13107|-13096|-13095|-13091|-13076|-13068|-13063|-13060|-12888' . '|-12875|-12871|-12860|-12858|-12852|-12849|-12838|-12831|-12829|-12812|-12802|-12607|-12597|-12594|-12585' . '|-12556|-12359|-12346|-12320|-12300|-12120|-12099|-12089|-12074|-12067|-12058|-12039|-11867|-11861|-11847' . '|-11831|-11798|-11781|-11604|-11589|-11536|-11358|-11340|-11339|-11324|-11303|-11097|-11077|-11067|-11055' . '|-11052|-11045|-11041|-11038|-11024|-11020|-11019|-11018|-11014|-10838|-10832|-10815|-10800|-10790|-10780' . '|-10764|-10587|-10544|-10533|-10519|-10331|-10329|-10328|-10322|-10315|-10309|-10307|-10296|-10281|-10274' . '|-10270|-10262|-10260|-10256|-10254';
        $tDataKey   = explode('|', $dataKey);
        $tDataValue = explode('|', $dataValue);
        $data       = '5.0' <= PHP_VERSION ? array_combine($tDataKey, $tDataValue) : static::arrayCombine($tDataKey, $tDataValue);
        arsort($data);
        reset($data);

        // 转换字符编码
        if ($encoding != 'gb2312')
            $inputString = static::convertUtf8ToGb($inputString);

        // 处理特殊字符
        if ($inputString === false)
            $inputString = 'jcbase' . Util::randomKeys(6);

        $result = '';
        $length = strlen($inputString);
        $i      = 0;

        // 遍历输入字符串
        while ($i < $length) {
            $char = ord(substr($inputString, $i, 1));

            // 处理多字节字符
            if ($char > 160) {
                $char2 = ord(substr($inputString, ++$i, 1));
                $char  = $char * 256 + $char2 - 65536;
            }

            // 获取拼音字符
            $result .= static::getPinyinChar($char, $data);
            ++$i;
        }

        // 移除非字母数字字符
        return preg_replace('/[^a-z0-9]*/', '', $result);
    }

    /**
     * 根据数字获取对应的拼音字符
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $num 输入的数字
     * @param array $data 拼音数据
     * @return string 拼音字符
     * @lasttime: 2023/11/12 11:14 PM
     */
    private static function getPinyinChar(int $num, array $data): string
    {
        if (0 < $num && $num < 160)
            return chr($num);

        if ($num < -20319 || -10247 < $num)
            return '';

        foreach ($data as $key => $value)
            if ($value <= $num)
                break;

        return $key ?? '';
    }

    /**
     * 将UTF-8字符转换为GB2312字符
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $char 输入的字符
     * @return string|false 转换后的字符
     * @lasttime: 2023/11/12 11:14 PM
     */
    private static function convertUtf8ToGb($char)
    {
        $string = '';

        if ($char < 128) {
            $string .= $char;
        } elseif ($char < 2048) {
            $string .= chr(192 | $char >> 6);
            $string .= chr(128 | $char & 63);
        } elseif ($char < 65536) {
            $string .= chr(224 | $char >> 12);
            $string .= chr(128 | $char >> 6 & 63);
            $string .= chr(128 | $char & 63);
        } elseif ($char < 2097152) {
            $string .= chr(240 | $char >> 18);
            $string .= chr(128 | $char >> 12 & 63);
            $string .= chr(128 | $char >> 6 & 63);
            $string .= chr(128 | $char & 63);
        }

        return iconv('UTF-8', 'GB2312', $string);
    }

    /**
     * 将两个数组合并为关联数组
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $arr1 第一个数组的键数组
     * @param array $arr2 第二个数组的值数组
     * @return array 合并后的关联数组
     * @lasttime: 2023/11/12 11:15 PM
     */
    private static function arrayCombine(array $arr1, array $arr2): array
    {
        $result = [];
        $count  = count($arr1);

        for ($i = 0; $i < $count; ++$i)
            $result[$arr1[$i]] = $arr2[$i];

        return $result;
    }
}
