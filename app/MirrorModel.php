<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

abstract class MirrorModel extends Model
{
    /** @var array
     * это надо для того чтобы делать model->fill. заморочка лары, см доки:
     * https://laravel.com/docs/7.x/eloquent#mass-assignment
     */
    protected $guarded = [];
    /**
     * Проверка на то что модель можно создать в зеркале и база не будет ругаться.
     * Т.е. все поля которые в базе not nullable должны быть заполнены
     *
     * Этод метод нужен если нас НЕ интересует какое конкретно поле не корректно
     *
     * @return bool
     */
    public function isValid(): bool
    {
        try{
            $this->checkValid();
            return true;
        } catch (\Throwable $e){
            return false;
        }
    }

    /**
     * Проверка на то что модель можно создать в зеркале и база не будет ругаться.
     * Т.е. все поля которые в базе not nullable должны быть заполнены
     *
     * Этод метод нужен если нас интересует какое конкретно поле не корректно
     *
     * @return void
     */
    public function checkValid(): void
    {
        if(empty($this->id)) throw new InvalidArgumentException("Missing required property id for MirrorModel");
        if(empty($this->name)) throw new InvalidArgumentException("Missing required property name for MirrorModel");
        if(empty($this->created_at)) throw new InvalidArgumentException("Missing required property created_at for MirrorModel");
        if(empty($this->updated_at)) throw new InvalidArgumentException("Missing required property updated_at for MirrorModel");
        if(empty($this->responsible_user_id)) throw new InvalidArgumentException("Missing required property responsible_user_id for MirrorModel");
        if(empty($this->created_by)) throw new InvalidArgumentException("Missing required property created_by for MirrorModel");
        if(empty($this->updated_by)) throw new InvalidArgumentException("Missing required property updated_by for MirrorModel");
    }

    /**
     * если в зеркале нет записей, то вернется null а из strtotime(null) вернется false.
     * (int) нужен для преобразования к 0 в таком случае
     * определяем именно по полю в амо, а не по полю обновления записи в зеркале.
     * из разницы updated_at и updated_at_mirror можно будет судить о задержке получения данных из амо в зеркало
     *
     * @return int
     */
    public static function getLastUpdatedTimestamp(): int
    {
        return (int)strtotime(self::query()->max('updated_at'));
    }

    /** @var string переопределяем потому что у сущностей амо есть свои поля created_at и updated_at */
    public const CREATED_AT = 'created_at_mirror';

    /** @var string переопределяем потому что у сущностей амо есть свои поля created_at и updated_at */
    public const UPDATED_AT = 'updated_at_mirror';
}
