<?php

namespace App\lib\AmoHelper;

use App\lib\AmoCrmApi\AmoCrmApi;
use App\lib\BusinessLogic\Act;
use App\lib\BusinessLogic\Interest;
use App\lib\BusinessLogic\JK;
use App\lib\BusinessLogic\Lot;
use App\lib\BusinessLogic\Payment;
use App\lib\BusinessLogic\Podbor;
use App\lib\CacheHelper;
use App\lib\FORMAT;
use Exception;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Iterator;
use RuntimeException;
use stdClass;
use Throwable;

class AmoHelper
{
    use CacheHelper;
    const ENTITY_TYPE_LEAD = 'leads';
    const ENTITY_TYPE_CONTACT = 'contacts';
    const ENTITY_TYPE_COMPANY = 'companies';
    const ENTITY_TYPE_TASK = 'tasks';
    const ENTITY_TYPE_EVENT = 'events';
    const ENTITY_TYPES = [
        self::ENTITY_TYPE_LEAD,
        self::ENTITY_TYPE_CONTACT,
        self::ENTITY_TYPE_COMPANY,
    ];
    const ENTITIES_QUERY_LIMIT = 250;
    const ENTITIES_QUERY_DEFAULT = 50;
    const CF_TYPE_TEXT = 'text';
    const CF_TYPE_NUMERIC = 'numeric';
    const CF_TYPE_CHECKBOX = 'checkbox';
    const CF_TYPE_SELECT = 'select';
    const CF_TYPE_MULTISELECT = 'multiselect';
    const CF_TYPE_DATE = 'date';
    const CF_TYPE_URL = 'url';
    const CF_TYPE_TEXTAREA = 'textarea';
    const CF_TYPE_RADIOBUTTON = 'radiobutton';
    const CF_TYPE_STREETADDRESS = 'streetaddress';
    const CF_TYPE_SMART_ADDRESS = 'smart_address';
    const CF_TYPE_BIRTHDAY = 'birthday';
    const CF_TYPE_LEGAL_ENTITY = 'legal_entity';
    const CF_TYPE_DATE_TIME = 'date_time';
    const CF_TYPE_PRICE = 'price';
    const CF_TYPE_CATEGORY = 'category';
    const CF_TYPE_ITEMS = 'items';
    const CF_TYPE_MULTITEXT = 'multitext';
    const CF_TYPE_TRACKING_DATA = 'tracking_data';
    const CF_TYPE_LINKED_ENTITY = 'linked_entity';
    const CF_TYPE_CHAINED_LIST = 'chained_list';
    const CF_TYPE_MONETARY = 'monetary';
    const CF_TYPE_FILE = 'file';
    const CF_TYPE_PAYER = 'payer';
    const CF_TYPE_SUPPLIER = 'supplier';
    /** @var string[]
     * под простым типом данных имеется ввиду такой, для записи которого
     * достаточно полей value, enum_id и enum_code.
     * сложные поля требуют кастомных полей типа legal_entity требует поля bank_account_number и тп.
     * по этому их нельзя записывать как строку в зеркало, их нужно хранить именно как json
     */
    const CF_TYPES_SIMPLE = [
        self::CF_TYPE_TEXT,
        self::CF_TYPE_NUMERIC,
        self::CF_TYPE_TEXTAREA,
        self::CF_TYPE_PRICE,
        self::CF_TYPE_STREETADDRESS,
        self::CF_TYPE_TRACKING_DATA,
        self::CF_TYPE_MONETARY,
        self::CF_TYPE_CHECKBOX,
        self::CF_TYPE_URL,
        self::CF_TYPE_DATE,
        self::CF_TYPE_DATE_TIME,
        self::CF_TYPE_BIRTHDAY,
        self::CF_TYPE_SELECT,
        self::CF_TYPE_MULTISELECT,
        self::CF_TYPE_RADIOBUTTON,
        self::CF_TYPE_CATEGORY,
        self::CF_TYPE_SMART_ADDRESS,
        self::CF_TYPE_MULTITEXT,
    ];

    const CF_TYPES_COMPLEX = [
        self::CF_TYPE_LEGAL_ENTITY,
        self::CF_TYPE_ITEMS,
        self::CF_TYPE_LINKED_ENTITY,
        self::CF_TYPE_CHAINED_LIST,
        self::CF_TYPE_FILE,
        self::CF_TYPE_PAYER,
        self::CF_TYPE_SUPPLIER,
    ];
    private static AmoCrmApi $amo;
    private static Collection $accountUsers;

    /**
     * на проде мы разделили интеграции на три штуки для избежания лимитов по 429. на дев такой необходимости нет,
     * по этому поднимать 2 дополнительные интеграции для дев на акке амо нет смысла, и пользуемся всегда одной основной dev интеграцией
     * это свойство содержит id интеграции которая используется в данной AmoHelper
     *
     * @var int
     */
    private static int $integration = GENERAL_CONFIG::CLIENT_ID;

