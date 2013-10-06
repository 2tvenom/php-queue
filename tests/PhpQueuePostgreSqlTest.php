<?php
namespace PhpQueue;

use PhpQueue\Drivers\SqlPdoDriver;

class PhpQueuePostgreSqlTest extends \PhpQueueTestDriver
{
    /**
     * @var \PDO|null
     */
    private static $connection = null;

    public static function prepareTestExecution()
    {
        self::$connection = new \PDO($GLOBALS['PGSQL_DB_DSN'], $GLOBALS['PGSQL_DB_USER'], $GLOBALS['PGSQL_DB_PASSWORD'], array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING
        ));

        self::$connection->query("DELETE FROM " . $GLOBALS['DB_TABLE']);
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
        self::$connection->query("DELETE FROM " . $GLOBALS['DB_TABLE']);
    }
}