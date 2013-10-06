<?php
namespace PhpQueue;

use PhpQueue\Drivers\SqlPdoDriver;

class PhpQueueSqliteTest extends \PhpQueueTestDriver
{
    private static $connection = null;

    public static function prepareTestExecution()
    {
        copy("../sql/queue.sqlite", "queue.sqlite");
        self::$connection = new \PDO($GLOBALS['SQLITE_DB_DSN'], "", "", array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_WARNING
        ));

        self::$connection->query("delete from " . $GLOBALS['DB_TABLE']);
    }

    /**
     * @return \PhpQueue\Interfaces\IQueueDriver
     */
    public static function getQueueDriver() {
        return new SqlPdoDriver(self::$connection);
    }

    public static function tearDownAfterClass()
    {
        unlink("queue.sqlite");
    }
}
