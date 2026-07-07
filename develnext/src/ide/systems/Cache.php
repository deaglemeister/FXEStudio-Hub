<?php
namespace ide\systems;

use ide\ui\LazyImage;
use ide\ui\LazyLoadingImage;
use ide\utils\FileUtils;
use php\gui\UXApplication;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\io\File;
use php\lib\str;
use php\time\Time;

class Cache
{
    protected static $cacheImage = [];
    protected static $cacheImageView = [];

    public static function clear()
    {
        static::$cacheImageView = [];
        static::$cacheImage = [];
    }

    /**
     * @param $path
     * @param array|null $size
     * @return UXImageView
     */
    public static function getResourceImageView($path, array $size = null)
    {
        $key = $path;

        if ($size) {
            $key .= '_' . str::join($size, '_');
        }

        if ($view = static::$cacheImageView[$key]) {
            return $view;
        }

        $image = self::getResourceImage($path);

        if ($image instanceof LazyLoadingImage) {
            $view = new UXImageView();

            if ($size) {
                $view->size = $size;
                $view->preserveRatio = true;
            }

            $loader = $image;

            uiLater(function () use ($view, $loader) {
                try {
                    $loaded = $loader->getImage();

                    if ($loaded instanceof UXImage) {
                        $view->image = $loaded;
                    }
                } catch (\Throwable $e) {
                }
            });

            static::$cacheImageView[$key] = $view;

            return $view;
        }

        $view = new UXImageView();
        $view->image = $image;

        if ($size) {
            $view->size = $size;
            $view->preserveRatio = true;
        }

        static::$cacheImageView[$key] = $view;
        return $view;
    }

    /**
     * @param $path
     * @return LazyImage|UXImage
     */
    public static function getResourceImage($path)
    {
        if (!str::startsWith($path, 'res://')) {
            $path = "res://$path";
        }

        list($image, $time) = static::$cacheImage[$path];

        if ($image) {
            return $image;
        }

        if (!UXApplication::isUiThread()) {
            return new LazyImage($path);
        }

        $image = new UXImage($path);
        static::$cacheImage[$path] = [$image, Time::millis()];

        return $image;
    }

    /**
     * @param string $file
     * @return UXImage|LazyImage|null
     */
    public static function getImage($file)
    {
        $file = new File($file);

        if (!$file->exists()) {
            return null;
        }

        try {
            $hash = FileUtils::hashName($file);

            list($image, $time) = static::$cacheImage[$hash];

            if ($image && $time && $time == $file->lastModified()) {
                return $image;
            }

            if (!UXApplication::isUiThread()) {
                return new LazyImage($file);
            }

            $image = new UXImage($file);
            static::$cacheImage[$hash] = [$image, $file->lastModified()];

            return $image;
        } catch (\Exception $e) {
            return null;
        }
    }
}