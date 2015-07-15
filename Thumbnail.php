<?php

namespace aquy\thumbnail;

use Yii;
use yii\helpers\Html;
use yii\helpers\FileHelper;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\ManipulatorInterface;
use yii\base\Object;

class Thumbnail extends Object
{
    const THUMBNAIL_OUTBOUND = ManipulatorInterface::THUMBNAIL_OUTBOUND;
    const THUMBNAIL_INSET = ManipulatorInterface::THUMBNAIL_INSET;

    public static $cacheAlias = 'assets/thumbnails';

    public static $cacheExpire = 0;

    public static function thumbnail($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
    {
        return Image::getImagine()->open(self::thumbnailFile($filename, $width, $height, $mode));
    }

    public static function thumbnailFile($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        if (!is_file($filename)) {
            throw new FileNotFoundException("File $filename doesn't exist");
        }
        $cachePath = Yii::getAlias('@webroot/' . self::$cacheAlias);

        $thumbnailFileExt = strrchr($filename, '.');
        $thumbnailFileName = md5($filename . $width . $height . $mode . filemtime($filename));
        $thumbnailFilePath = $cachePath . DIRECTORY_SEPARATOR . substr($thumbnailFileName, 0, 2);
        $thumbnailFile = $thumbnailFilePath . DIRECTORY_SEPARATOR . $thumbnailFileName . $thumbnailFileExt;

        if (file_exists($thumbnailFile)) {
            if (self::$cacheExpire !== 0 && (time() - filemtime($thumbnailFile)) > self::$cacheExpire) {
                unlink($thumbnailFile);
            } else {
                return $thumbnailFile;
            }
        }
        if (!is_dir($thumbnailFilePath)) {
            mkdir($thumbnailFilePath, 0755, true);
        }

        $box = new Box($width, $height);
        $image = Image::getImagine()->open($filename);
        $image = $image->thumbnail($box, $mode);

        $image->save($thumbnailFile);
        return $thumbnailFile;
    }

    public static function thumbnailFileUrl($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND)
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        $cacheUrl = Yii::getAlias('@web/' . self::$cacheAlias);
        $thumbnailFilePath = self::thumbnailFile($filename, $width, $height, $mode);

        preg_match('#[^\\' . DIRECTORY_SEPARATOR . ']+$#', $thumbnailFilePath, $matches);
        $fileName = $matches[0];

        return $cacheUrl . '/' . substr($fileName, 0, 2) . '/' . $fileName;
    }

    public static function thumbnailImg($filename, $width, $height, $mode = self::THUMBNAIL_OUTBOUND, $options = [])
    {
        $filename = FileHelper::normalizePath(Yii::getAlias($filename));
        try {
            $thumbnailFileUrl = self::thumbnailFileUrl($filename, $width, $height, $mode);
        } catch (FileNotFoundException $e) {
            return 'File doesn\'t exist';
        } catch (\Exception $e) {
            Yii::warning("{$e->getCode()}\n{$e->getMessage()}\n{$e->getFile()}");
            return 'Error ' . $e->getCode();
        }

        return Html::img(
            $thumbnailFileUrl,
            $options
        );
    }

    public static function clearCache()
    {
        $cacheDir = Yii::getAlias('@webroot/' . self::$cacheAlias);
        self::removeDir($cacheDir);
        return @mkdir($cacheDir, 0755, true);
    }

    protected static function removeDir($path)
    {
        if (is_file($path)) {
            @unlink($path);
        } else {
            array_map('self::removeDir', glob($path . DIRECTORY_SEPARATOR . '*'));
            @rmdir($path);
        }
    }
}