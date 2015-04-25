<?php

class Database
{
    private static $Database;

    public static function getInstance()
    {
        if (!self::$Database)
        {
            self::$Database = new PDO('mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_DB . ';charset=utf8', MYSQL_USER, MYSQL_PASS);
        }

        return self::$Database;
    }
}