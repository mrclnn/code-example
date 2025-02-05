<?php

namespace App\lib\AmoHelper;

use App\lib\AmoMapping\AmoMapping;
use App\lib\AmoMapping\samolet\GENERAL_CONFIG;
use App\lib\FORMAT;
use DateTime;
use Illuminate\Support\Collection;
use stdClass;
use Throwable;

abstract class AmoEntity
{
    protected ?int $id;
    protected ?string $name;
    protected ?int $responsibleUserId;
    protected ?int $groupId;
    protected ?int $createdBy;
    protected ?int $updatedBy;
    protected ?DateTime $createdAt;
    protected ?DateTime $updatedAt;
    protected ?DateTime $closestTaskAt;
    protected ?bool $isDeleted;
    /**  @var AmoCF[] */
    protected array $CFList;
    protected ?int $accountId;
    protected ?stdClass $embedded;
    protected string $json;
    protected string $entityType;
    /**
     * это уникальный id именно объекта. используется при создании / обновлении отправляется как request_id
     * чтобы соотнести с ответом
     * @var string
     */
    protected string $uniqid;

    protected Collection $linkedTags;
    protected Collection $linkedContacts;
    protected Collection $linkedCompanies;
    protected Collection $linkedLeads;
    public function __construct(?stdClass $entity = null)
    {
        $entity = $this->clearEntity($entity);
        $this->json = json_encode($entity);
        $this->uniqid = uniqid('entity_', true);

        $this->id = (int)($entity->id ?? 0) ?: null;
        $this->name = $entity->name ?? null;
        $this->responsibleUserId = (int)($entity->responsible_user_id ?? 0) ?: null;
        $this->groupId = (int)($entity->group_id ?? 0) ?: null;
        $this->createdBy = (int)($entity->created_by ?? 0) ?: null;
        $this->updatedBy = (int)($entity->updated_by ?? 0) ?: null;
        $this->createdAt = FORMAT::DateTime($entity->created_at ?? null, 'UTC');
        $this->updatedAt = FORMAT::DateTime($entity->updated_at ?? null, 'UTC');
        $this->closestTaskAt = FORMAT::DateTime($entity->closest_task_at ?? null, 'UTC');
        $this->isDeleted = (bool)($entity->is_deleted ?? false);
        $this->CFList = array_map(function($cf){ return new AmoCF($cf); }, $entity->custom_fields_values ?? []);
        $this->accountId = (int)($entity->account_id ?? 0) ?: null;

        $this->embedded = $entity->_embedded ?? null;
        $this->linkedTags = collect($entity->_embedded->tags ?? null)->pluck('id')->filter();
        $this->linkedContacts = collect($entity->_embedded->contacts ?? null)->pluck('id')->filter();
        $this->linkedCompanies = collect($entity->_embedded->companies ?? null)->pluck('id')->filter();
        $this->linkedLeads = collect($entity->_embedded->leads ?? null)->pluck('id')->filter();
    }

    /**
     * Амо возвращает объект наполненный лишенй инфой: например ссылками на самого себя
     * Если запрос сложный содержащий кучу id в filter, и мы еще и получаем несколько страниц,
     * то выходит огромное количество мусорной информации в _links свойстве, которая жрет место а кэше, в оперативке и тд
     *
     * @param ?stdClass $entity
     * @return stdClass
     */
    private function clearEntity(?stdClass $entity): stdClass
    {
        if(empty($entity)) return new stdClass;
        if(isset($entity->_links)) unset($entity->_links);
        foreach($entity->_embedded ?? [] as $embeddedEntities){
            foreach($embeddedEntities as $embeddedEntity){
                if(isset($entity->_links)) unset($embeddedEntity->_links);
            }
        }
        return $entity;
    }

    public function stdClass(): stdClass
    {
        return json_decode($this->getJSON());
    }

    /**
     * Этот метод посылает запрос в амо на создание / редактирование сущности !
     * Если у сущности заполнено поле id то обновляет сущность в амо
     * Если поле id пустое - создает
     * Работает на подобие save ларавель модели
     *
     * @return self
     * @throws Throwable
     */
    public function save(): self
    {
        $this->getId() ? AmoHelper::updateEntity($this) : AmoHelper::createEntity($this);
        return $this;
    }

    public function switchResponsible(): self
    {
        if(!AmoHelper::issetUser($this->getResponsibleUserId())) $this->setResponsible(null);
        return $this;
    }

    public function getUniqId(): string { return $this->uniqid; }

    public function unlinkTags(): self { $this->linkedTags = new Collection(); return $this;}
    public function unlinkCompanies(): self { $this->linkedCompanies = new Collection(); return $this;}
    public function unlinkContacts(): self { $this->linkedContacts = new Collection(); return $this;}

    public function addLinkedTag(?int $id): self {
        if(!is_null($id)) $this->getLinkedTags()->push($id)->unique();
        return $this;
    }
    public function addLinkedCompany(?int $id): self {
        if(!is_null($id)) $this->getLinkedCompanies()->push($id)->unique();
        return $this;
    }
    public function addLinkedContact(?int $id): self {
        if(!is_null($id)) $this->getLinkedContacts()->push($id)->unique();
        return $this;
    }

