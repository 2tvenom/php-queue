<?php
namespace PhpQueue;

use PhpQueue\Drivers\FileDriver;

class PhpQueueFileTest extends \PhpQueueTestDriver
{
    private static $connection = 'file_queue';

    private static function delete($path)
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /**
         * @var \SplFileInfo[] $it
         */
        foreach ($it as $file) {

            if (in_array($file->getBasename(), array('.', '..'))) {
                continue;
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            } elseif ($file->isFile() || $file->isLink()) {
                unlink($file->getPathname());
            }
        }
    }

    public static function prepareTestExecution()
    {
        self::delete(self::$connection);
    }

    /**
     * @return \PhpQueue\Interfaces\IQueueDriver
     */
    public static function getQueueDriver()
    {
        return new FileDriver(self::$connection);
    }

    public static function tearDownAfterClass()
    {
        self::delete(self::$connection);
    }
}
