<?php

namespace Jcbowen\JcbaseYii2\components;

class Image
{
    private $src;
    private $actions = [];
    private $resize_width = 0;
    private $resize_height = 0;

    private $image = null;
    private $imageInfo = [];
    private $crop_width = 0;
    private $crop_height = 0;
    private $crop_position = 1;

    private $ext = '';

    public function __construct($src)
    {
        $this->src = $src;
        $this->ext = pathinfo($src, PATHINFO_EXTENSION);
    }

    public static function create($src): Image
    {
        return new self($src);
    }

    public function resize($width = 0, $height = 0): Image
    {
        if ($width > 0 || $height > 0) {
            $this->actions[] = 'resize';
        }
        if ($width > 0 && 0 == $height) {
            $height = $width;
        }
        if ($height > 0 && 0 == $width) {
            $width = $height;
        }
        $this->resize_width = $width;
        $this->resize_height = $height;

        return $this;
    }

    public function crop($width = 400, $height = 300, $position = 1): Image
    {
        if ($width > 0 || $height > 0) {
            $this->actions[] = 'crop';
        }
        if ($width > 0 && 0 == $height) {
            $height = $width;
        }
        if ($height > 0 && 0 == $width) {
            $width = $height;
        }
        $this->crop_width = $width;
        $this->crop_height = $height;
        $this->crop_position = min(intval($position), 9);

        return $this;
    }

    public function getExt()
    {
        return in_array($this->ext, ['jpg', 'jpeg', 'png', 'gif']) ? $this->ext : 'jpeg';
    }

    public function isPng(): bool
    {
        return (new File())->file_is_image($this->src) && $this->getExt() == 'png';
    }

    public function isJPEG(): bool
    {
        return (new File)->file_is_image($this->src) && in_array($this->getExt(), ['jpg', 'jpeg']);
    }

    public function isGif(): bool
    {
        return (new File)->file_is_image($this->src) && $this->getExt() == 'gif';
    }


    public function saveTo($path, $quality = null)
    {
        $path = Safe::gpcPath($path);
        if (empty($path)) {
            return false;
        }
        $result = $this->handle();
        if (!$result) {
            return false;
        }
        $ext = $this->getExt();
        if ('jpg' == $ext) {
            $ext = 'jpeg';
        }
        $func = 'image' . $ext;
        $real_quality = $this->realQuality($quality);
        $saved = false;
        $image = $this->image();
        imagealphablending($image, false);
        imagesavealpha($image, true);
        if (is_null($real_quality)) {
            $saved = $func($image, $path);
        } else {
            if (!$this->isGif()) {
                $saved = $func($image, $path, $real_quality);
            }
        }
        $this->destroy();

        return $saved ? $path : $saved;
    }

    private function realQuality($quality = null): ?float
    {
        if (is_null($quality)) {
            return null;
        }
        $quality = min($quality, 100);
        if ($this->isJPEG()) {
            return $quality * 0.75;
        }
        if ($this->isPng()) {
            return round(abs((100 - $quality) / 11.111111));
        }

        return null;
    }

    protected function handle(): bool
    {
        if (!function_exists('gd_info')) {
            return false;
        }
        $this->image = $this->createResource();
        if (!$this->image) {
            return false;
        }
        $this->imageInfo = getimagesize($this->src);
        $actions         = array_unique($this->actions);
        $src_image = $this->image;
        foreach ($actions as $action) {
            $method = 'do' . ucfirst($action);
            $src_image = $this->{$method}($src_image);
        }
        $this->image = $src_image;

        return true;
    }


    protected function doCrop($src_image)
    {
        list($dst_x, $dst_y) = $this->getCropDestPoint();
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            $new_image = imagecrop($src_image, [
                'x'      => $dst_x,
                'y'      => $dst_y,
                'width'  => $this->crop_width,
                'height' => $this->crop_height
            ]);
            imagedestroy($src_image);
        } else {
            $new_image = $this->modify($src_image, $this->crop_width,
                $this->crop_height, $this->crop_width, $this->crop_height, 0, 0, $dst_x, $dst_y);
        }
        $this->imageInfo[0] = $this->crop_width;
        $this->imageInfo[1] = $this->crop_height;

        return $new_image;
    }


    protected function doResize($src_image)
    {
        $newimage           = $this->modify($src_image, $this->resize_width, $this->resize_height,
            $this->imageInfo[0], $this->imageInfo[1]);
        $this->imageInfo[0] = $this->resize_width;
        $this->imageInfo[1] = $this->resize_height;

        return $newimage;
    }


    protected function modify($src_image, $width, $height, $src_width,
                              $src_height, $dst_x = 0, $dst_y = 0, $src_x = 0, $src_y = 0)
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $width, $height, $src_width, $src_height);
        imagedestroy($src_image);

        return $image;
    }

    private function image()
    {
        return $this->image;
    }

    private function destroy()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

    private function createResource()
    {
        if (file_exists($this->src) && !is_readable($this->src)) {
            return null;
        }
        if ($this->isPng()) {
            return imagecreatefrompng($this->src);
        }
        if ($this->isJPEG()) {
            return imagecreatefromjpeg($this->src);
        }
        if ($this->isGif()) {
            return imagecreatefromgif($this->src);
        }

        return null;
    }


    public function toBase64($prefix = 'data:image/%s;base64,')
    {
        $filename = tempnam('tmp', 'base64');
        $prefix = sprintf($prefix, $this->getExt());
        $result = $this->saveTo($filename);
        if (!$result) {
            return false;
        }
        $content = file_get_contents($filename);
        $base64 = base64_encode($content);
        unlink($filename);

        return $prefix . $base64;
    }


    private function getCropDestPoint(): array
    {
        $s_width = $this->imageInfo[0];
        $s_height = $this->imageInfo[1];
        $dst_x = $dst_y = 0;
        if ('0' == $this->crop_width || $this->crop_width > $s_width) {
            $this->crop_width = $s_width;
        }
        if ('0' == $this->crop_height || $this->crop_height > $s_height) {
            $this->crop_height = $s_height;
        }
        switch ($this->crop_position) {
            case 0:
            case 1:
                $dst_x = 0;
                $dst_y = 0;
                break;
            case 2:
                $dst_x = ($s_width - $this->crop_width) / 2;
                $dst_y = 0;
                break;
            case 3:
                $dst_x = $s_width - $this->crop_width;
                $dst_y = 0;
                break;
            case 4:
                $dst_x = 0;
                $dst_y = ($s_height - $this->crop_height) / 2;
                break;
            case 5:
                $dst_x = ($s_width - $this->crop_width) / 2;
                $dst_y = ($s_height - $this->crop_height) / 2;
                break;
            case 6:
                $dst_x = $s_width - $this->crop_width;
                $dst_y = ($s_height - $this->crop_height) / 2;
                break;
            case 7:
                $dst_x = 0;
                $dst_y = $s_height - $this->crop_height;
                break;
            case 8:
                $dst_x = ($s_width - $this->crop_width) / 2;
                $dst_y = $s_height - $this->crop_height;
                break;
            case 9:
                $dst_x = $s_width - $this->crop_width;
                $dst_y = $s_height - $this->crop_height;
                break;
            default:
                $dst_x = 0;
                $dst_y = 0;
        }
        if ($this->crop_width == $s_width) {
            $dst_x = 0;
        }
        if ($this->crop_height == $s_height) {
            $dst_y = 0;
        }

        return [
            intval($dst_x),
            intval($dst_y)
        ];
    }
}
