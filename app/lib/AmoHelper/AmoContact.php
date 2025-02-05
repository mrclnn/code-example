<?php

namespace App\lib\AmoHelper;

use App\lib\AmoMapping\samolet\CF_CONTACT;
use App\lib\AmoMapping\samolet\PIPELINE;
use App\lib\BusinessLogic\Interest;
use App\lib\BusinessLogic\JK;
use App\lib\BusinessLogic\Podbor;
use App\lib\FORMAT;
use stdClass;

class AmoContact extends AmoEntity
{
    protected ?string $firstName;
    protected ?string $lastName;
    protected ?bool $isUnsorted;
    protected string $entityType = AmoHelper::ENTITY_TYPE_CONTACT;

    public function __construct(?stdClass $contact = null)
    {
        parent::__construct($contact);
        $contact = $contact ?? new stdClass();

        $this->firstName = $contact->first_name ?? null;
        $this->lastName = $contact->last_name ?? null;
        $this->isUnsorted = (bool)($contact->is_unsorted ?? false);
    }

    /**
     * Возвращает массив int номеров телефона контакта
     *
     * @return int[]
     */
    public function getPhones(): array
    {
        return array_map(function($phone){ return (int)$phone; }, $this->getCFV('PHONE') ?? []);
    }

    public function setChildrenExistence(bool $exist): self
    {
        $this->setCF(CF_CONTACT::CHILDREN_EXISTS, $exist);
        return $this;
    }

    public function getMainPhone(): ?int
    {
        return current($this->getPhones()) ?: null;
    }

    /**
     * Возвращает имя контакта, как комбинацию Фамилия + Имя, либо как просто Имя контакта (отдельное поле)
     * Форматирует как имя
     * Переданный параметр указывает стоит ли использовать западный формат имени: Имя Фамилия [Отчество],
     * вместо СНГ формата имени: Фамилия Имя Отчество
     * т.е по умолчанию возвращается ФИО, если параметр передать в true, то вернется ИФО
     *
     * @param bool $enNameFormat
     * @return string
     */
    public function getFullName(bool $enNameFormat = false): string
    {
        if($enNameFormat) return trim(FORMAT::name(implode(' ', [$this->getFirstName(), $this->getLastName()]) ?: $this->getName()));
        return trim(FORMAT::name(implode(' ', [$this->getLastName(), $this->getFirstName()]) ?: $this->getName()));
    }

    /**
     * Возвращает массив всех email контакта
     *
     * @return string[]
     */
    public function getEmails(): array
    {
        return $this->getCFV('EMAIL') ?? [];
    }

    /**
     * Перезаписывает емейлы. т.е. если были определены иные, то они затрутся новыми
     * принимает iterable string с email почтами или одиночный string
     *
     * @return void
     */
    public function updEmail($emails)
    {
        if(is_null($emails)) return;
        if(!is_iterable($emails) && !is_string($emails))
            throw new \InvalidArgumentException('Argument emails passed to '.__METHOD__.'must be typeof iterable or string');
        $emails = collect($emails);
        $this->setCF('EMAIL', $emails);
    }

    public function addEmail($emails)
    {

    }

    public function empty(): bool
    {
        //todo возможно здесь пригодится дополнительная проверка мол, нет телефона и тд например:
        // return parent::empty() && (bool)$this->getPhone();
        return parent::empty();
    }
    public function getFirstName(): ?string { return $this->firstName; }
    public function getLastName(): ?string { return $this->lastName; }
    public function getIsUnsorted(): bool { return $this->isUnsorted; }

    /**
     * Часто в амо отдельные поля под фамилию имя и отчество не заполняются. а все пишется в поле name через пробел.
     * Эта функция нужна чтобы распарсить поле name на имя фамилию и отчество и вернуть соответствующие значения
     *
     * @return string
     */
    public function getFirstNameFromName(): ?string
    {
        return collect(explode(' ', $this->getName()))->get(1) ?: null;
    }
    /**
     * Часто в амо отдельные поля под фамилию имя и отчество не заполняются. а все пишется в поле name через пробел.
     * Эта функция нужна чтобы распарсить поле name на имя фамилию и отчество и вернуть соответствующие значения
     *
     * @return string
     */
    public function getLastNameFromName(): ?string
    {
        return collect(explode(' ', $this->getName()))->get(0) ?: null;
    }
    /**
     * Часто в амо отдельные поля под фамилию имя и отчество не заполняются. а все пишется в поле name через пробел.
     * Эта функция нужна чтобы распарсить поле name на имя фамилию и отчество и вернуть соответствующие значения
     *
     * @return string
     */
    public function getMiddleNameFromName(): ?string
    {
        return collect(explode(' ', $this->getName()))->get(2) ?: null;
    }

    /**
     * Дополняет родительский метод получения структуры для обновления уникальными для контакта полями
     *
     * @return array
     */
    public function getUpdateStructureV4(): array
    {
        $structure = parent::getUpdateStructureV4();
        return array_filter(array_merge($structure, [
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
        ]), function($field){ return !empty($field); });
    }

    /**
     * @return int[]
     */
    public function getLinkedLeadsIds(): array
    {
        return array_diff(array_map(function($lead){
            return (int)($lead->id ?? 0) ?: null;
        }, $this->getEmbedded()->leads ?? []), [null]);
    }
}
