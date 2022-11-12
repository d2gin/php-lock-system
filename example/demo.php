<?php
include "../../../../vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$lxs->lockTimeoutException = true;// 超时后抛出异常
$lxs->redis()->run(function () use ($lxs) {
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
