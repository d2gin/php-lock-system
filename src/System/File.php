<?php

namespace icy8\PHPLock\System;
class File extends Driver
{

    public function __construct($config = [])
    {
        $this->prefix = 'icy8/php_lock_system/';
        if (isset($config['key'])) {
            $this->bindKey($config['key'], $config['expire']);
            $this->resolve($this->key);
        }
    }

    public function resolve($key)
    {
        $dir = dirname($key);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $this->resolve = fopen($key, 'w+');
        return $this;
    }

    public function bindKey($key, $expire = 3)
    {
        parent::bindKey($key, $expire);
        return $this->resolve($this->key);
    }

    /**
     * @return bool|int
     */
    public function isLock()
    {
        // @todo 待优化，逻辑实现有点冲突
        /*$this->abortIfNotInit();
        if (flock($this->resolve, LOCK_SH | LOCK_NB) && (time() - filemtime($this->key)) < $this->expire) {
            return true;
        }
        $this->rm();*/
        return false;
    }

    public function isUnlock()
    {
        return !$this->isLock();
    }

    public function lock()
    {
        $this->abortIfNotInit();
        $res = flock($this->resolve, LOCK_EX | LOCK_NB);
        if ($res) {
            fwrite($this->resolve, "locked");
        }
        return $res;
    }

    public function unlock()
    {
        $this->abortIfNotInit();
        flock($this->resolve, LOCK_UN);
        // @todo 待优化
        $this->rm();
        return true;
    }

    protected function rm()
    {
        if (is_file($this->key)) {
            @unlink($this->key);
        }
    }

    protected function abortIfNotInit()
    {
        if (!$this->resolve) {
            throw new \Exception("没有指定锁文件");
        }
    }
}
