<?php

namespace Jcbowen\JcbaseYii2\components\captcha;

use Imagick;
use yii\base\InvalidConfigException;

/**
 * Class Captcha
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:20 AM
 * @package Jcbowen\JcbaseYii2\components\captcha
 */
class Captcha
{
    /**
     * Initializes the widget.
     */
    public function init()
    {
        static::checkRequirements();
    }

    /**
     * Checks if there is graphic extension available to generate CAPTCHA images.
     * This method will check the existence of ImageMagick and GD extensions.
     * @return string the name of the graphic extension, either "imagick" or "gd".
     * @throws InvalidConfigException if neither ImageMagick nor GD is installed.
     */
    public static function checkRequirements(): string
    {
        if (extension_loaded('imagick')) {
            $imagickFormats = (new Imagick())->queryFormats('PNG');
            if (in_array('PNG', $imagickFormats, true)) {
                return 'imagick';
            }
        }
        if (extension_loaded('gd')) {
            $gdInfo = gd_info();
            if (!empty($gdInfo['FreeType Support'])) {
                return 'gd';
            }
        }
        throw new InvalidConfigException('Either GD PHP extension with FreeType support or ImageMagick PHP extension with PNG support is required.');
    }
}
