<?php

namespace App\Exceptions;

use Exception;

class NoQuestionsAvailableException extends Exception
{
    protected $message = 'Hozircha savollar mavjud emas. Administratorga murojaat qiling.';
}
