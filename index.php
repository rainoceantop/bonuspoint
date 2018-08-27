<?php
set_time_limit(5);
require dirname(__FILE__).'/api/bonuspoint.php';

$bp = new BonusPoint();
// 获取请求函数
$func = $_GET['m'];
//判断函数是否存在
if(method_exists($bp, $func)){
    // 获取请求方式GET或POST
    $method = $_SERVER['REQUEST_METHOD'];
    if($method == 'GET'){
        switch($func){
            case 'cancelOrder':
                $uid = $_GET['uid'];
                $oid = $_GET['oid'];
                echo $bp->cancelOrder($uid, $oid);
                break;
            case 'getTypes':
                echo $bp->getTypes();
                break;
            case 'canBuyCheck':
                $uid = $_GET['uid'];
                $points = $_GET['points'];
                echo $bp->canBuyCheck($uid, $points);
                break;
            case 'getOrdersByType':
                $uid = $_GET['uid'];
                $tid = $_GET['tid'];
                echo $bp->getOrdersByType($uid, $tid);
                break;
            case 'getOrdersByTime':
                $uid = $_GET['uid'];
                $day = $_GET['day'];
                echo $bp->getOrdersByTime($uid, $day);
                break;
            case 'getUserReport':
                $uid = $_GET['uid'];
                echo $bp->getUserReport($uid);
                break;
            default:
                echo '<script>alert("未找到该POST请求的方法")</script>';
                break;
        }
    } else if ($method == 'POST'){
        if($func == 'newOrder'){
            $data = file_get_contents('php://input');
            if(!$data){
                $data = array();
                foreach($_REQUEST as $key => $value){
                    $data[$key] = $value;
                }
            }
            print_r($data);
            $uid = $data['uid'];
            $tid = $data['tid'];
            $name = $data['name'];
            $type = $data['type'];
            $price = $data['price'];
            $points = $data['points'];
            $mode = $data['mode'];
            $rtdays = $data['rtdays'];
            echo $bp->newOrder($uid, $tid, $name, $type, $price, $points, $mode, $rtdays);
        } else {
            // 405错误：method not allowed
            http_response_code(405);
        }
    } else {
        http_response_code(405);
    }
} else {
    http_response_code(405);
}