<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Jcbowen\JcbaseYii2\components\captcha;

use Exception;
use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use ImagickPixel;
use ImagickPixelException;
use Jcbowen\JcbaseYii2\components\Util;
use Yii;
use yii\base\Action;
use yii\base\InvalidConfigException;
use yii\helpers\Url;
use yii\web\Response;

/**
 * CaptchaAction renders a CAPTCHA image.
 *
 * CaptchaAction is used together with [[Captcha]] and [[\yii\captcha\CaptchaValidator]]
 * to provide the [CAPTCHA](https://en.wikipedia.org/wiki/CAPTCHA) feature.
 *
 * By configuring the properties of CaptchaAction, you may customize the appearance of
 * the generated CAPTCHA images, such as the font color, the background color, etc.
 *
 * Note that CaptchaAction requires either GD2 extension or ImageMagick PHP extension.
 *
 * Using CAPTCHA involves the following steps:
 *
 * 1. Override [[\yii\web\Controller::actions()]] and register an action of class CaptchaAction with ID 'captcha'
 * 2. In the form model, declare an attribute to store user-entered verification code, and declare the attribute
 *    to be validated by the 'captcha' validator.
 * 3. In the controller view, insert a [[Captcha]] widget in the form.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @property-read string $verifyCode The verification code.
 *
 * @since 2.0
 */
class CaptchaAction extends Action
{
    /**
     * The name of the GET parameter indicating whether the CAPTCHA image should be regenerated.
     */
    const REFRESH_GET_VAR = 'refresh';

    /**
     * @var int how many times should the same CAPTCHA be displayed. Defaults to 3.
     * A value less than or equal to 0 means the test is unlimited (available since version 1.1.2).
     */
    public $testLimit = 3;
    /**
     * @var int the width of the generated CAPTCHA image. Defaults to 120.
     */
    public $width = 120;
    /**
     * @var int the height of the generated CAPTCHA image. Defaults to 50.
     */
    public $height = 50;
    /**
     * @var int padding around the text. Defaults to 2.
     */
    public $padding = 2;
    /**
     * @var int the background color. For example, 0x55FF00.
     * Defaults to 0xFFFFFF, meaning white color.
     */
    public $backColor = 0xFFFFFF;
    /**
     * @var int the default font color. For example, 0x55FF00. Defaults to 0x2040A0 (blue color).
     */
    public $foreColor = 0x2040A0;
    /**
     * @var bool whether to use transparent background. Defaults to false.
     */
    public $transparent = false;
    /**
     * @var int the minimum length for randomly generated word. Defaults to 6.
     */
    public $minLength = 6;
    /**
     * @var int the maximum length for randomly generated word. Defaults to 7.
     */
    public $maxLength = 7;
    /**
     * @var int the offset between characters. Defaults to -2. You can adjust this property
     * in order to decrease or increase the readability of the captcha.
     */
    public $offset = -2;
    /**
     * @var string the TrueType font file. This can be either a file path or [path alias](guide:concept-aliases).
     */
    public $fontFile = '@yii/captcha/SpicyRice.ttf';
    /**
     * @var string|null the fixed verification code. When this property is set,
     * [[getVerifyCode()]] will always return the value of this property.
     * This is mainly used in automated tests where we want to be able to reproduce
     * the same verification code each time we run the tests.
     * If not set, it means the verification code will be randomly generated.
     */
    public $fixedVerifyCode;
    /**
     * @var string|null the rendering library to use. Currently supported only 'gd' and 'imagick'.
     * If not set, library will be determined automatically.
     * @since 2.0.7
     */
    public $imageLibrary;


    /**
     * Initializes the action.
     * @throws InvalidConfigException if the font file does not exist.
     */
    public function init()
    {
        $this->fontFile = Yii::getAlias($this->fontFile);
        if (!is_file($this->fontFile)) {
            throw new InvalidConfigException("The font file does not exist: $this->fontFile");
        }
    }

    /**
     * Runs the action.
     * @return array|string
     * @throws ImagickDrawException
     * @throws ImagickException
     * @throws ImagickPixelException
     * @throws InvalidConfigException
     * @lasttime: 2024/4/16 10:55 AM
     */
    public function run()
    {
        if (Yii::$app->request->getQueryParam(self::REFRESH_GET_VAR) !== null) {
            // AJAX request for regenerating code
            $code                       = $this->getVerifyCode(true);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'hash1' => $this->generateValidationHash($code),
                'hash2' => $this->generateValidationHash(strtolower($code)),
                // we add a random 'v' parameter so that FireFox can refresh the image
                // when src attribute of image tag is changed
                'url'   => Url::to([$this->id, 'v' => uniqid('', true)]),
            ];
        }

