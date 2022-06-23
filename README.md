# php-lock-system

#### 介绍
基于php的锁机制

#### 软件架构
1. php>=7.0
2. 基于redis/file锁
3. 默认使用的是redis锁

#### 使用说明

1. 推荐使用redis锁
2. 不推荐复用实例
3. 构造函数传入一个可以阻塞进程的唯一key，视业务而定
4. 文件锁的key是对应的文件名
5. 文件锁运行过程中默认会产生一个名为`icy8/php_lock_system/`的文件夹
6. 如果闭包内有结果返回，那么在run方法可以获取到这个结果。
7. 安装：`composer require icy8/php-lock-system`

#### 已知问题

- 这个项目主要解决并发引起的数据异常问题，不涉及性能方面。

- 如果需要上锁的业务代码运行时间超过设定的锁失效时长，那么并发引起的数据问题依然存在，显然，当前项目在这种场景下并不适用。

- 如果你的业务代码运行超过了3秒甚至更长时间，那么说明你需要优化业务代码提高程序性能，显然，这是另一个层面的问题。

#### 样例

~~目前只支持闭包运行，请勿传非\Closure类型的参数~~

1. 使用默认参数运行
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
// 默认使用的是redis锁
$lxs->run(function () use ($lxs) {
    // 业务逻辑放到闭包运行
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
```

2. redis锁
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$lxs->redis()->run(function () use ($lxs) {
    // 业务逻辑放到闭包运行
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
```

3. redis连接配置
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$lxs->redis([
    'host'=>'127.0.0.1',
    'port'=>'6379',
    'password'=>'123456',
])->run(function () use ($lxs) {
    // 业务逻辑放到闭包运行
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
```

4. 文件锁
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

$lxs = new Client("test_lock_userid");
$lxs->file()->run(function () use ($lxs) {
    // 业务逻辑放到闭包运行
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
});
```

#### 支持的运行方式

1. 通过静态方法调起运行
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;
// 第二个参数支持闭包、数组（对象）、字符串（函数名）。
Client::newRun("test_lock_userid", function ($that, $v1) {
    // 第一个参数 $that 是当前运行的实例
    // 第二个参数起即为自定义传入的参数值
    var_dump($v1);
    var_dump("running pid: " . $that->getPid());
    sleep(2);
}, "这里是自定义参数");
```

2. 对象
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

class Business
{
    public function handle($lxs)
    {
        var_dump("running pid: " . $lxs->getPid());
        sleep(2);
    }
}

$lxs = new Client("test_lock_userid");
$lxs->redis()->run([new Business, "handle"], $lxs);
```

3. 函数名
```php

<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

function Business($lxs, $v1)
{
    var_dump($v1);
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
}

$lxs = new Client("test_lock_userid");
$lxs->redis()->run("Business", $lxs, "aaa");
```

4. 其他案例
```php
<?php
include "vendor/autoload.php";

use \icy8\PHPLock\Client;

function Business($lxs, $v1)
{
    var_dump($v1);
    var_dump("running pid: " . $lxs->getPid());
    sleep(2);
}

$lxs = new Client("test_lock_userid");
// 绑定业务函数
$lxs->bindEvent("Business");
// 或者绑定对象方法
// $lxs->bindEvent([new Business, 'handle']);
// 开始运行。并传入所需的业务参数。
$lxs->redis()->run($lxs, "aaa");
```

#### 错误用法

1. 数据库事务

    错误例子：
    ```php
    <?php
    include "vendor/autoload.php";

    use \icy8\PHPLock\Client;

    $pdo = new \PDO("mysql:dbname=testdb;host=127.0.0.1", "root", "root");
    $pdo->beginTransaction();
    $lxs = new Client("test_lock_userid");
    $lxs->redis()->run(function () use ($lxs) {
        // 这样写虽然不会报错，但是数据并发问题依然存在
        var_dump("running pid: " . $lxs->getPid());
        sleep(2);
    });
    $pdo->commit();
    ```

	错误改正：
	
	```php
	<?php
	include "vendor/autoload.php";
	
	use \icy8\PHPLock\Client;
	
	$pdo = new \PDO("mysql:dbname=testdb;host=127.0.0.1", "root", "root");
	$lxs = new Client("test_lock_userid");
	$lxs->redis()->run(function () use ($lxs, $pdo) {
	    // 事务代码最好是放到锁里面运行
	    $pdo->beginTransaction();
	    var_dump("running pid: " . $lxs->getPid());
	    sleep(2);
	    $pdo->commit();
	});
	```


2. die、exit等终止脚本的函数

    因为释放锁是在闭包运行完成后进行的，所以通过这类函数直接退出程序会导致锁无法正确释放掉。
    这样会出现不必要的锁释放等待，影响程序效率。

    错误例子：

    ```php
    <?php
    include "vendor/autoload.php";

    use \icy8\PHPLock\Client;

    $lxs = new Client("test_lock_userid");
    $lxs->run(function () use ($lxs) {
        // 业务逻辑放到闭包运行
        var_dump("running pid: " . $lxs->getPid());
        // 这是一段业务流程代码
        if(1==1) {
            die();// 直接退出脚本
        }
        sleep(2);
    });
    ```

	错误改正：
    ```php
    <?php
    include "vendor/autoload.php";

    use \icy8\PHPLock\Client;

    $lxs = new Client("test_lock_userid");
    $lxs->run(function () use ($lxs) {
        // 业务逻辑放到闭包运行
        var_dump("running pid: " . $lxs->getPid());
        // 这是一段业务流程代码
        if(1==1) {
            throw new \Exception("终止业务逻辑闭包");// 通过抛出异常来终止闭包
        }
        sleep(2);
    });
    ```
    
    如果业务中必须要用到这类函数，那么建议你在终止脚本前手动释放锁：
    
    ```php
    <?php
    include "vendor/autoload.php";
    
    use \icy8\PHPLock\Client;
    
    $lxs = new Client("test_lock_userid");
    $lxs->run(function () use ($lxs) {
        // 业务逻辑放到闭包运行
        var_dump("running pid: " . $lxs->getPid());
        // 这是一段业务流程代码
        if(1==1) {
            // 手动释放掉当前的锁
            $lxs->unlock();
            die;
        }
        sleep(2);
    });
    ```
    
