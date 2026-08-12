<?php

namespace App\Exceptions;

use Exception;

class AttemptExpiredException extends Exception
{
    protected $message = 'Bu urinishning vaqti tugagan.';
}
