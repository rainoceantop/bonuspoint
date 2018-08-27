<?php
require dirname(__FILE__, 2).'/database/database.php';

class BonusPoint{
    private $conn;

    //实例化连接数据库
    function __construct(){
        $pdo = new Database();
        $this->conn = $pdo->connect();
    }

    // 新订单
    function newOrder($uid, $tid, $name, $type, $price, $points, $mode, $rtdays){
        $resp = '';
        try{
            // 执行订单存储过程函数
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
            // 存储过程没有出错情况下返回0，代表了插入成功，所以返回TRUE
            $resp = $stmt->fetch()[0] == 0 ? TRUE : FALSE;
        } catch(PDOException $e){
            $resp = '出错：'.$e->getMessage();
        } finally{
            return $resp;
        }
    }

    //取消订单
    function cancelOrder($uid, $oid){
        try{
            $stmt = $this->conn->prepare("call CANCEL_ORDER(:uid, :oid)");
            $stmt->bindParam(':uid', $uid);
            $stmt->bindParam(':oid', $oid);
            $stmt->execute();
            if($stmt->fetch()[0] == 0){
                return TRUE;
            } else {
                return FALSE;
            }
        } catch(PDOException $e){
            return '出错：'.$e->getMessage();
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
        return json_encode($resp, JSON_UNESCAPED_UNICODE);
    }

    //判断能否购买，可以返回1，不可以没有返回值
    function canBuyCheck($uid, $points){
        $sql = 'select bpoint from accounts where uid = :uid';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        if($stmt->fetch()[0] >= $points)
            return TRUE;
        else
            return FALSE;
    }

    //根据类型获取用户订单
    function getOrdersByType($uid, $tid){
        $resp = $this->getOrdersList($uid, 'bytype', $tid);
        return $resp;
    }

    //根据时间获取用户订单
    function getOrdersByTime($uid, $day){
        $resp = $this->getOrdersList($uid, 'bytime', $day);
        return $resp;
    }
    //列表查询
    function getOrdersList($uid, $symbol, $mark){
        switch ($symbol){
            case 'bytype':
                $sql = 'select a.name as user_name, o.id as oid, o.name as order_name, o.price, o.points, o.mode, o.return_days, o.at, t.name as type from orders o left join types t on t.id = o.tid left join accounts a on a.uid = o.uid where a.uid = :uid order by o.at desc';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':tid', $mark);
                $stmt->bindParam(':uid', $uid);
                break;
            case 'bytime':
                $sql = 'select a.name as user_name, o.id as oid, o.name as order_name, o.price, o.points, o.mode, o.return_days, o.at, t.name as type from orders o left join types t on t.id = o.tid left join accounts a on a.uid = o.uid where a.uid = :uid having(datediff(now(), o.at) <= :day) order by o.at desc';
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':uid', $uid);
                $stmt->bindParam(':day', $mark);
                break;
        }
        $stmt->execute();
        $data = array();
        $resp = array();
        /**
         * 返回的索引代表字段
         * 0: user_name，用户名称
         * 1: oid，订单id
         * 2: order_name，订单名称
         * 3: price，订单金额
         * 4: points，订单积分
         * 5: mode，订单模式，0立即，1两步
         * 6: rtdays, 退货期限 -1代表已结算，-2代表取消订单，0代表即将结算，大于0代表剩余天数
         * 7: time，订单生成时间
         * 8: type，订单种类（types表定义的种类）
         */
        while($row = $stmt->fetch()){
            $data['user_name'] = $row[0];
            $data['oid'] = $row[1];
            $data['order_name'] = $row[2];
            $data['price'] = $row[3];
            $data['points'] = $row[4];
            $data['mode'] = $row[5];
            $data['rtdays'] = $row[6];
            $data['time'] = $row[7];
            $data['type'] = $row[8];
            array_push($resp, $data);
        }
        return json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    //用户报表统计
    function getUserReport($uid){
        $data = array();
        $this->conn->beginTransaction();
        //获取用户的id，姓名，积分
        $sql = 'select uid, name, bpoint from accounts where uid = :uid';
        $row = $this->comExec($sql, $uid);
        /**
         * 字段参数
         * 返回的索引代表字段
         * 0: uid，用户id
         * 1: name，用户名称
         * 2: bpoint，用户积分
         */
        $data['id'] = $row[0];
        $data['名称'] = $row[1];
        $data['积分'] = $row[2];
        //获取用户的立即到帐增加的次数
        $sql = "select count(*) from orders where orders.type = '+' and orders.mode = 0 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['立即到帐增加'] = $row[0];
        //获取用户的立即到帐减少的次数
        $sql = "select count(*) from orders where orders.type = '-' and orders.mode = 0 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['立即到帐减少'] = $row[0];
        //获取用户的两步到帐增加的次数
        $sql = "select count(*) from orders where orders.type = '+' and orders.mode = 1 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['两步到帐增加'] = $row[0];
        //获取用户的两步到帐减少的次数
        $sql = "select count(*) from orders where orders.type = '-' and orders.mode = 1 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['两步到帐减少'] = $row[0];
        //获取用户已结帐的次数
        $sql = "select count(*) from orders where orders.return_days = -1 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['已结帐'] = $row[0];
        //获取用户取消的次数
        $sql = "select count(*) from orders where orders.return_days = -2 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['取消订单'] = $row[0];
        //获取用户正在结帐的次数
        $sql = "select count(*) from orders where orders.return_days > 0 and orders.uid = :uid";
        $row = $this->comExec($sql, $uid);
        $data['正在结帐'] = $row[0];
        $this->conn->commit();

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    // 用户报告统计共用函数
    function comExec($sql, $uid){
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':uid', $uid);
        $stmt->execute();
        return $stmt->fetch();
    }
}