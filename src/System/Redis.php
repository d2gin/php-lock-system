<?php

namespace icy8\PHPLock\System;
class Redis extends Driver
{
    public function __construct($config = [])
    {
        $config = $this->setConfig($config)->config;
        if (!$config['host']) {
            throw new \Exception("host not found");
        }
        $this->resolve = new \Redis();
        if (!$this->resolve->connect($config['host'], $config['port'] ?? 6379, $config['timeout'] ?? 0.0)) {
            throw new \Exception("Redis connect fail");
        }
        if (@$config['password']) {
            $this->resolve->auth($config['password']);
        }
    }

    /**
     * 锁是否存在
     * @return bool|int
     */
    public function isLock()
    {
        return $this->resolve->get($this->key) !== false;
    }

    /**
     * 是否解锁/释放锁
     * @return bool
     */
    public function isUnlock()
    {
        return !$this->isLock();
    }

    /**
     * 上锁
     * @return bool
     */
    public function lock()
    {
        return $this->resolve->set($this->key, 'running', ['nx', 'ex' => $this->expire + 1]);// @todo 待优化
    }

    /**
     * 释放锁
     * @return int
     */
    public function unlock()
    {
        return $this->resolve->del($this->key);
    }
}
