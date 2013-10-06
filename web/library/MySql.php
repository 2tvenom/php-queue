<?php
class MySql {
    public static function get_connection(){
        return new PDO("mysql:host=" . QUEUE_HOST . ";dbname=" . QUEUE_DATABASE, QUEUE_USER, QUEUE_PASSWORD, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING
        ));
    }
}