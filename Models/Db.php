<?php

namespace Mikroatlas\Models;

use PDO;

class Db
{
    private const DB_HOST = '127.0.0.1';
    private const DB_USER = 'mikroatlasapp';
    private const DB_PASS = 'password';
    private const DB_NAME = 'mikroatlas';
    private const DB_SETTINGS = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_EMULATE_PREPARES => false
    );

    /**
     * @var PDO Singleton design pattern
     */
    private static PDO $connection;
    
    /**
     * Connects to the database
     * @return PDO Database connection
     */
    public static function connect(): PDO
    {
        if (!isset(self::$connection)) {
            self::$connection = new PDO('mysql:host='.self::DB_HOST.';dbname='.self::DB_NAME, self::DB_USER, self::DB_PASS, self::DB_SETTINGS);
        }
        return self::$connection;
    }
}
