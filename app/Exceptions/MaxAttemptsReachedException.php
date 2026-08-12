<?php

namespace App\Exceptions;

use Exception;

class MaxAttemptsReachedException extends Exception
{
    protected $message = 'Siz ruxsat etilgan maksimal urinishlar soniga yetdingiz.';
}
