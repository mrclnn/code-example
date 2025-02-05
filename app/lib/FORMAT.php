<?php

namespace App\lib;

use DateTime;
use DateTimeZone;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use Throwable;

abstract class FORMAT
{
    const MALE = 'male';
    const FEMALE = 'female';
    /**
     * Принимает $date строку, которая может содержать или timestamp или корректное значение для strtotime
     * Возвращает DateTime или null соответствующие переданному значению
     *
     * @param string|null $date
     * @param string|null $timezone
     * @return DateTime|null
     */
    public static function DateTime(?string $date, ?string $timezone = null): ?DateTime
    {
        if(!$date) return null;
        try{
            //todo не хватает обработки неверного timezone здесь
            if(is_numeric($date)) {
                $timezone = new DateTimeZone($timezone ?? 'Europe/Moscow');
                return new DateTime(date('Y-m-d H:i:s', (int)$date), $timezone);
            }
            if(strtotime($date)) {
                $timezone = new DateTimeZone($timezone ?? 'Europe/Moscow');
                return new DateTime($date, $timezone);
            }
        } catch (Throwable $e){
            return null;
        }
        return null;
    }

    /**
     * Принимает числовой индекс возвращает колонку ексель соответствующую.
     * 0 => 'A', 27 => 'AB' etc
     *
     * @param int $index
     * @return int
     */
    public static function getExcelColumnName(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Принимает строку название колонки ексель
     * возвращает ее индекс
     * 'A' => 0, 'AB' = 27 etc
     *
     * @param string $column
     * @return int
     */
    public static function getExcelColumnIndex(string $column): int
    {
        try{
            return @Coordinate::columnIndexFromString($column) - 1;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function comparePhones(?string $phone1, ?string $phone2): bool
    {
        if(empty($phone1) || empty($phone2)) return false;
        return substr(self::intPhone($phone1), -10) === substr(self::intPhone($phone2), -10);
    }

    public static function date(?string $date, $format, ?string $timezone = null): ?string
    {
        if(!$date) return null;
        return self::DateTime($date, $timezone)->format($format);
    }

    public static function timestamp(?DateTime $dateTime): ?int
    {
        if(!$dateTime) return null;
        return $dateTime->getTimestamp();
    }

    /**
     * Конвертирует секунды в микросекунды. Полезно для удобности чтения usleep
     *
     * @param float $seconds
     * @return void
     */
    public static function microseconds(float $seconds): int
    {
        return (int)($seconds * 1000 * 1000);
    }

    /**
     * Принимает произвольную строку, приводит её к snake_case
     * пробелы, дефисы, camelCase обрабатываются
     * многократное нижнее подчеркивание преобразуется к одинарному
     *
     * пример:
     * "  some_LongName-with   anyStrange __syntax" => "some_long_name_with_any_strange_syntax"
     *
     * @param string|null $input
     * @return string
     */
    public static function snakeCase(?string $input = null): string
    {
        $output = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim($input ?? '')));
        $output = str_replace('-', '_', $output);
        return preg_replace(['/_+/', '/\s+/'], '_', $output);
    }

    /**
     * Форматирует строку как имя
     * " иМя   ФаМИЛИя  " => "Имя Фамилия"
     *
     * @param string|null $name
     * @return string|null
     */
    public static function name(?string $name): ?string
    {
        if(!$name) return null;
        return mb_convert_case(mb_strtolower(trim(preg_replace('/\s/', ' ', $name))), MB_CASE_TITLE);
    }

    /**
     * Возвращает путь разделённый корректными для системы разделителями. т.е. dir\dir\dir => dir/dir/dir
     * или наоборот, в зависимости от системы на которой исполняется функция
     *
     * @param string|null $path
     * @return string|null
     */
    public static function getPath(?string $path): ?string
    {
        if(!$path) return null;
        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    }

    public static function isJson($string) : bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * На самом деле спорно очень, потому что (int) "съест" ведущие нули.
     * Однако учитывая что номер телефона как правило начинается с 7 или 8 проблема не частая,но присутствует
     *
     * @param string|null $phone
     * @return int
     */
    public static function intPhone(?string $phone): int
    {
        return (int)preg_replace('/[^0-9]/', '', $phone ?? '');
    }

    /**
     * Сравнивает имена $first и $second.
     * Например, чтобы "Иван Иванов" и "Иванов Иван Иванович" считались одним именем
     * По сути разбиваем оба переданных значения на массивы содержащие элементы имени (разделитель пробел)
     * После чего находим схождение массивов и индекс схождения: отношение общих элементов к максимальному набору первоначальных
     *
     * например:
     * сравнение "Иванов Иван Иванович" и " Иван иванов" даст индекс 0.66 (т.е. совпадение 2/3 элементов имени),
     * и мы будем считать это одинаковым именем.
     *
     * важно:
     * сравнение "Иванович Иван" и "Иванович Иван Иванов" тоже даст 0.66, и мы будем дуать что это одно имя
     * хотя сочетание Имя + Отчество и гораздо менее уникально чем ФИО
     *
     * @param string $first первое имя для сравнения
     * @param string $second второе имя для сравнения
     * @return bool
     */
    public static function isSameNames(string $first, string $second): bool
    {
        $first = collect(explode(' ', self::name($first)));
        $second = collect(explode(' ', self::name($second)));
        $intersection = $first->intersect($second);
        $intersectionIndex = $intersection->count() / max($first->count(), $second->count());
        return $intersectionIndex >= 0.75;
    }

    public static function toScalarIterable(iterable $iterable): Collection
    {
        //todo array_filter вместо filter() коллекции использовается
        // потому что is_scalar и подобные фукнции принимают только один параметр,
        // а filter() передаёт еще и ключ после значения
        return collect(array_filter(collect($iterable)->all(), 'is_scalar'));
    }
    public static function isScalarIterable(iterable $iterable): bool
    {
        return collect($iterable)->count() === collect(self::toScalarIterable($iterable))->count();
    }

    public static function cfv4($cfId, $value, $enum = null): ?array
    {
        if(!is_int($cfId) && !is_string($cfId))
            throw new InvalidArgumentException('cfId must be integer or string (cf code) types');
        $values = collect($value);
        if($values->isNotEmpty() && ! self::isScalarIterable($values))
            throw new InvalidArgumentException('value must be iterable[scalar] or scalar types, received: '.json_encode([$value]));
        $enums = collect($enum);
        if($enums->isNotEmpty() && ! self::isScalarIterable($enums))
            throw new InvalidArgumentException('enum must be iterable[scalar] or scalar types');
        if(empty($cfId) || ($values->isEmpty() && $enums->isEmpty())) return null;

        if(is_numeric($cfId)) $cf['field_id'] = (int)$cfId;
        if(is_string($cfId)) $cf['field_code'] = (string)$cfId;

        if($enums->isNotEmpty()){
            $cf['values'] = $enums->map(function($enum){
                if(is_numeric($enum)) return ['enum_id' => (int)$enum];
                if(is_string($enum)) return ['enum_code' => (string)$enum];
                return [];
            })->all();
        } else {
            $cf['values'] = $values->map(function($value){
                return ['value' => $value];
            })->all();
        }

        return $cf;
    }

    public static function numericArray(iterable $array): array
    {
        // мы не можем сделать это через ->filter() коллекции,
        // потому что filter передает в callable аргумент 2 аргумента,
        // а is_numeric бросает ошибку если передано более 1 аргумента
        return array_filter(collect($array)->all(), 'is_numeric');
    }

    /**
     * приводит к инту все элементы массива-аргумента если это возможно
     * [1, '2', 3.3, 'abc'] => [1,2,3]
     *
     * @param array $iterable
     * @return array
     */
    public static function toIntArray(iterable $iterable): array
    {
        return array_map('intval', self::numericArray($iterable));
    }

    /**
     * Аналогичен toIntArray но кроме этого еще и фильтрует массив на уникальность
     *
     * @param iterable $array
     * @return array
     */
    public static function toIDArray(iterable $array): array
    {
        return array_unique(self::toIntArray($array));
    }

    /**
     * При обновлении значения CF type date амо принимает timestamp
     *
     * @param string|null $date
     * @return int|null
     */
    public static function amoDate(?string $date, ?string $timezone = null): ?int
    {
        // DateTime конструктор не переваривает 2000.01.01 например ему нужно 2000-01-01
        $date = str_replace('.', '-', $date);
        return self::timestamp(self::DateTime($date, $timezone));
    }

    public static function excelBool(?bool $value): string
    {
        if(is_null($value)) return '';
        return $value ? 'ДА' : 'НЕТ';
    }
}
