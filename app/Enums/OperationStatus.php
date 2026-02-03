<?php

namespace App\Enums;

enum OperationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed], true);
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }
}