    public function getLinkedTags() :Collection { return $this->linkedTags ?? $this->linkedTags = new Collection(); }
    public function getLinkedCompanies() :Collection { return $this->linkedCompanies ?? $this->linkedCompanies = new Collection(); }
    public function getLinkedContacts() :Collection { return $this->linkedContacts ?? $this->linkedContacts = new Collection(); }
    public function getLinkedLeads() :Collection { return $this->linkedLeads ?? $this->linkedLeads = new Collection(); }

    public function setResponsible(?int $id): self
    {
        if($id && !AmoHelper::issetUser($id)) throw new \InvalidArgumentException("User $id not found on amo account");
        $this->responsibleUserId = $id;
        return $this;
    }

    public function switchCFToSamolet(): self
    {
        //todo вообще-то по идее этому методу тут не совсем место, т.к. он привязан к бизнесс логике.
        // но с другой стороны в php нет множественного наследования, по этому мы вынуждены абстрагировать метод сюда
        AmoMapping::switchCFToSamolet($this);
        return $this;
    }

    public function refreshId(): self { $this->id = null; return $this; }
    public function setId(int $id): self { $this->id = $id; return $this; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /**
     * если передать '123441' это будет считать как code, а не как id
     *
     * @param $cfId
     * @return AmoCF|null
     */
    public function getCF($cfId): ?AmoCF
    {
        //todo сделать поиск по index массива custom fields. но тогда придется изменить структуру custom fields
        return current(array_filter($this->getCFList(), function($cf) use ($cfId){
            return $cf->getId() === $cfId || $cf->getCode() === $cfId;
        })) ?: null;
    }

    /**
     * Пока нельзя делать этот метод открытым, потому что много подводных с разными типами полей.
     * Нужно создать более мощную инфраструктуру перед этим
     *
     * @param $cfId
     * @param $value
     * @param null $enum
     * @return void
     */
    protected function setCF($cfId, $value, $enum = null): void
    {
        $cf = $this->getCF($cfId);
        if(isset($cf)) {
            $cf->setValue($value);
            return;
        }
        $cf = (object)[
            'field_id' => $cfId,
            'type' => !is_array($value) ? 'text' : 'multiselect',
            'values' => !is_array($value) ? [['value' => $value]] : (array)$value,
        ];

        if($enum) $cf->values[0]->enum_id = $enum;
        $cf = new AmoCF($cf);
        $cfList = $this->getCFList();
        $cfList[] = $cf;
        $this->setCFList($cfList);
    }

    protected function setCFEnums($cfId, $enums)
    {
        $enums = collect(FORMAT::toIDArray(collect($enums)))->map(fn(int $enum) => ['enum_id' => $enum])->all();
        $this->setCF($cfId, $enums);
    }

    public function deleteCF(int $cfId): self
    {
        $this->setCFList(array_filter($this->getCFList(), function(AmoCF $cf) use ($cfId){
            return $cf->getId() !== $cfId;
        }));
        return $this;
    }

    private function setCFList(array $cfList): void
    {
        $cfList = array_filter($cfList, function($cf){ return $cf instanceof AmoCF; });
        $this->CFList = $cfList;
    }
    public function getCFV($cfId)
    {
        return $this->getCF($cfId) ? $this->getCF($cfId)->getValue() : null;
    }
    public function empty(): bool
    {
        return $this->getJSON() === '{}';
    }

    /**
     * Возвращает строковое представление типа сущности амо: leads / contacts / companies
     * Идентично константам в AmoHelper
     *
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function getResponsibleUserId(): ?int { return $this->responsibleUserId; }
    public function getGroupId(): ?int { return $this->groupId; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getUpdatedBy(): ?int{ return $this->updatedBy; }
    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function getClosestTaskAt(): ?DateTime { return $this->closestTaskAt; }
    public function getIsDeleted(): bool { return $this->isDeleted; }
    /** @return AmoCF[] */
    public function getCFList(): array { return $this->CFList; }
    public function getAccountId(): ?int { return $this->accountId; }
    public function getEmbedded(): ?stdClass { return $this->embedded; }
    public function getJSON(): string { return $this->json; }

    /**
     * Возвращает структуру для вставки / обновления сущности запросом в amo
     * Массив не содержит пустых значений
     *
     * @return array
     */
    public function getUpdateStructureV4(): array
    {
        return array_filter([
            'request_id' => $this->getUniqId(),
            'id' => $this->getId(),
            'name' => $this->getName(),
            'responsible_user_id' => $this->getResponsibleUserId(),
//            'created_by' => $this->getCreatedBy(),
//            'updated_by' => $this->getUpdatedBy(),
//            'created_at' => $this->getCreatedAt(),
//            'updated_at' => $this->getUpdatedAt(),
            'custom_fields_values' => $this->getUpdateStructureV4CF(),
            '_embedded' => [
                'tags' => $this->getLinkedTags()->map(function(int $id){ return ['id' => $id]; })->all(),
                'contacts' => $this->getLinkedContacts()->map(function(int $id){ return ['id' => $id]; })->all(),
                'companies' => $this->getLinkedCompanies()->map(function(int $id){ return ['id' => $id]; })->all(),
            ]
        ], function($field){ return !empty($field); });
    }

    private function getUpdateStructureV4CF(): array
    {
        return array_filter(array_map(function(AmoCF $cf){
            return $cf->getV4Structure();
        }, $this->getCFList()), function($field){ return !empty($field); });
    }

    public function __clone()
    {
        $this->CFList = array_map(function(AmoCF $cf){ return clone $cf; }, $this->CFList);
    }
}
