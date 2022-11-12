<?php
$pdo = new PDO("mysql:dbname=testdb;host=127.0.0.1", "root", "root");
$pdo->beginTransaction();
$date = date("Y-m-d");
$stmt = $pdo->query("select * from `aaa` where `created_at`='{$date}'");
$rand = rand(0, 999);
//sleep(1);
// 模拟延迟 延迟越大越容易出现并发问题
usleep(50000);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    var_dump("已存在");
    var_dump($pdo->exec("update `aaa` set `data`=`data`+{$rand} where `created_at`='{$date}'"));
} else {
    var_dump($pdo->exec("insert into `aaa` (`data`, `created_at`) values ($rand, '{$date}')"));
}
$pdo->exec("insert into `bbb` (`data`, `created_at`) values ({$rand}, '{$date}')");
var_dump("running pid: " . $lxs->getPid());
$pdo->commit();