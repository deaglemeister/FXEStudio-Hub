<?php
namespace ide\systems;

use ide\ui\LazyImage;
use ide\utils\FileUtils;
use php\gui\UXApplication;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\io\File;
use php\lib\str;
use php\time\Time;

class Cache
{
    protected static $cachedImages = [];
    protected static $cachedImageViews = [];
    
    public static function clear()
    {
        self::$cachedImageViews = [];
        self::$cachedImages = [];
    }
    
    public static function getResourceImageView($path, ?array $size = null): UXImageView
    {
        $key = $path . '_' . str::join($size ?? [], '_');
    
        if (isset(self::$cachedImageViews[$key])) {
            return self::$cachedImageViews[$key];
        }
    
        $image = self::getResourceImage($path);
        $view = new UXImageView();
        $view->image = $image;
    
        if ($size) {
            $view->size = $size;
            $view->preserveRatio = true;
        }
    
        self::$cachedImageViews[$key] = $view;
        return $view;
    }
    
    public static function getResourceImage($path): ?UXImage
    {
        if (!str::startsWith($path, 'res://')) {
            $path = "res://$path";
        }
    
        if (isset(self::$cachedImages[$path])) {
            return self::$cachedImages[$path];
        }
    
        if (!UXApplication::isUiThread()) {
            return new LazyImage($path);
        }
    
        $image = new UXImage($path);
        self::$cachedImages[$path] = $image;
    
        return $image;
    }
    
    public static function getImage(string $filePath): ?UXImage
    {
        $file = new File($filePath);
    
        if (!$file->exists()) {
            return null;
        }
    
        try {
            $hash = FileUtils::hashName($file);
    
            if (isset(self::$cachedImages[$hash]) && isset(self::$cachedImages[$hash][0]) && isset(self::$cachedImages[$hash][1]) && self::$cachedImages[$hash][1] === $file->lastModified()) {
                return self::$cachedImages[$hash][0];
            }
    
            if (!UXApplication::isUiThread()) {
                return new LazyImage($file);
            }
    
            $image = new UXImage($file);
            self::$cachedImages[$hash] = [$image, $file->lastModified()];
    
            return $image;
        } catch (\Exception $e) {
            return null;
        }
    }
}