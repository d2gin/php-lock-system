<?php

namespace icy8\PHPLock\System;
abstract class  Driver
{
    protected $resolve; // 锁
    public    $key;     // 锁名
    protected $config = [];
    public    $prefix = 'icy8:php_lock_system:'; // key前缀
    protected $expire = 3;                       // 秒 redis key过期时间 防止进程意外终止产生死锁

    public function setConfig($key, $value = null)
    {
        if (is_array($key) && $value === null) {
            $this->config = array_merge($this->config, $key);
        } else {
            $this->config[$key] = $value;
        }
        return $this;
    }

    public function bindKey($key, $expire = 3)
    {
        $this->key    = $this->prefix . $key;
        $this->expire = $expire ?: 3;
        return $this;
    }

    /**
     * 锁是否存在
     * @return bool|int
     */
    abstract public function isLock();

    /**
     * 是否解锁/释放锁
     * @return bool
     */
    abstract public function isUnlock();

    /**
     * 上锁
     * @return bool
     */
    abstract public function lock();

    /**
     * 释放锁
     * @return int
     */
    abstract public function unlock();
}
