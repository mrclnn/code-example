<?php

namespace App\lib\AmoHelper;

use App\lib\FORMAT;
use Monolog\Handler\PsrHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

trait Logger
{
    use LoggerAwareTrait;
    private function setLogger(string $path = null) : void
    {
        $path = $path ?? FORMAT::getPath(__CLASS__);
        $logger_name = basename($path);
        $storage_sub_dir = basename(str_replace($logger_name, '', $path));

        $logsStorage = env('LOGS_STORAGE') ? env('LOGS_STORAGE').'/'.$storage_sub_dir : storage_path("logs/$storage_sub_dir");
        @mkdir($logsStorage, 0755, true);

        if(is_writable($logsStorage)){
            $this->logger = new \Monolog\Logger(
                $logger_name,
                [
                    new PsrHandler(app()->make('log'), \Monolog\Logger::WARNING),
                    new RotatingFileHandler("$logsStorage/$logger_name.log", 14, \Monolog\Logger::DEBUG, true, 0664),
                ],
                [
                    new PsrLogMessageProcessor(),
                ]
            );
        } else {
            $this->logger = new NullLogger();
        }
    }
}
