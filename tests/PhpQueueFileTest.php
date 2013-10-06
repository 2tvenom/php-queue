<?php
namespace PhpQueue;

use PhpQueue\Drivers\FileDriver;

class PhpQueueFileTest extends \PhpQueueTestDriver
{
    private static $connection = 'file_queue';

    public static function prepareTestExecution()
    {
        exec('rm -fr ' . self::$connection . '/*');
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
        exec('rm -fr ' . self::$connection . '/*');
    }
}
