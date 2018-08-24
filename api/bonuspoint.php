<?php
require dirname(__FILE__, 2).'/database/database.php';

class BonusPoint{
    private $conn;

    //实例化连接数据库
    function __construct(){
        $pdo = new Database();
        $this->conn = $pdo->connect();
    }

    //订单模式
    function orderUpdate($uid, $tid, $name, $type, $price, $points, $mode, $rtdays){
        $resp = '';
        try{
            $stmt = $this->conn->prepare("call ORDER_UPDATE(:uid, :tid, :name, :type, :price, :points, :mode, :rtdays)");
            $stmt->bindParam(':uid', $uid);
            $stmt->bindParam(':tid', $tid);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':points', $points);
            $stmt->bindParam(':mode', $mode);
            $stmt->bindParam(':rtdays', $rtdays);
            $stmt->execute();
            $resp = $stmt->fetch()[0] == 0 ? TRUE : FALSE;
        } catch(PDOException $e){
            $resp = '出错：'.$e->getMessage();
        } finally{
            echo $resp;
        }
    }

    //取消订单
    function cancelOrder($oid){
        try{
            $stmt = $this->conn->prepare("call CANCEL_ORDER(:oid)");
            $stmt->bindParam(':oid', $oid);
            $stmt->execute();
            if($stmt->fetch()[0]){
                echo TRUE;
            } else {
                echo FALSE;
            }
        } catch(PDOException $e){
            echo '出错：'.$e->getMessage();
        }
    }

    //获取类型
    function getTypes(){
        $sql = 'select id,name from types';
        $stmt = $this->conn->query($sql);
        $data = array();
        $resp = array();
        /**
         * 字段参数
         * 0: id
         * 1: name
         */
        while($row = $stmt->fetch()){
            $data['tid'] = $row[0];
            $data['name'] = $row[1];
            array_push($resp, $data);
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    }

    //判断能否购买，可以返回1，不可以没有返回值
    function canBuyCheck($uid, $points){
        $sql = 'select bpoint from accounts where uid = :uid';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        if($stmt->fetch()[0] >= $points)
            echo TRUE;
        else
            echo FALSE;
    }

    //根据类型获取用户订单
    function getOrdersByType($uid, $tid){
        $resp = $this->getOrdersList($uid, 'bytype', $tid);
        echo $resp;
    }

    //根据时间获取用户订单
    function getOrdersByTime($uid){
        $resp = $this->getOrdersList($uid, 'bytime', NULL);
        echo $resp;
    }
    //列表查询
    function getOrdersList($uid, $symbol, $tid){
        switch ($symbol){
            case 'bytype':
                $sql = 'select a.name as user_name, o.name as order_name, o.price, o.points, o.at, t.name as type from orders o left join types t on t.id = o.tid left join accounts a on a.uid = o.uid where t.id = :tid and a.uid = :uid';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':tid', $tid);
                $stmt->bindParam(':uid', $uid);
                break;
            case 'bytime':
                $sql = 'select a.name as user_name, o.name as order_name, o.price, o.points, o.at, t.name as type from orders o left join types t on t.id = o.tid left join accounts a on a.uid = o.uid where a.uid = :uid order by o.at desc';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':uid', $uid);
                break;
        }
        $stmt->execute();
        $data = array();
        $resp = array();
        while($row = $stmt->fetch()){
            $data['user_name'] = $row[0];
            $data['order_name'] = $row[1];
            $data['price'] = $row[2];
            $data['points'] = $row[3];
            $data['time'] = $row[4];
            $data['type'] = $row[5];
            array_push($resp, $data);
        }
        return json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    }
    //用户报表统计
    function getUserReport($uid){
        $data = array();
        //获取用户的id，姓名，积分
        $sql = 'select uid, name, bpoint from accounts where uid = :uid';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['id'] = $row[0];
        $data['名称'] = $row[1];
        $data['积分'] = $row[2];
        //获取用户的立即到帐增加的次数
        $sql = "select count(*) from orders where orders.type = '+' and orders.mode = 0 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['立即到帐增加'] = $row[0];
        //获取用户的立即到帐减少的次数
        $sql = "select count(*) from orders where orders.type = '-' and orders.mode = 0 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['立即到帐减少'] = $row[0];
        //获取用户的两步到帐增加的次数
        $sql = "select count(*) from orders where orders.type = '+' and orders.mode = 1 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['两步到帐增加'] = $row[0];
        //获取用户的两步到帐减少的次数
        $sql = "select count(*) from orders where orders.type = '-' and orders.mode = 1 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['两步到帐减少'] = $row[0];
        //获取用户已结帐的次数
        $sql = "select count(*) from orders where orders.return_days = -1 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['已结帐'] = $row[0];
        //获取用户取消的次数
        $sql = "select count(*) from orders where orders.return_days = -2 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['取消订单'] = $row[0];
        //获取用户正在结帐的次数
        $sql = "select count(*) from orders where orders.return_days > 0 and orders.uid = :uid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        $row = $stmt->fetch();
        $data['正在结帐'] = $row[0];

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}