    private static bool $debugMode = false;
    public static function setDebugMode(bool $debugMode): void
    {
        self::$debugMode = $debugMode;
        self::amo()::debug(true);
    }

    /**
     * Используется для установки id интеграции через которую взаимодействуем с амо.
     * на проде мы разделили интеграции на три штуки для избежания лимитов по 429. на дев такой необходимости нет,
     * по этому поднимать 2 дополнительные интеграции для дев на акке амо нет смысла, и пользуемся всегда одной основной dev интеграцией
     *
     * @throws Exception
     */
    public static function setIntegration(int $integration): void
    {
        $integrationExist = DB::table('clients')->where('id', $integration)->first();
        if(!$integrationExist) return;
        self::$integration = $integration;
        self::amo(true);
    }

    /**
     * В идеале сделать deleteEntitiesById но с контактами запрос почему-то не проходит
     *
     * @param iterable $leadsIds
     * @return void
     * @throws Exception
     */
    public static function deleteLeadsByIds(iterable $leadsIds): void
    {
        $leadsIds = FORMAT::toIDArray($leadsIds);
        if(empty($leadsIds)) return;
        $response = self::amo()->__request('post', '/ajax/leads/multiple/delete', [
            'ID' => $leadsIds,
        ], ['X-Requested-With: XMLHttpRequest']);
        if(($response->status ?? null) !== 'success')
            throw new RuntimeException("Unable to delete lead: ".($response->message ?? 'no error message from amo'));
    }

    public static function deleteEntitiesById(iterable $entitiesIds, string $entityType)
    {
        //todo сделать deleteEntitiesById но с контактами запрос почему-то не проходит
        self::checkType($entityType);

    }

    public static function findContact(AmoContact $contact): ?AmoContact
    {
        $phones = $contact->getPhones();
        $emails = $contact->getEmails();
        if(empty($phones) && empty($emails)) return null;
        foreach($phones as $phone){
            $found = self::findContactByPhone($phone);
            if($found) return $found;
        }
        foreach($emails as $email){
            $found = self::findContactByMail($email);
            if($found) return $found;
        }
        return null;
    }
    public static function getCFV($cfId, $entity)
    {
        $cf = self::getCF($cfId, $entity);
        $cfType = (string)($cf->type_id ?? $cf->field_type ?? null);

        switch ($cfType){
            case 'price':   // field_type v4 api
            case '20':      // type_id v2 api
            case 'textarea':
            case '9':
            case 'numeric':
            case '2':
            case 'date':
            case '6':
            case 'text':
            case '1':
            case 'select':
            case '4':
            case 'url':
            case '7':
            case 'checkbox':
            case '3':
                return $cf->values[0]->value ?? null;
            case 'payer':
            case '26':
                return $cf->values[0]->value->name ?? null;
            case 'items':
            case '16':
                return $cf->values[0] ?? [];
            case 'multiselect':
            case '5':
                // для мультиселекта вернёт массив с парой ключ => значение: enum_id => value
                $res = [];
                foreach($cf->values ?? [] as $value){
                    $res[$value->enum_id ?? $value->enum] = $value->value;
                }
                return $res;
            case 'multitext':
            case '8':
                // для телефонов и email вернёт массив с целочисленными ключами
                // массив не содержит пустых значений (== null)
                return array_values(array_diff(array_map(function($cf){ return $cf->value ?? null; }, $cf->values ?? []), [null]));
            default:
                //todo в идеале после перечисления всех типов доступных в амо выбрасывать в default ошибку,
                // типа "Received unsupported cf type: $cfType"
                return $cf->values[0]->value ?? null;
        }

    }
    public static function getCF($cfId, $entity): ?object
    {
        if($entity instanceof Collection) $entity = (object)$entity->all();
        $cfList = $entity->custom_fields_values ?? $entity->custom_fields ?? [];
        $cfList = is_object($cfList) ? [] : $cfList;
        if(empty($cfList)){
            $cfList = array_merge( // если мы передаем параметром $entity результат (new AmoCrmApi())->getCF(); т.е. все cf на аккаунте
                (array)($entity->_embedded->custom_fields->leads ?? $entity->leads ?? null),
                (array)($entity->_embedded->custom_fields->contacts ?? $entity->contacts ?? null),
                (array)($entity->_embedded->custom_fields->companies ?? $entity->companies ?? null),
                (array)($entity->_embedded->custom_fields->customers ?? $entity->customers ?? null),
            );
        }

        return current(array_filter($cfList, function($cf) use ($cfId){
            if(gettype($cfId) === 'integer'){
                if(isset($cf->id)) return (int)$cf->id === $cfId;
                if(isset($cf->field_id)) return (int)$cf->field_id === $cfId;
            }
            if(gettype($cfId) === 'string'){
                if(isset($cf->code)) return $cf->code === $cfId;
                if(isset($cf->field_code)) return $cf->field_code === $cfId;
            }
            return false;
        })) ?: null;
    }
    public static function findContactByMail(?string $email): ?AmoContact
    {
        if(empty($email) || strlen($email) < 5) return null;
        $contacts = self::request('get', '/api/v4/contacts', ['query' => $email]);
        foreach ($contacts->_embedded->contacts ?? [] as $contact) {
            $contact = new AmoContact($contact);
            if (in_array($email, $contact->getEmails(), true)) return $contact;
        }
        return null;
    }

