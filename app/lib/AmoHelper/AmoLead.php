<?php

namespace App\lib\AmoHelper;

use App\lib\AmoMapping\AmoMapping;
use App\lib\AmoMapping\samolet\GENERAL_CONFIG;
use App\lib\BusinessLogic\Interest;
use App\lib\BusinessLogic\JK;
use App\lib\BusinessLogic\Lot;
use App\lib\BusinessLogic\Podbor;
use App\lib\FORMAT;
use DateTime;
use stdClass;

class AmoLead extends AmoEntity
{
    protected ?AmoContact $mainContact;
    protected ?int $price;
    protected ?int $statusId;
    protected ?int $pipelineId;
    protected ?int $lossReasonId;
    protected ?DateTime $closedAt;
    protected ?int $score;
    protected ?int $laborCost;
    protected string $entityType = AmoHelper::ENTITY_TYPE_LEAD;
    protected ?int $sourceId;

    /**
     * Дополняет родительский метод получения структуры для обновления уникальными для лида полями
     *
     * @return array
     */
    public function getUpdateStructureV4(): array
    {
        $structure = parent::getUpdateStructureV4();
        return array_filter(array_merge($structure, [
            'price' => $this->getPrice(),
            'status_id' => $this->getStatusId(),
            'pipeline_id' => $this->getPipelineId(),
//            'closed_at' => FORMAT::timestamp($this->getClosedAt()),
//            'loss_reason_id' => $this->getLossReasonId(),
        ]), function($field){ return !empty($field); });
    }

    public function __construct(?stdClass $lead = null)
    {
        parent::__construct($lead);
        $this->price = (int)($lead->price ?? 0) ?: null;
        $this->statusId = (int)($lead->status_id ?? 0) ?: null;
        $this->pipelineId = (int)($lead->pipeline_id ?? 0) ?: null;
        $this->lossReasonId = (int)($lead->loss_reason_id ?? 0) ?: null;
        $this->closedAt = FORMAT::DateTime($lead->closed_at ?? null, 'UTC');
        $this->score = (int)($lead->score ?? 0) ?: null;
        $this->laborCost = (int)($lead->labor_cost ?? 0) ?: null;
        $this->sourceId = (int)($lead->source_id ?? 0) ?: null;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId ?? null;
    }

    public function getMainContact(): ?AmoContact
    {
        return $this->mainContact ?? null;
    }

    /**
     * Возвращает id основного контакта. Проверяет исключительно по данным из амо.
     * т.е. если изменить в объекте setMainContact то id возвращаемое этой функцией не обновится
     *
     * @return int|null
     */
    public function getMainContactId(): ?int
    {
        return (int)(collect($this->getEmbedded()->contacts ?? null)
            ->where('is_main', '=', true)->first()->id ?? 0) ?: null;
    }

    public function setMainContact(AmoContact $contact): self
    {
        $this->mainContact = $contact;
        return $this;
    }

    public function setStatus(?int $status): self
    {
        $this->statusId = $status;
        return $this;
    }

    public function switchPipelineToSamolet(): self
    {
        AmoMapping::switchPipelineToSamolet($this);
        return $this;
    }

    public function setPipeline(?int $pipeline): self
    {
        $this->pipelineId = $pipeline;
        return $this;
    }

    public function interest(): Interest { return new Interest($this); }
    public function podbor(): Podbor { return new Podbor($this); }
    public function jk(): JK { return new JK($this); }
    public function lot(): Lot { return new Lot($this); }


    // GETTERS

    public function getPrice(): ?int{ return $this->price; }
    public function getStatusId(): ?int { return $this->statusId; }
    public function getPipelineId(): ?int { return $this->pipelineId; }
    public function getLossReasonId(): ?int { return $this->lossReasonId; }
    public function getClosedAt(): ?DateTime { return $this->closedAt; }
    public function getScore(): ?int { return $this->score; }
    public function getLaborCost(): ?int { return $this->laborCost; }
}
