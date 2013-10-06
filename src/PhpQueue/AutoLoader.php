<?
namespace PhpQueue;

use DirectoryIterator;

class AutoLoader
{
    public static $classNames = array();

    /**
     * Register directory for auto loader
     * @param string|array $dirName
     * @param $use_namespace
     * @return bool
     */
    public static function RegisterDirectory(array $dirName, $use_namespace = false)
    {
        foreach($dirName as $dir)
        {
            self::RegisterDirectoryRecursive(null, $use_namespace, $dir, $dir);
        }
        return true;
    }

    public static function RegisterNamespaces(array $dirName)
    {
        foreach ($dirName as $namespace_key => $dir) {
            self::RegisterDirectoryRecursive(
                $namespace_key,
                true,
                $dir,
                $dir
            );
        }

        return true;
    }

    private static function RegisterDirectoryRecursive($namespace, $need_namespace, $dir_name, $first_dir)
    {
        $di = new DirectoryIterator($dir_name);
        /**
         * @var DirectoryIterator[] $di
         */
        foreach ($di as $file) {
            if ($file->isDir() && !$file->isLink() && !$file->isDot()) {
                self::RegisterDirectoryRecursive($namespace, $need_namespace, $file->getPathname(), $first_dir);
            } elseif (substr($file->getFilename(), -4) === '.php') {
                if($need_namespace)
                {
                    $className = $file->getPathname();

                    if(!is_null($namespace))
                    {
                        $className = str_replace($first_dir, $namespace, $className);
                    }

                    $className = str_replace("/", "\\", $className);

                } else {
                    $className = $file->getFilename();
                }
                $className = substr($className, 0, -4);

                self::RegisterClass($className, $file->getPathname());
            }
        }
    }

    /**
     * Register class in auto loader
     * @param $className
     * @param $fileName
     * @return bool
     */
    public static function RegisterClass($className, $fileName)
    {
        self::$classNames[$className] = $fileName;
        return true;
    }

    /**
     * AutoLoader
     * @param $className
     * @return bool
     */
    public static function LoadClass($className)
    {
        if (!array_key_exists($className, self::$classNames)) return false;
        require_once(self::$classNames[$className]);
        return true;
    }

    /**
     * Register AutoLoader
     * @return bool
     */
    public static function RegisterAutoLoader()
    {
        spl_autoload_register(array(__NAMESPACE__ . '\AutoLoader', 'loadClass'));
        return true;
    }
}