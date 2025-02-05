<?php

namespace App\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int|null id
 * @property string environment
 * @property string command
 * @property string|null last_handle
 * @property string|null last_error
 * @property string|null last_success
 * @property int success_count
 * @property int failed_count
 * @property int handle_count
 * @property string|null last_status
 * @property float success_execute_time
 * @property float error_execute_time
 * @property int|null execution_interval
 * @property string|null last_error_message
 */
class CronsStatus extends Model
{
    protected $table = 'crons_status';
    public $timestamps = false;
    private static float $timeStart;

    public static function start(string $signature)
    {
        self::$timeStart = microtime(true);
        $record = self::getBySignature(self::clearSignature($signature));
        //todo 'Europe/Moscow' - это часовой пояс базы данных.
        $executionInterval = now('UTC')->diffInSeconds(Carbon::parse($record->last_handle, 'UTC'));
        $executionInterval = CarbonInterval::seconds($executionInterval)->cascade()->forHumans();
        $record->last_status = 'busy';
        $record->increment('handle_count');
        $record->last_handle = now('UTC');
        $record->execution_interval = $executionInterval;
        $record->save();
    }

    public static function success(string $signature)
    {
        $record = self::getBySignature(self::clearSignature($signature));
        $record->last_status = 'success';
        $record->increment('success_count');
        $record->last_success = now('UTC');
        $record->success_execute_time = empty($record->success_execute_time)
            ? (microtime(true) - self::$timeStart)
            : collect([microtime(true) - self::$timeStart, $record->success_execute_time])->avg();
        $record->save();
    }

    public static function error(string $signature, ?string $error = null)
    {
        $record = self::getBySignature(self::clearSignature($signature));
        $record->last_status = 'error';
        $record->increment('failed_count');
        $record->last_error = now('UTC');
        if($error){
            $record->last_error_message = substr($error, 0, 923);
        }
        $record->error_execute_time = empty($record->error_execute_time)
            ? (microtime(true) - self::$timeStart)
            : collect([microtime(true) - self::$timeStart, $record->error_execute_time])->avg();
        $record->save();
    }

    /**
     * Сигнатуры бывают с доп параметрами, переносами строк и прочим. Мы в свою очередь хотим подпись которую используем для запуска конкретно
     *
     * @param string $signature
     * @return string
     */
    private static function clearSignature(string $signature): string
    {
        return trim(preg_replace('/(\s|\n).+/', '', $signature));
    }
    private static function getBySignature(string $signature): self
    {
        $environment = env('APP_ENVIRONMENT') ?? 'unknown';
        $record = self::query()
            ->where('command', $signature)
            ->where('environment', $environment)->first();
        if($record instanceof self) return $record;
        self::query()->insert(['command' => $signature, 'environment' => $environment]);
        return self::getBySignature($signature);
    }
}