        $this->setHttpHeaders();
        Yii::$app->response->format = Response::FORMAT_RAW;

        return $this->renderImage($this->getVerifyCode());
    }

    /**
     * Generates a hash code that can be used for client-side validation.
     * @param string $code the CAPTCHA code
     * @return int a hash code generated from the CAPTCHA code
     */
    public function generateValidationHash(string $code): int
    {
        for ($h = 0, $i = strlen($code) - 1; $i >= 0; --$i)
            $h += ord($code[$i]) << $i;

        return $h;
    }

    /**
     * Gets the verification code.
     * @param bool $regenerate whether the verification code should be regenerated.
     * @return string the verification code.
     * @throws Exception
     */
    public function getVerifyCode(bool $regenerate = false): ?string
    {
        if ($this->fixedVerifyCode !== null) {
            return $this->fixedVerifyCode;
        }

        $session = Yii::$app->getSession();
        $session->open();
        $name = $this->getSessionKey();
        if ($session[$name] === null || $regenerate) {
            $session[$name]           = $this->generateVerifyCode();
            $session[$name . 'count'] = 1;
        }

        return $session[$name];
    }

    /**
     * Validates the input to see if it matches the generated code.
     * @param string $input user input
     * @param bool $caseSensitive whether the comparison should be case-sensitive
     * @return bool whether the input is valid
     * @throws Exception
     */
    public function validate(string $input, bool $caseSensitive): bool
    {
        $code    = $this->getVerifyCode();
        $valid   = $caseSensitive ? ($input === $code) : strcasecmp($input, $code) === 0;
        $session = Yii::$app->getSession();
        $session->open();
        $name           = $this->getSessionKey() . 'count';
        $session[$name] += 1;
        if ($valid || $session[$name] > $this->testLimit && $this->testLimit > 0) {
            $this->getVerifyCode(true);
        }

        return $valid;
    }

    /**
     * Generates a new verification code.
     * @return string the generated verification code
     * @throws Exception
     */
    protected function generateVerifyCode(): string
    {
        if ($this->minLength > $this->maxLength) {
            $this->maxLength = $this->minLength;
        }
        if ($this->minLength < 3) {
            $this->minLength = 3;
        }
        if ($this->maxLength > 20) {
            $this->maxLength = 20;
        }

        $length = random_int($this->minLength, $this->maxLength);

        $letters = 'bcdfghjklmnpqrstvwxyz';
        $vowels  = 'aeiou';
        $code    = '';
        for ($i = 0; $i < $length; ++$i) {
            if ($i % 2 && random_int(0, 10) > 2 || !($i % 2) && random_int(0, 10) > 9) {
                $code .= $vowels[random_int(0, 4)];
            } else {
                $code .= $letters[random_int(0, 20)];
            }
        }

        return $code;
    }

    /**
     * Returns the session variable name used to store verification code.
     * @return string the session variable name
     */
    protected function getSessionKey(): string
    {
        return '__captcha/' . $this->getUniqueId();
    }

    /**
     * Renders the CAPTCHA image.
     * @param string $code the verification code
     * @return string image contents
     * @throws ImagickDrawException
     * @throws ImagickException
     * @throws ImagickPixelException
     * @throws InvalidConfigException if imageLibrary is not supported
     */
    protected function renderImage(string $code): string
    {
        $imageLibrary = $this->imageLibrary ?? Captcha::checkRequirements();
        if ($imageLibrary === 'gd') {
            return $this->renderImageByGD($code);
        } elseif ($imageLibrary === 'imagick') {
            return $this->renderImageByImagick($code);
        }

        throw new InvalidConfigException("Defined library '$imageLibrary' is not supported");
    }

    /**
     * Renders the CAPTCHA image based on the code using GD library.
     * @param string $code the verification code
     * @return string image contents in PNG format.
     * @throws Exception
     */
    protected function renderImageByGD(string $code): string
    {
        $image = imagecreatetruecolor($this->width, $this->height);

        $backColor = imagecolorallocate(
            $image,
            (int)($this->backColor % 0x1000000 / 0x10000),
            (int)($this->backColor % 0x10000 / 0x100),
            $this->backColor % 0x100
        );
        imagefilledrectangle($image, 0, 0, $this->width - 1, $this->height - 1, $backColor);
        imagecolordeallocate($image, $backColor);

        if ($this->transparent) {
            imagecolortransparent($image, $backColor);
        }

        // 注释此处的字体颜色，后续采用随机字体色
        /*$foreColor = imagecolorallocate(
            $image,
            (int)($this->foreColor % 0x1000000 / 0x10000),
            (int)($this->foreColor % 0x10000 / 0x100),
            $this->foreColor % 0x100
        );*/

        //线条
        for ($i = 0; $i < 6; $i++) {
            $linecolor = imagecolorallocate($image, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            imageline($image, mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(0, $this->width), mt_rand(0, $this->height), $linecolor);
        }
        //雪花
        for ($i = 0; $i < 100; $i++) {
            $snowColor = imagecolorallocate($image, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
            imagestring($image, mt_rand(1, 5), mt_rand(0, $this->width), mt_rand(0, $this->height), '*', $snowColor);
        }

        $length = strlen($code);
        $box    = imagettfbbox(30, 0, $this->fontFile, $code);
        $w      = $box[4] - $box[0] + $this->offset * ($length - 1);
        $h      = $box[1] - $box[5];
        $scale  = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
        $x      = 10;
        $y      = round($this->height * 27 / 40);

        for ($i = 0; $i < $length; ++$i) {
            $fontSize = (int)(random_int(26, 32) * $scale * 0.8);
            $angle    = random_int(-10, 10);
            $letter   = $code[$i];
            // 随机字体色
            $foreColor = imagecolorallocate($image, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            $box       = imagettftext($image, $fontSize, $angle, $x, $y, $foreColor, $this->fontFile, $letter);
            $x         = $box[2] + $this->offset;
        }

        $foreColor = $foreColor ?? $this->foreColor;
        imagecolordeallocate($image, $foreColor);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        $get = ob_get_clean();
        ob_end_clean();
        return $get;
    }

    /**
     * Renders the CAPTCHA image based on the code using ImageMagick library.
     * @param string $code the verification code
     * @return string image contents in PNG format.
     * @throws ImagickPixelException
     * @throws ImagickDrawException
     * @throws ImagickException
     */
    protected function renderImageByImagick(string $code): string
    {
        $backColor = $this->transparent ? new ImagickPixel('transparent') : new ImagickPixel('#' . str_pad(dechex($this->backColor), 6, 0, STR_PAD_LEFT));
        // 注释，在下方实现随机色
        /*$foreColor = new \ImagickPixel('#' . str_pad(dechex($this->foreColor), 6, 0, STR_PAD_LEFT));*/

        $image = new Imagick();
        $image->newImage($this->width, $this->height, $backColor);

        $draw = new ImagickDraw();
        $draw->setFont($this->fontFile);
        $draw->setFontSize(30);
        $fontMetrics = $image->queryFontMetrics($draw, $code);

        $length = strlen($code);
        $w      = (int)$fontMetrics['textWidth'] - 8 + $this->offset * ($length - 1);
        $h      = (int)$fontMetrics['textHeight'] - 8;
        $scale  = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);

        // 加入，随机字母背景
        for ($i = 0; $i < $length * 2; $i++) {
            $draw->setFont($this->fontFile);
            $draw->setFontSize((int)(mt_rand(26, 32) * $scale * 1));
            $draw->setFillColor($this->randColor(10));
            $image->annotateImage($draw, mt_rand(0, $this->width), mt_rand(0, $this->height), mt_rand(-10, 10), Util::random(1));
        }

        $x = 10;
        $y = round($this->height * 27 / 40);
        for ($i = 0; $i < $length; ++$i) {
            $draw = new ImagickDraw();
            $draw->setFont($this->fontFile);
            $draw->setFontSize((int)(random_int(26, 32) * $scale * 0.8));
            // $draw->setFillColor($foreColor);
            // 随机字体色
            $draw->setFillColor($this->randColor(0, 10));
            $image->annotateImage($draw, $x, $y, random_int(-10, 10), $code[$i]);
            $fontMetrics = $image->queryFontMetrics($draw, $code[$i]);
            $x           += (int)$fontMetrics['textWidth'] + $this->offset;
        }

        ob_clean();

        $image->setImageFormat('png');
        return $image->getImageBlob();
    }

    /**
     * Sets the HTTP headers needed by image response.
     */
    protected function setHttpHeaders()
    {
        Yii::$app->getResponse()->getHeaders()
            ->set('Pragma', 'public')
            ->set('Expires', '0')
            ->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->set('Content-Transfer-Encoding', 'binary')
            ->set('Content-type', 'image/png');
    }

    /**
     * 随机颜色代码(除了白色)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $begin
     * @param int $end
     * @return ImagickPixel
     * @throws ImagickPixelException
     * @lasttime: 2024/4/16 10:54 AM
     */
    private function randColor(int $begin = 0, int $end = 15): ImagickPixel
    {
        $colors = array();
        for ($i = 0; $i < 6; $i++)
            $colors[] = dechex(rand($begin, $end));

        $color = implode('', $colors);
        if ($color === 'ffffff')
            return $this->randColor();

        return new ImagickPixel('#' . $color);
    }
}
