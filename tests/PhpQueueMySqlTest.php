<?php
namespace PhpQueue;

use PhpQueue\Drivers\SqlPdoDriver;

class PhpQueueMySqlTest extends \PhpQueueTestDriver
{
    /**
     * @var null|\PDO
     */
    private static $connection = null;

    public static function prepareTestExecution()
    {
        self::$connection = new \PDO($GLOBALS['MYSQL_DB_DSN'], $GLOBALS['MYSQL_DB_USER'], $GLOBALS['MYSQL_DB_PASSWORD'], array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING
        ));

        self::$connection->query("TRUNCATE " . $GLOBALS['DB_TABLE']);
    }

    /**
     * @return \PhpQueue\Interfaces\IQueueDriver
     */
    public static function getQueueDriver()
    {
        return new SqlPdoDriver(self::$connection);
    }

    public static function tearDownAfterClass()
    {
        self::$connection->query("TRUNCATE " . $GLOBALS['DB_TABLE']);
    }
}
