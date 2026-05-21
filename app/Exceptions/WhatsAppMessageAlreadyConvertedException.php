<?php

namespace App\Exceptions;

use App\Models\Task;
use RuntimeException;

class WhatsAppMessageAlreadyConvertedException extends RuntimeException
{
    public function __construct(
        public readonly Task $task,
    ) {
        parent::__construct('This WhatsApp message is already converted to a task.');
    }
}