    /**
     * Принимает внутренний id компании домбук, пытается найти его в амо.
     * Например такого вида: b3182da5-e103-482c-a314-c733ae0dca2d
     *
     * @param string|null $dombookId
     * @return null|stdClass
     */
    public static function findProjectByDombookId(?string $dombookId): ?stdClass
    {
        if(empty($dombookId)) return null;
        if(strlen($dombookId) !== 36) return null;
        $companies = self::request('get', '/api/v4/companies', ['query' => $dombookId]);
        foreach ($companies->_embedded->companies ?? [] as $company){
            if(AmoHelper::getCFV(CF_COMPANY::PROJECT_DB_ID, $company) === $dombookId) return $company;
        }
        return null;
    }
    public static function findContactByPhone(?int $phone): ?AmoContact
    {
        if(empty($phone) || strlen($phone) < 7) return null;
        $contacts = self::request('get', '/api/v4/contacts', ['query' => $phone, 'with' => 'leads']);
        foreach ($contacts->_embedded->contacts ?? [] as $contact) {
            $contact = new AmoContact($contact);
            if(in_array($phone, $contact->getPhones())) return $contact;
        }
        return null;
    }
    public static function getContact(?int $id): ?AmoContact
    {
        return self::getContactsById([$id])->current();
    }
    public static function getLead(?int $id): ?AmoLead
    {
        return self::getLeadsById([$id])->current();
    }
    public static function getCompany(?int $id): ?AmoCompany
    {
        return self::getCompaniesById([$id])->current();
    }
    public static function getLot(?int $id): ?Lot
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($lot = new Lot(new AmoLead(json_decode($lead->getJSON()))))) ? null : $lot;
    }

    public static function getAct(?int $id): ?Act
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        return empty((array)($lot = new Act(new AmoLead(json_decode($lead->getJSON()))))) ? null : $lot;
    }
    public static function getInterest(?int $id): ?Interest
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        //todo надо переделать
        return empty((array)($interest = new Interest(new AmoLead(json_decode($lead->getJSON()))))) ? null : $interest;
    }
    public static function getJK(?int $id): ?JK
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        //todo надо переделать
        return empty((array)($jk = new JK(new AmoLead(json_decode($lead->getJSON()))))) ? null : $jk;
    }
    public static function getPodbor(?int $id): ?Podbor
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        //todo надо переделать
        return empty((array)($podbor = new Podbor(new AmoLead(json_decode($lead->getJSON()))))) ? null : $podbor;
    }

    public static function getPayment(?int $id): ?Payment
    {
        if(empty($id)) return null;
        if(empty($lead = self::getLead($id))) return null;
        $payment = new Payment($lead);
        return $payment->getId() ? $payment : null;
    }

    /**
     * reInit свойство нужно для принудительной реинициализации AmoCrmApi объекта.
     * например если мы по ходу выполнения скрипта меняем $integration свойство.
     *
     * @param bool $reInit
     * @return AmoCrmApi
     * @throws Exception
     */
    private static function amo(bool $reInit = false): AmoCrmApi
    {
        if($reInit) return self::$amo = new AmoCrmApi(self::$integration);
        return self::$amo ?? self::$amo = new AmoCrmApi(self::$integration);
    }

    /**
     * @param iterable $entitiesIds
     * @param string $entityType
     * @return Iterator<AmoEntity>
     */
    private static function getEntitiesById(iterable $entitiesIds, string $entityType) : Iterator
    {
        self::checkType($entityType);
        $entitiesIds = collect(FORMAT::toIDArray($entitiesIds));
        foreach($entitiesIds->chunk(self::ENTITIES_QUERY_LIMIT) as $entitiesIdsChunk){
            $filter = ['id' => $entitiesIdsChunk->all()];
            $with = ['leads','contacts','tags','source_id'];
            $amoResponse = self::request('get', "/api/v4/$entityType", ['filter' => $filter, 'with' => $with]);
            //todo на этом этапе нужно amoResponse класть в кэш
            foreach($amoResponse->_embedded->$entityType ?? [] as $entity){
                if($entityType === self::ENTITY_TYPE_LEAD) yield new AmoLead($entity);
                if($entityType === self::ENTITY_TYPE_CONTACT) yield new AmoContact($entity);
                if($entityType === self::ENTITY_TYPE_COMPANY) yield new AmoCompany($entity);
            }
        }
    }

    public static function createEntity(AmoEntity $entity): void
    {
        //todo в данный момент мы возвращаем тот же объект, просто добавляем ему id созданного.
        // возможно правильнее будет получать из амо новый объект по id, там будут иными несколько полей (например created_at, updated_at)
        // т.к. мы их не передаем
        try{
            $entityType = $entity->getEntityType();
            $response = self::request('post', "/api/v4/$entityType", [$entity->refreshId()->getUpdateStructureV4()]);
            $createdEntityID = $response->_embedded->{$entityType}[0]->id ?? null;
            if(empty($createdEntityID)) throw new RuntimeException("Unable to create $entityType. Amo response: ".json_encode($response));
            $entity->setId($createdEntityID);
        } catch (\Throwable $e){
            $er = "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}, upd structure: ".json_encode($entity->getUpdateStructureV4());
            throw new RuntimeException($er);
        }
    }

    public static function updateEntity(AmoEntity $entity): void
    {
        try{
            $entityType = $entity->getEntityType();
            if(empty($entity->getId())) throw new InvalidArgumentException("Trying to update not existed entity $entityType");
            $response = self::request('patch', "/api/v4/$entityType", [$entity->getUpdateStructureV4()]);
        } catch (\Throwable $e){
            $er = "{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}, upd structure: ".json_encode($entity->getUpdateStructureV4());
            throw new RuntimeException($er);
        }
    }

    /**
     * @param array $leadsIds
     * @return Generator<AmoLead>
     */
    public static function getLeadsById(iterable $leadsIds) : Generator
    {
        return self::getEntitiesById($leadsIds, self::ENTITY_TYPE_LEAD);
    }

    /**
     * @param array $contactsIds
     * @return Generator<AmoContact>
     */
    public static function getContactsById(iterable $contactsIds) : Generator
    {
        return self::getEntitiesById($contactsIds, self::ENTITY_TYPE_CONTACT);
    }

    /**
     * @param array $companiesIds
     * @return Generator<AmoCompany>
     */
    public static function getCompaniesById(iterable $companiesIds) : Generator
    {
        return self::getEntitiesById($companiesIds, self::ENTITY_TYPE_COMPANY);
    }

    public static function getAllCFs(): ?stdClass
    {
        //todo эта функция возвращает идентичный ответ на запрос к амо, что и self::getAccount()
        // только тут это объект а там коллекция
//        return self::amo()->getCF();
        return (object)self::getAccount()->all();
    }

    public static function issetUser(?int $id): bool
    {
        if(empty($id)) return false;
        $user = self::getUsers()->get($id);
        if(empty($user)) return false;
        return $user->rights->is_active ?? false;
    }

    /**
     * Возвращает инфо по пользователям из амо: по возможности берёт данные из кэша
     * (автообновление каждые сутки при запросе)
     * Отличие с функцией getUsers в том, что эта использует v2.
     * При этом при использовании v2 мы получаем номер телефона пользователя, а при v4 нет,
     * по этому в некоторых случаях нужна именно эта функция.
     *
     * @param bool $force если true, то не проверяет кэш, а делает запрос к амо
     * @return Collection коллекция пользователей: ответов амо на запрос /api/v4/leads/users ->_embedded->users
     */
    public static function getAccountUsers(bool $force = false): Collection
    {
        return self::$accountUsers ??
            self::$accountUsers = collect(self::getAccount($force)->get('_embedded')->users ?? []);
    }

    public static function addSystemNote(int $entityID, string $note, string $service): void
    {
        self::request('post', "/api/v4/leads/$entityID/notes", [
            [
                'note_type' => 'extended_service_message',
                'params' => [
                    'text' => $note,
                    'service' => $service,
                ]
            ]
        ]);
    }

    /**
     * Обновленная версия фукнции, работает с кэшем (обновляется раз в сутки, или при обновлении поля)
     * Если получает значение cf enum которое уже существовало, запросов к амо не происходит
     *
     * @param $field_id
     * @param $value
     * @return int|null
     */
    public static function autoUpdateMultiselect($field_id, $value): ?int
    {
        try{
            if(empty($value)) return null;
            $enum = self::getENUM($field_id, $value);
            if($enum) return $enum;
            self::updateMultiSelect($field_id, $value);
            $enum = self::getENUM($field_id, $value, true);
            if(!$enum) throw new RuntimeException("Unable to update field $field_id by enum $value");
            return $enum;
        } catch (\Throwable $e){
            // getENUM выбросит исключение если $field_id не существует, или это не мультиселект/селект
            dump("{$e->getMessage()} in file {$e->getFile()} at line {$e->getLine()}");
            return null;
        }
    }

    /**
     * Функция принимает массив $upd и обновляет его в амо.
     * $upd - это тело запроса к амо, т.е. массив который содержит сущности для обновления
     * Если всё прошло успешно - вернет пустой массив
     * Возвращает массив лидов, обновить которые не удалось:
     * [['reason' => reason, 'entity' => entity]]
     * где reason это массив с ошибками из амо, а entity - сущность которую передали аргументов в эту функцию
     * array:2 [▼
     * 0 => array:2 [▶]
     * 1 => array:2 [▼
     * "reason" => array:1 [▼
     * 0 => {#2246 ▼
     * +"code": "NotSupportedChoice"
     * +"path": "custom_fields_values.1.values.0.value"
     * +"detail": "The value you selected is not a valid choice."
     * }
     * ]
     * "entity" => array:3 [▼
     * "id" => 4070439
     * "price" => 500
     * "custom_fields_values" => array:2 [▼
     * 0 => array:2 [▼
     * "field_id" => 967623
     * "values" => array:1 [▶]
     * ]
     * 1 => array:2 [▼
     * "field_id" => 975275
     * "values" => array:1 [▼
     * 0 => array:1 [▼
     * "value" => "four"
     * ]
     * ]
     * ]
     * ]
     * ]
     * ]
     * ]
     *
     * @param array $upd
     * @return void
     * @throws Throwable
     */
    public static function updateLeads(array $upd): array
    {
        return self::updateEntities($upd, self::ENTITY_TYPE_LEAD);
    }

    /**
     * @throws Throwable
     */
    public static function updateContacts(array $upd): array
    {
        return self::updateEntities($upd, self::ENTITY_TYPE_CONTACT);
    }

    /**
     * @throws Throwable
     */
    public static function updateContact(array $updEntity): array
    {
        return self::updateContacts([$updEntity]);
    }

    public static function createContacts(array $add): array
    {
        return self::createEntities($add, self::ENTITY_TYPE_CONTACT);
    }

    /**
     * @throws Throwable
     */
    public static function updateLead(array $updEntity): array
    {
        return self::updateLeads([$updEntity]);
    }

    public static function createLeads(array $add): array
    {
        return self::createEntities($add, self::ENTITY_TYPE_LEAD);
    }

    /**
     * @throws Throwable
     */
    public static function updateCompanies(array $upd): array
    {
        return self::updateEntities($upd, self::ENTITY_TYPE_COMPANY);
    }

    public static function createCompanies(array $add): array
    {
        return self::createEntities($add, self::ENTITY_TYPE_COMPANY);
    }

    /**
     * @throws Throwable
     */
    public static function updateCompany(array $updEntity): array
    {
        return self::updateCompanies([$updEntity]);
    }
    private static function saveEntitiesLegacy(array $upd, string $entityType, bool $create = false): array
    {
        //todo во первых мы должны разделить массив на кол-во элементов которые позволяет амо обновить
        // т.е. посылать запросы с чанками
        self::checkType($entityType);
        $actionType = $create ? 'post' : 'patch';
        $unableToUpdate = [];
        foreach(array_chunk($upd, self::ENTITIES_QUERY_DEFAULT) as $updChunk){

            $recursionIndex = 0;
            $recursionLimit = 10;
            do{
                if($recursionIndex++ > $recursionLimit) {
                    //todo при любых условиях не должно быть больше 2х итераций
                    throw new RuntimeException('recursion limit exceed, terminate');
                }
                try{
                    if(empty($updChunk)) break;
                    $response = self::request($actionType, "/api/v4/$entityType", $updChunk);
                } catch (Throwable $e){
                    if($e->getCode() !== 400) throw $e;

                    $amoResponse = str_replace('Amo return 400: ', '', $e->getMessage());
                    if(!FORMAT::isJson($amoResponse)) throw $e;
                    $amoResponse = collect(json_decode($amoResponse));
                    $errs = $amoResponse->get('validation-errors');
                    if(!empty($errs)) {
                        foreach ($errs as $error){
                            $problemEntity = $updChunk[$error->request_id];
                            $unableToUpdate[] = [
                                'reason' => $error->errors,
                                'entity' => $problemEntity,
                            ];
                            unset($updChunk[$error->request_id]);
                        }
                        continue;
                    }
                    $errs = $amoResponse->get('errors');
                    if(!empty($errs)){
                        if(collect($errs)->every(function($err){
                            return in_array($err, [
                                'Lead not found',
                                'Contact not found',
                                'Company not found',
                            ]);
                        })){
                            foreach($errs as $leadId => $reason){
                                $problemEntity = collect($updChunk)->where('id', $leadId)->first();
                                $unableToUpdate[] = [
                                    'reason' => $reason,
                                    'entity' => $problemEntity,
                                ];
                            }
                            break;
                        }
                    }
                    throw $e;
                }

            } while (empty($response));
        }
        return $unableToUpdate;
    }

    /**
     * @throws Throwable
     */
    private static function updateEntities(array $upd, string $entityType): array
    {
        return self::saveEntitiesLegacy($upd, $entityType);
    }
    private static function createEntities(array $add, string $entityType): array
    {
        return self::saveEntitiesLegacy($add, $entityType, true);
    }

    /**
     * Принимает id кастомного поля (селект или мультиселект) и значение енама
     * возвращает id енама, если найдено такое поле,
     * выбрасывает исключения, если поля с cfId не найдено на аккаунте, или оно не selectable (не имеет enum)
     * параметр $force отвечает за то будем ли брать данные из кэша, или делать запрос к амо
     *
     * @param int $cfId
     * @param string $value
     * @param bool $force
     * @return int|null
     */
    private static function getENUM(int $cfId, string $value, bool $force = false): ?int
    {
        $account = self::getAccount($force);
        $cf = self::getCF($cfId, $account);
        if(empty($cf)) throw new InvalidArgumentException("CF $cfId not found on account");
        $enums = collect($cf->enums ?? []);
        if(empty($enums)) throw new InvalidArgumentException("CF $cfId is not selectable: not found enums for this field");
        return $enums->search($value) ?: null;
    }

    private static function updateMultiSelect($cfId, $value, $recursion = 0) {
        $account = self::getAccount();
        $cf = self::getCF($cfId, $account);
        if(empty($cf)) throw new InvalidArgumentException("CF $cfId not found on account");
        $enums = collect($cf->enums ?? []);
        if(empty($enums)) throw new InvalidArgumentException("CF $cfId is not selectable: not found enums for this field");

        $enums = $enums->map(function($value, $enum){
            return [ 'id' => $enum, 'value' => $value, ];
        })->values()->push([ 'value' => $value ]);

        $cf = [[
            'id' => $cf->id,
            'name' => $cf->name,
            'enums' => $enums->all()
        ]];

        $entityType = collect($account->get('_embedded')->custom_fields ?? [])
            ->filter(function($cfs) use ($cfId){
                return collect($cfs)->has($cfId);
            })->keys()->first();

        try{
            self::request('patch', "/api/v4/$entityType/custom_fields", $cf);
        } catch (\Throwable $e){
            // мы можем получить ошибку валидации от амо:
            // {"validation-errors":[{"request_id":"0","errors":[{"code":"NotSupportedChoice","path":"enums.0.id","detail":"The value you selected is not a valid choice."}]}],"title":"Bad Request","type":"https://httpstatus.es/400","status":400,"detail":"Request validation failed"}
            // потому что данные в кэше не совпадают с тем, что есть в амо
            // (если в амо удалили один из вариантов enum, а в кэше он еще остался. должно быть редкой ситуацией)
            if(!str_contains($e->getMessage(), 'Amo return 400')) throw $e;
            if($recursion > 0) throw $e;
            self::getAccount(true);
            self::updateMultiSelect($cfId, $value, ++$recursion);
        }
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех лидов на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getLeads(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_LEAD);
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех компаний на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getCompanies(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_COMPANY);
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех контактов на аккаунте.
     * context представляет собой массив содержащий параметры запросов. get к амо
     * например filter или with
     *
     * @param array $context
     * @return void
     */
    public static function getContacts(array $context = []): Generator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_CONTACT);
    }
    /**
     * Если вызвать без параметров, то вернет генератор всех задач на аккаунте.
     *  context представляет собой массив содержащий параметры запросов. get к амо
     *  например filter
     *
     * @param array $context
     * @return Iterator
     */
    public function getTasks(array $context = []): Iterator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_TASK);
    }

    /**
     * Если вызвать без параметров, то вернет генератор всех событий на аккаунте.
     *  context представляет собой массив содержащий параметры запросов. get к амо
     *  например filter
     *
     * @param array $context
     * @return Iterator
     */
    public static function getEvents(array $context = []): Iterator
    {
        return self::getAllEntities($context, self::ENTITY_TYPE_EVENT);
    }

    /**
     * Возвращает генератор stdClass объектов которые представляют собой сущность в ответе из амо апи
     *
     * @param array $context
     * @param string $entityType
     * @return Generator
     */
    public static function getAllEntities(array $context, string $entityType): Generator
    {
        $page = 1;
        do{
            $context['page'] = $page++;
            $context['limit'] = $context['limit'] ?? self::ENTITIES_QUERY_LIMIT;
            $amoResponse = self::request('get', "/api/v4/$entityType", $context);
            foreach($amoResponse->_embedded->$entityType ?? [] as $entity){
                $entity = self::clearEntity($entity);
                yield $entity;
            }

        } while($amoResponse->_links->next->href ?? false);
    }

    /**
     * Единственный обязательный парамтер - это текст задачи.
     * Если не передать timestamp срока задачи, то будет взят текущий timestamp
     *
     * @param string $text
     * @param int $completeTill
     * @param int|null $entityId
     * @param string|null $entityType
     * @param int|null $responsible
     * @param int|null $taskType
     * @return void
     */
    public static function addTask(
        string $text,
        int $completeTill = 0,
        ?int $entityId = null,
        ?string $entityType = null,
        ?int $responsible = null,
        ?int $taskType = null
    ){

        $taskData = [
            'text' => $text,
            'complete_till' => max($completeTill, time()),
        ];
        if(!empty($entityId) && !empty($entityType) && in_array($entityType, self::ENTITY_TYPES)){
            $taskData['entity_type'] = $entityType;
            $taskData['entity_id'] = $entityId;
        }
        if(!empty($responsible)) $taskData['responsible_user_id'] = $responsible;
        if(!empty($taskType)) $taskData['task_type_id'] = $taskType;

        self::request('post', '/api/v4/tasks', [$taskData]);

    }

    /**
     * Принимает id поля амо и значение енама, возвращает id енама соответственно
     *
     * @param int|null $cf
     * @param string|null $enumValue
     * @return int|null
     */
    public static function getEnumByName(?int $cf, ?string $enumValue): ?int
    {
        if(empty($cf) || empty($enumValue)) return null;
        return collect(self::getCF($cf, self::getAllCFs())->enums ?? [])->search($enumValue);
    }

    public static function addLeadNote(int $leadId, string $note): void
    {
        self::addNote($leadId, $note, self::ENTITY_TYPE_LEAD);
    }
    public static function addContactNote(int $contactId, string $note): void
    {
        self::addNote($contactId, $note, self::ENTITY_TYPE_CONTACT);
    }
    public static function addCompanyNote(int $companyId, string $note): void
    {
        self::addNote($companyId, $note, self::ENTITY_TYPE_COMPANY);
    }

    /**
     * Не очень универсальная но простая функция, добавляет заметку к сущности
     *
     * @param int $entityID
     * @param string $note
     * @param string $entityType
     * @return void
     */
    private static function addNote(int $entityID, string $note, string $entityType): void
    {
        self::checkType($entityType);
        self::request('post', "/api/v4/$entityType/$entityID/notes", [
            [
                'note_type' => 'common',
                'params' => [
                    'text' => $note,
                ]
            ]
        ]);
    }

    private static function request(string $method, string $uri, array $params = [])
    {
        if(self::$debugMode) dump("$method ".substr($uri, 0, 64));
        return self::amo()->__request($method, $uri, $params);
    }


    /**
     * Принимает коллекцию объектов AmoEntity. Все объекты у которых не установлено id будут созданы
     * и для них будет получен id созданной сущности в амо. Все объекты у которых установлено id будут
     * обновлены в амо. Все сущности обрабатываются пакетно, на каждые 50 сущностей по 1 запросу к амо.
     * может принимать смешанную коллекцию содержащую и AmoLead и AmoContact и AmoCompany
     *
     * todo у нас тут нет никаких оповещений о том что что-то пошло не так
     *
     * @param Collection<AmoEntity> $entities
     * @return void
     */
    public static function saveEntities(Collection $entities): void
    {
        $toUpdate = $entities->filter(function(AmoEntity $entity){ return $entity->getId(); });
        $toCreate = $entities->filter(function(AmoEntity $entity){ return !$entity->getId(); });
        $allTypesOfEntities = [
            'patch' => [
                self::ENTITY_TYPE_LEAD => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoLead; }),
                self::ENTITY_TYPE_CONTACT => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoContact; }),
                self::ENTITY_TYPE_COMPANY => $toUpdate->filter(function(AmoEntity $entity){ return $entity instanceof AmoCompany; })
            ],
            'post' => [
                self::ENTITY_TYPE_LEAD => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoLead; }),
                self::ENTITY_TYPE_CONTACT => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoContact; }),
                self::ENTITY_TYPE_COMPANY => $toCreate->filter(function(AmoEntity $entity){ return $entity instanceof AmoCompany; })
            ]
        ];
        foreach($allTypesOfEntities as $method => $entityTypes){
            foreach($entityTypes as $type => $entities){
                if($entities->isEmpty()) continue;
                $entities = $entities->mapWithKeys(function(AmoEntity $entity){return [$entity->getUniqId() => $entity];});
                $requestData = $entities->map(function(AmoEntity $entity){ return $entity->getUpdateStructureV4(); });
                $requestData->chunk(self::ENTITIES_QUERY_DEFAULT)->each(function(Collection $chunk) use ($method, $entities, $type){
                    $response = self::request($method, "/api/v4/$type", $chunk->all());
                    foreach ($response->_embedded->$type ?? [] as $createdEntity){
                        if($entity = $entities->get($createdEntity->request_id ?? null)) $entity->setId($createdEntity->id);
                    }
                });
            }
        }
    }

    /**
     * Амо возвращает объект наполненный лишенй инфой: например ссылками на самого себя
     * Если запрос сложный содержащий кучу id в filter, и мы еще и получаем несколько страниц,
     * то выходит огромное количество мусорной информации в _links свойстве, которая жрет место а кэше, в оперативке и тд
     *
     * @param ?stdClass $entity
     * @return stdClass
     */
    private static function clearEntity(?stdClass $entity): stdClass
    {
        if(empty($entity)) return new stdClass;
        unset($entity->_links);
        foreach($entity->_embedded ?? [] as $embeddedEntities){
            foreach($embeddedEntities as $embeddedEntity){
                unset($embeddedEntity->_links);
            }
        }
        return $entity;
    }


    /**
     * Возвращает инфо по аккаунту из амо: по возможности берёт данные из кэша
     * (автообновление каждые сутки при запросе)
     * ответ - это структура которую возвращает амо на /api/v2/account?with=custom_fields
     *
     * @param bool $force если true, то не проверяет кэш, а делает запрос к амо
     * @return Collection коллекция из ответа амо на /api/v2/account?with=custom_fields
     */
    public static function getAccount(bool $force = false): Collection
    {
        if(!$force){
            $account = (new self())->getFromCache('account');
            if(!empty($account)) return $account;
        }
        $account = collect(self::request('get', '/api/v2/account', ['with' => 'custom_fields,users,pipelines']));
        (new self())->putToCache('account', $account, 24 * 60);
        return $account;
    }


    /**
     * Возвращает инфо по воронкам из амо: по возможности берёт данные из кэша
     * (автообновление каждые сутки при запросе)
     *
     * @param bool $force если true, то не проверяет кэш, а делает запрос к амо
     * @return Collection коллекция воронок: ответов амо на запрос /api/v4/leads/pipelines ->_embedded->pipelines
     */
    public static function getPipelines(bool $force = false): Collection
    {
        if(!$force){
            $pipelines = (new self())->getFromCache('pipelines');
            if(!empty($pipelines)) return $pipelines;
        }
        $pipelines = collect(self::request('get', '/api/v4/leads/pipelines')->_embedded->pipelines);
        (new self())->putToCache('pipelines', $pipelines, 24 * 60);
        return $pipelines;
    }

    /**
     * Возвращает инфо по пользователям из амо: по возможности берёт данные из кэша
     * (автообновление каждые сутки при запросе)
     *
     * @param bool $force если true, то не проверяет кэш, а делает запрос к амо
     * @return Collection коллекция пользователей: ответов амо на запрос /api/v4/leads/users ->_embedded->users
     */
    public static function getUsers(bool $force = false): Collection
    {
        if(!$force){
            $users = (new self())->getFromCache('users');
            if(!empty($users)) return $users;
        }
        $users = collect(self::request('get', '/api/v4/users')->_embedded->users)->keyBy('id');
        (new self())->putToCache('users', $users, 24 * 60);
        return $users;
    }


    /**
     * Получает список сущностей, связанных с теми, что переданы в аргументе
     *
     * @param iterable $contactsIds
     * @return Iterator
     */
    public static function getLinkedToContacts(iterable $contactsIds): Iterator
    {
        return self::getLinkedToEntities($contactsIds, self::ENTITY_TYPE_CONTACT);
    }
    /**
     * Получает список сущностей, связанных с теми, что переданы в аргументе
     *
     * @param iterable $companiesIds
     * @return Iterator
     */
    public static function getLinkedToCompanies(iterable $companiesIds): Iterator
    {
        return self::getLinkedToEntities($companiesIds, self::ENTITY_TYPE_COMPANY);
    }
    /**
     * Получает список сущностей, связанных с теми, что переданы в аргументе
     *
     * @param iterable $leadsIds
     * @return Iterator
     */
    public static function getLinkedToLeads(iterable $leadsIds): Iterator
    {
        return self::getLinkedToEntities($leadsIds, self::ENTITY_TYPE_LEAD);
    }
    public static function getLinkedToEntities(iterable $entityIds, string $entityType): Iterator
    {
        self::checkType($entityType);
        $entityIds = collect(FORMAT::toIDArray($entityIds));
        foreach($entityIds->chunk(self::ENTITIES_QUERY_DEFAULT) as $entitiesIdsChunk){
            $filter = ['filter' => ['entity_id' => $entitiesIdsChunk->all()]];
            $response = self::amo()->__request('get', "/api/v4/$entityType/links", $filter);
            foreach($response->_embedded->links as $link){
                yield $link;
            }
        }
    }

    public static function checkType(string $entityType)
    {
        if(!in_array($entityType, self::ENTITY_TYPES))
            throw new \InvalidArgumentException("Received unsupported entity type: $entityType");
    }

    public static function linkContacts(iterable $links): void
    {
        self::linkEntities($links, self::ENTITY_TYPE_CONTACT);
    }
    public static function linkCompanies(iterable $links): void
    {
        self::linkEntities($links, self::ENTITY_TYPE_COMPANY);
    }
    public static function linkLeads(iterable $links): void
    {
        self::linkEntities($links, self::ENTITY_TYPE_LEAD);
    }

    private static function linkEntities(iterable $links, string $entityType): void
    {
        self::checkType($entityType);
        foreach(collect($links)->chunk(self::ENTITIES_QUERY_DEFAULT) as $linksChunk){
            self::amo()->__request('post', "/api/v4/$entityType/link", $linksChunk->all());
        }
    }


    public static function unlinkContacts(iterable $links): void
    {
        self::unlinkEntities($links, self::ENTITY_TYPE_CONTACT);
    }
    public static function unlinkCompanies(iterable $links): void
    {
        self::unlinkEntities($links, self::ENTITY_TYPE_COMPANY);
    }
    public static function unlinkLeads(iterable $links): void
    {
        self::unlinkEntities($links, self::ENTITY_TYPE_LEAD);
    }

    private static function unlinkEntities(iterable $links, string $entityType): void
    {
        self::checkType($entityType);
        foreach(collect($links)->chunk(self::ENTITIES_QUERY_DEFAULT) as $linksChunk){
            self::amo()->__request('post', "/api/v4/$entityType/unlink", $linksChunk->all());
        }
    }

}
