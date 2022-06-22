<?php
include "../vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$lxs->redis()->run(function () use ($lxs) {
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
