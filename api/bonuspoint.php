<?php
require dirname(__FILE__, 2).'/database/database.php';

class BonusPoint{
    private $conn;

    //实例化连接数据库
    function __construct(){
        $pdo = new Database();
        $this->conn = $pdo->connect();
    }

    //订单-立即模式
    function immediately($uid, $tid, $name, $type, $price, $points){
        var_dump($price);
        try{
            $stmt = $this->conn->prepare("call IMMEDIATELY(:uid, :tid, :name, :type, :price, :points)");
            $stmt->bindParam(':uid', $uid);
            $stmt->bindParam(':tid', $tid);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':points', $points);
            $stmt->execute();
            return $stmt->fetch()[0];
        } catch(PDOException $e){
            echo '出错：'.$e->getMessage();
        }
    }

    //订单-两步模式
    function steps(){

    }
}