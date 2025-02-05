<?php

namespace App\lib\AmoHelper;

use stdClass;

class AmoCF
{
    private static stdClass $allCFs;
    const CF_TYPE_TEXT = 'text';
    const CF_TYPE_NUMERIC = 'numeric';
    const CF_TYPE_CHECKBOX = 'checkbox';
    const CF_TYPE_SELECT = 'select';
    const CF_TYPE_MULTISELECT = 'multiselect';
    const CF_TYPE_MULTI_TEXT = 'multitext';
    const CF_TYPE_DATE = 'date';
    const CF_TYPE_URL = 'url';
    const CF_TYPE_TEXTAREA = 'textarea';
    const CF_TYPE_RADIOBUTTON = 'radiobutton';
    const CF_TYPE_STREET_ADDRESS = 'streetaddress';
    const CF_TYPE_SMART_ADDRESS = 'smart_address';
    const CF_TYPE_BIRTHDAY = 'birthday';
    const CF_TYPE_LEGAL_ENTITY = 'legal_entity';
    const CF_TYPE_DATE_TIME = 'date_time';
    const CF_TYPE_PRICE = 'price';
    const CF_TYPE_CATEGORY = 'category';
    const CF_TYPE_ITEMS = 'items';
    const CF_TYPE_TRACKING_DATA = 'tracking_data';
    const CF_TYPE_LINKED_ENTITY = 'linked_entity';
    const CF_TYPE_CHAINED_LIST = 'chained_list';
    const CF_TYPE_MONETARY = 'monetary';
    const CF_TYPE_FILE = 'file';
    const CF_TYPE_PAYER = 'payer';
    const CF_TYPE_SUPPLIER = 'supplier';
    const CF_TYPES = [
        self::CF_TYPE_TEXT,
        self::CF_TYPE_NUMERIC,
        self::CF_TYPE_CHECKBOX,
        self::CF_TYPE_SELECT,
        self::CF_TYPE_MULTISELECT,
        self::CF_TYPE_MULTI_TEXT,
        self::CF_TYPE_DATE,
        self::CF_TYPE_URL,
        self::CF_TYPE_TEXTAREA,
        self::CF_TYPE_RADIOBUTTON,
        self::CF_TYPE_STREET_ADDRESS,
        self::CF_TYPE_SMART_ADDRESS,
        self::CF_TYPE_BIRTHDAY,
        self::CF_TYPE_LEGAL_ENTITY,
        self::CF_TYPE_DATE_TIME,
        self::CF_TYPE_PRICE,
        self::CF_TYPE_CATEGORY,
        self::CF_TYPE_ITEMS,
        self::CF_TYPE_TRACKING_DATA,
        self::CF_TYPE_LINKED_ENTITY,
        self::CF_TYPE_CHAINED_LIST,
        self::CF_TYPE_MONETARY,
        self::CF_TYPE_FILE,
        self::CF_TYPE_PAYER,
        self::CF_TYPE_SUPPLIER,
    ];
    private int $id;
    private string $name;
    private int $codeV2;
    private string $codeV4;
    private string $type;
    private array $values;
    public function __construct(stdClass $cf)
    {
        //todo если нужна информация о том системное ли это поле (есть в методах типа /api/v2/leads но нет в v4),
        // то можно через /api/v4/leads/custom_fields, там будет эта инфо, можно соотнести
        $this->id = $cf->field_id ?? $cf->id ?? 0; //todo проверить какие там еще варианты есть
        $this->name = $cf->field_name ?? $cf->name ?? '';
        $this->codeV4 = $cf->field_code ?? '';
        $this->codeV2 = (int)($cf->code ?? 0);
        $this->type = $cf->field_type ?? $cf->type ?? ''; //todo вообще тут не должно быть значения по умолчанию
        $this->values = $cf->values ?? [];
    }

    public function setId(int $id) { $this->id = $id; }

    /**
     * v4 версия не поддерживает передачу code как идентификатора для поля, по этому передаем только field_id
     * values содержит структуру массива, который получали от amo api же, но очищаем от null полей типа enum_code=>null,
     * иначе не пропустит валидацей. при этом например для cf телефонов enum_code=>work остается заполненным
     *
     * @return array
     */
    public function getV4Structure(): array
    {
        //todo можем передавать field_code это будет работать
        if(empty($this->getId())) return [];
        return [
            'field_id' => $this->getId(),
            'values' => array_filter(array_map(function($cfv){
                $value = (object)array_filter((array)$cfv, function($field){
                    return !is_null($field);
                });
                if(isset($value->enum_id) && !in_array($this->getCode(), ['PHONE', 'EMAIL'])){
                    return (object)[
                        'enum_id' => $value->enum_id
                    ];
                }
                if($this->getType() === self::CF_TYPE_MULTISELECT && !empty($value->value)) {
                    return (object)[
                        'value' => $value->value
                    ];
                }

                return $value;
            }, $this->getValues()), function($cfv){return !empty((array)$cfv);}),
        ];
    }


    public function setValue($value): void
    {
        if(empty($values = $this->getValues())) return;
        if(!is_array($value)) {
            $this->values = [['value' => $value]];
//            if (count($values) === 1) $values[0]->value = $value;
            return;
        }
        //todo опять же без проверки типов полей это может быть очень опасно
        $this->values = $value;
    }


    //  =================================
    //  ============ GETTERS ============
    //  =================================


    public function getValue()
    {
        // если обычные поля
        if(empty($values = $this->getValues())) return null;
        if(count($values) === 1) {
            if(!in_array(strtolower($this->getCode()), ['phone', 'email'])) return $values[0]->value ?? $values[0]['value'] ?? null;
        }
        // если мультиполе
        $res = [];
        foreach($this->getValues() as $value){
            if(!($enum = $value->enum_id ?? $value->enum)) continue;
            if(in_array(strtolower($this->getCode()), ['phone', 'email'])){
                $res[] = $value->value;
            } else {
                $res[$enum] = $value->value;
            }
        }
        return $res;
    }


    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getCode() { return $this->codeV4 ?? $this->codeV2; }
    public function getValues(): array {
        if($this->getType() === AmoCF::CF_TYPE_TEXT) return ($this->values[0] ?? null) ? [$this->values[0]] : [];
        return $this->values;
    }
    //todo getValues вероятно вообще стоит сделать приватным методом,
    // а в интерфейсе пользоваться только getValue который уже будет брать значения из getValues в зависимости от getType
    public static function getENUMValue(int $cf, int $enum, string $entityType)
    {
        if($entityType === AmoHelper::ENTITY_TYPE_LEAD) return self::getLeadENUMName($cf, $enum);
        if($entityType === AmoHelper::ENTITY_TYPE_CONTACT) return self::getContactENUMName($cf, $enum);
        throw new \InvalidArgumentException("Received unsupported entity type: $entityType");
    }
    public static function getLeadENUMName(int $cf, int $enum)
    {
        return self::getLeadsCFs()->$cf->enums->$enum;
    }
    public static function getContactENUMName(int $cf, int $enum)
    {
        return self::getContactsCFs()->$cf->enums->$enum;
    }
    private static function getLeadsCFs(): stdClass
    {
        return self::getAllCFs()->_embedded->custom_fields->leads ?? new stdClass();
    }
    private static function getContactsCFs(): stdClass
    {
        return self::getAllCFs()->_embedded->custom_fields->contacts ?? new stdClass();
    }
    private static function getAllCFs(): ?stdClass
    {
        return self::$allCFs ?? self::$allCFs = AmoHelper::getAllCFs();
    }
}
