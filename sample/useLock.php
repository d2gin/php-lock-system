<?php
include "../vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$pdo = new PDO("mysql:dbname=testdb;host=127.0.0.1", "root", "root");
$lxs->redis()->run(function () use ($lxs, $pdo) {
    $pdo->beginTransaction();
    $date = date("Y-m-d");
    $stmt = $pdo->query("select * from `aaa` where `created_at`='{$date}'");
    $rand = rand(0, 999);
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
});