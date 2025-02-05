<?php

namespace App\lib;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Iterator;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

trait CacheHelper
{
    /**
     * Указывает максимальное количество элементов в конструкции select in ,
     * нужно для массовой загрузки из кэша
     *
     * @var int
     */
    private int $SQL_IN_OPERATOR_LIMIT = 1000;
    private int $defaultCacheExpiresMinutes = 60;
    private Collection $RAMCache;
    private bool $allowToUseRamCache = false;

    protected function allowToUseRamCache(bool $allow)
    {
        $this->allowToUseRamCache = $allow;
    }

    protected function RAMCache(): Collection
    {
        return $this->RAMCache ?? $this->RAMCache = new Collection();
    }

    /**
     * Вернет кол-во очищенных строк
     *
     * @param bool $force Если true, то будет очищен весь кэщ, и даже те записи, время которых еще не истекло. Если false, то будут очищены только те записи, для которых время жизни уже истекло
     * @return int
     */
    protected function clearCache(bool $force = false): int
    {
        if($this->allowToUseRamCache) {
            $cleared = $this->RAMCache()->count();
            $this->RAMCache = new Collection();
            return $cleared;
        }
        $builderQuery = DB::table('cache')
            ->where('key', 'like', "{$this->getLaraCachePrefix()}{$this->getCachePrefix()}%");
        if( ! $force) $builderQuery = $builderQuery->where('expiration', '<', time());
        return $builderQuery->delete();
    }

    /**
     * В качестве value принимает любой тип данных, т.к. лара кэш умеет работать с этим, и сериализация скрыта под капотом
     * т.е. если мы положим в кэш сложный объект, мы такой же сложный объект и извлечём обратно
     *
     * @param string|null $key
     * @param $value
     * @param int|null $minutes время хранения кэша в минутах
     * @return void
     */
    protected function putToCacheOld(?string $key, $value, ?int $minutes = null): void
    {
        if(empty($key)) return;
        $expires = now()->addMinutes($minutes ?? $this->defaultCacheExpiresMinutes);
        //todo expires может быть int, и тогда это или timestamp, или количество секунд (с версии 7+) минут (с версии 5.3/5.4)
        Cache::store('database')->put("{$this->getCachePrefix()}$key", $value, $expires);
    }

    /**
     * В качестве value принимает любой тип данных, т.к. лара кэш умеет работать с этим, и сериализация скрыта под капотом
     *  т.е. если мы положим в кэш сложный объект, мы такой же сложный объект и извлечём обратно
     *  Может принимать множество значений для оптимизации кол-ва запросов к серверу кэша
     *
     * @param $keys
     * @param $values
     * @param int|null $minutes
     * @return void
     * @throws InvalidArgumentException
     */
    protected function putToCache($keys, $values = null, ?int $minutes = null): void
    {
        if(empty($keys)) return;
        if(is_string($keys) || is_numeric($keys)){
            if($this->allowToUseRamCache){
                $this->RAMCache()->put("{$this->getCachePrefix()}$keys", $values);
            } else {
                $expires = now()->addMinutes($minutes ?? $this->defaultCacheExpiresMinutes);
                //todo expires может быть int, и тогда это или timestamp, или количество секунд (с версии 7+) минут (с версии 5.3/5.4)
                Cache::store('database')->put("{$this->getCachePrefix()}$keys", $values, $expires);
            }
        }
        if(is_iterable($keys)){
            if($this->allowToUseRamCache){
                $values = collect($keys)->mapWithKeys(fn($val, $key) => ["{$this->getCachePrefix()}$key" => $val]);
                $this->RAMCache = $this->RAMCache()->merge(collect($values));
            } else {
                $expires = now()->addMinutes(is_int($values) ? $values : $this->defaultCacheExpiresMinutes);
                $values = collect($keys)->mapWithKeys(fn($val, $key) => ["{$this->getCachePrefix()}$key" => $val]);
                Cache::store('database')->setMultiple($values, $expires);
            }
        }
    }

    protected function getFromCacheOld(?string $key)
    {
        if(empty($key)) return null;
        return Cache::store('database')->get("{$this->getCachePrefix()}$key");
    }

    protected function getFromCache($keys)
    {
        if(empty($keys)) return null;
        try{
            if(is_string($keys) || is_numeric($keys)){
                if($this->allowToUseRamCache){
                    return $this->RAMCache()->get("{$this->getCachePrefix()}$keys");
                } else {
                    return Cache::store('database')->get("{$this->getCachePrefix()}$keys");
                }
            }
            if(is_iterable($keys)) {
                if($this->allowToUseRamCache){
                    $keys = collect($keys)->map(fn(string $key) => "{$this->getCachePrefix()}$key");
                    $values = $this->RAMCache()->only($keys);
                    foreach($keys as $key){
                        if( ! $values->has($key)) $values->put($key, null);
                    }
                    return $values;
                } else {
                    //todo проверка на то что это iterable string
                    return collect(Cache::store('database')->getMultiple(collect($keys)
                        ->map(fn(string $key) => "{$this->getCachePrefix()}$key")))
                        ->mapWithKeys(fn($val, $key) => [str_replace($this->getCachePrefix(), '', $key) => $val]);
                }

            }
        } catch (InvalidArgumentException $e) {
            return null;
        }
        return null;
    }

    private function getLaraCachePrefix(): string
    {
        // лара записывает название корня папки _cache как первый обязательный элемент ключа кэша, например:
        // лара лежит в папке /var/www/laravel, значит префикс будет: laravel_cache
        return FORMAT::snakeCase(basename(base_path()).'_cache');
    }
    private function getCachePrefix(): string
    {
        return str_replace('\\', '/', '_'.self::class.'_');
    }

    protected function removeFromCache(string $key): void
    {
        Cache::store('database')->forget("{$this->getCachePrefix()}$key");
    }
}
