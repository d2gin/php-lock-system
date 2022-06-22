<?php

namespace icy8\PHPLock;

use icy8\PHPLock\System\File;
use icy8\PHPLock\System\Driver;
use icy8\PHPLock\System\Redis;

class Client
{
    const fire_until_unlock = 1;
    const fire_until_lock   = 2;
    protected string          $pid;
    protected                 $event;                 // 业务体事件
    protected Driver          $lockSystem;            // 编程锁实例
    protected array           $lock;                  // 锁信息
    protected int             $lockTimeout = 3;       // 秒 循环体超时时间 防死锁
    protected int             $opportunity = self::fire_until_lock;

    public function __construct($key = '')
    {
        if ($key) $this->bindKey($key);
        $this->pid = uniqid();
    }

    /**
     * 注册一个用于区分业务的key
     * @param $key
     * @return $this
     */
    public function bindKey($key)
    {
        $this->lock = [$key, $this->lockTimeout];
        return $this;
    }

    /**
     * 绑定业务闭包
     * @param \Closure|array|string $closure
     * @return $this
     */
    public function bindEvent($closure)
    {
        $this->event = $closure;
        return $this;
    }

    /**
     * 锁超时时间
     * @param int $timeout
     * @return $this
     */
    public function lockTimeout(int $timeout = 3)
    {
        $this->lockTimeout = $timeout;
        return $this;
    }

    /**
     * 启动redis锁
     * @param array $config
     * @return $this
     * @throws \Exception
     */
    public function redis($config = [])
    {
        if (!$this->lock) {
            throw new \Exception("请设置锁名称");
        }
        $this->bindLockSystem("redis", $config);
        $this->lockSystem->bindKey($this->lock[0], $this->lock[1]);
        return $this;
    }

    /**
     * 启动文件锁
     * @param array $config
     * @return $this
     */
    public function file($config = [])
    {
        $this->bindLockSystem('file', array_merge([
            'key'    => $this->lock[0],
            'expire' => $this->lock[1],
        ], $config));
        return $this;
    }

    /**
     * 绑定锁实例
     * @param $resolve
     * @param array $config
     * @return $this
     * @throws \Exception
     */
    public function bindLockSystem($resolve, $config = [])
    {
        if ($resolve == 'redis') {
            $config           = array_merge(['host' => '127.0.0.1'], $config);
            $this->lockSystem = new Redis($config);
        } else if ($resolve == 'file') {
            $this->lockSystem = new File($config);
        } else if ($resolve instanceof Driver) {
            $this->lockSystem = $resolve;
        }
        return $this;
    }

    public function getPid()
    {
        return $this->pid;
    }

    /**
     * 释放锁
     * @return int
     */
    public function unlock() {
        return $this->lockSystem->unlock();
    }

    /**
     * 触发业务
     * @return mixed
     * @throws \Exception
     */
    protected function fireEvent()
    {
        if (!$this->isAvailablceEvent()) {
            throw new \Exception("请设置业务体");
        }
        return call_user_func_array($this->event, func_get_args());
    }

    /**
     * 等待至释放锁时
     * @throws \Exception
     */
    public function whenUntilUnlock()
    {
        $this->opportunity = self::fire_until_lock;
    }

    /**
     * 等待至上锁时
     */
    public function whenUntilLock()
    {
        $this->opportunity = self::fire_until_lock;
    }

    /**
     * 直至释放锁时触发
     * @return bool|mixed
     */
    protected function fireUntilUnlock(...$args)
    {
        // @todo 直至释放锁时触发，文件锁会出问题，先撤回。
        // return $this->fireEvent(...$args);
        throw new \Exception("暂不支持");
    }

    /**
     * 直至上锁时触发
     * @return bool|mixed
     */
    protected function fireUntilLock(...$args)
    {
        $res = null;
        try {
            $this->safeLoopFire(function () use (&$res, $args) {
                // 已释放锁，开始触发事件
                if ($this->lockSystem->lock()) {
                    $res = $this->fireEvent(...$args);
                    return true;
                }
                return false;
            });
            $this->lockSystem->unlock();
        } catch (\Exception | \Throwable $e) {
            // 意外终止 释放锁
            $this->lockSystem->unlock();
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
        return $res;
    }


    /**
     * 循环体&防死锁
     * @param \Closure $closure
     * @param int $timeout
     * @return bool
     */
    protected function safeLoopFire(\Closure $closure)
    {
        $st = microtime(true);
        while (1) {
            if (($st - microtime(true)) > $this->lockTimeout) {
                // 解除死锁
                $this->lockSystem->unlock();
            }
            if ($closure() === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否为有效的事件
     * @return bool
     */
    protected function isAvailablceEvent()
    {
        if (!$this->event instanceof \Closure && !is_array($this->event) && !is_string($this->event)) {
            return false;
        } else if (is_array($this->event) && (!is_object($this->event[0]) || !method_exists($this->event[0], $this->event[1]))) {
            return false;
        } else if (is_string($this->event) && !function_exists($this->event)) {
            return false;
        }
        return true;
    }

    /**
     * 运行锁
     * 不要在事务里面加锁，没有效果
     * 不要在运行锁时使用die、exit等函数
     * @return bool|mixed
     */
    public function run($event = null)
    {
        if (!$this->isAvailablceEvent() && $event) {
            $this->bindEvent($event);
            $args = array_slice(func_get_args(), 1);
        } else $args = func_get_args();
        if (!$this->lockSystem) $this->redis();
        if (!$this->isAvailablceEvent()) {
            throw new \Exception("请设置业务体");
        } else if (!$this->lockSystem) {
            throw new \Exception("程序锁实例未注册");
        } else if (str_replace($this->lockSystem->prefix, '', $this->lockSystem->key) === '') {
            throw new \Exception("请设置锁名称");
        } else if ($this->opportunity == self::fire_until_unlock) {
            // 直到解锁时
            return $this->fireUntilUnlock(...$args);
        } else if ($this->opportunity == self::fire_until_lock) {
            // 直到上锁成功时
            return $this->fireUntilLock(...$args);
        }
        // 没有指定运行时机 马上运行
        return $this->fireEvent(...$args);
    }

    /**
     * 到处运行
     * @param $key
     * @param $event
     */
    static public function newRun($key, $event)
    {
        $instance = new static($key);
        return $instance->run($event, $instance, ...array_slice(func_get_args(), 2));
    }
}
