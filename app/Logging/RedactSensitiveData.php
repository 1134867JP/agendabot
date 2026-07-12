<?php

namespace App\Logging;

use App\Support\DataMasker;
use Monolog\LogRecord;

class RedactSensitiveData
{
    public function __invoke($logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(context: DataMasker::context($record->context));
        });
    }
}
