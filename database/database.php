<?php

class Database{
    private $db_conf;
    private $conn;

    function __construct(){
        $this->db_conf = require dirname(__FILE__, 2).'/config/database.conf.php';
    }

    function connect(){
        extract($this->db_conf);
        try{
            $this->conn = new PDO($db.':host='.$host.';port='.$port.';dbname='.$dbname, $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->query('set names utf8');
            return $this->conn;
        } catch(PDOException $e){
            exit('连接错误：'.$e->getMessage());
        }
    }
}