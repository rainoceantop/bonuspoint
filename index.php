<?php
require dirname(__FILE__).'/api/bonuspoint.php';

$bp = new BonusPoint();
$res = $bp->immediately(1, 1, 'iphoneX', '+', 3240, 324);
echo $res;