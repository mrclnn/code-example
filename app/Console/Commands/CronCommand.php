<?php

namespace App\Console\Commands;

use App\lib\AmoHelper\Logger;
use App\lib\FORMAT;
use App\Models\CronsStatus;
use Illuminate\Console\Command;
use Throwable;

/**
 * Это обёртка для крон артизан команд чтобы каждой не указывать логгер и прочее
 * Этот класс должны наследовать те артизан команды которые используются в кернел в периодическом запуске
 * Если консольная команда наследует этот класс, ее состояние можно отслеживать в crons_status
 *
 * Если мы наследуемся от этого класса мы обязаны убрать handle метод из дочернего класса, а так же реализовать handler
 */
abstract class CronCommand extends Command
{
    use Logger;
    public function handle()
    {
        $this->setLogger(FORMAT::getPath(get_called_class()));
        try{
            if($this->isRun()){
                $this->logger->warning("Trying to execute another command instance, terminate");
                return;
            }
            CronsStatus::start($this->signature);
            $this->logger->debug(__CLASS__.' start handling');
            $this->handler();
            CronsStatus::success($this->signature);
        } catch (Throwable $e){
            CronsStatus::error($this->signature, "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
            $this->logger->error("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
        }
    }

    public abstract function handler();

    protected function isRun(?string $command = null): bool
    {
        $signature = trim(preg_replace('/(\s|\n).+/', '', $command ?? $this->signature));
        ob_start();
        system("ps ax | grep 'artisan $signature'");
        $res = explode("\n", ob_get_contents());
        ob_end_clean();

        $processes = 0;
        foreach ($res as $row) {
            if (str_contains($row, PHP_BINARY." artisan $signature")) $processes++;
            if (str_contains($row, "php artisan $signature")) $processes++;
        }
        return $processes > 1;
    }
}
