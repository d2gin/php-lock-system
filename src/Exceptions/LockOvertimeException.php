<?php

namespace icy8\PHPLock\Exceptions;

use Throwable;

class LockOvertimeException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        if (!$message) {
            $message = '锁等待超时';
        }
        parent::__construct($message, $code, $previous);
    }
}
