<?php

namespace App\lib\MirrorHelper;

use App\lib\AmoHelper\AmoCF;
use App\lib\AmoHelper\AmoHelper;
use App\lib\AmoHelper\AmoLead;
use App\MirrorCfs;
use App\MirrorCompanies;
use App\MirrorContacts;
use App\MirrorLeads;
use App\MirrorModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Iterator;

/**
 *
 */
class MirrorHelper
{

    public static function getEntitiesMirrorLastUpdate(string $entityType): int
    {
        AmoHelper::checkType($entityType);
        if($entityType === AmoHelper::ENTITY_TYPE_LEAD) return MirrorLeads::getLastUpdatedTimestamp();
        if($entityType === AmoHelper::ENTITY_TYPE_CONTACT) return MirrorContacts::getLastUpdatedTimestamp();
        if($entityType === AmoHelper::ENTITY_TYPE_COMPANY) return MirrorCompanies::getLastUpdatedTimestamp();
        throw new \InvalidArgumentException("Received unsupported entity type: $entityType");
    }

    public static function getLeadsMirrorStatus()
    {
        //todo статус обновлено, статус в процессе обновления и пр.
    }

    public static function getLeadsMirrorLastUpdate(): int
    {
        return self::getEntitiesMirrorLastUpdate(AmoHelper::ENTITY_TYPE_LEAD);
    }

    public static function getContactsMirrorStatus()
    {

    }

    public static function getContactsMirrorLastUpdate(): int
    {
        return self::getEntitiesMirrorLastUpdate(AmoHelper::ENTITY_TYPE_CONTACT);
    }

    public static function getCompaniesMirrorStatus()
    {

    }

    public static function getCompaniesMirrorLastUpdate(): int
    {
        return self::getEntitiesMirrorLastUpdate(AmoHelper::ENTITY_TYPE_COMPANY);
    }


    public static function getLeadsByIds(iterable $ids): Iterator
    {
        return self::getEntitiesByIds($ids, AmoHelper::ENTITY_TYPE_LEAD);
    }
    public static function getContactsByIds(iterable $ids): Iterator
    {
        return self::getEntitiesByIds($ids, AmoHelper::ENTITY_TYPE_CONTACT);
    }
    public static function getCompaniesByIds(iterable $ids): Iterator
    {
        return self::getEntitiesByIds($ids, AmoHelper::ENTITY_TYPE_COMPANY);
    }

    /**
     * Такая "сложная" структура нужна для максимально плавной обработки.
     * Предположим что мы получаем генератор id на входе и отдаем генератор же на выходе
     * Чтобы не ждать сборки всего генератора мы будем отдавать готовые результаты каждые 500 элементов в исходном массиве
     *
     * Если на входе имеем массив из 20 $ids то решение переусложнено
     * Но если на входе у нас будет тяжеловесный генератор на десятки тысяч значений - такое решение позволит начинать обработку
     * сразу после получения первых 500 записей, а не пока мы сгенерируем все десятки тысяч id.
     *
     * @param iterable $ids
     * @param string $entityType
     * @return Iterator<self>
     */
    public static function getEntitiesByIds(iterable $ids, string $entityType): Iterator
    {
        AmoHelper::checkType($entityType);
        $entityBuilders = [
            AmoHelper::ENTITY_TYPE_LEAD => MirrorLeads::query(),
            AmoHelper::ENTITY_TYPE_CONTACT => MirrorContacts::query(),
            AmoHelper::ENTITY_TYPE_COMPANY => MirrorCompanies::query(),
        ];
        $builder = $entityBuilders[$entityType];
        if(! $builder instanceof Builder)
            throw new InvalidArgumentException('Unable to get mirror entities builder');

        $idsChunk = [];
        foreach($ids as $index => $id){
            $idsChunk[] = $id;
            if($index % 500 === 0){
                foreach($builder->whereIntegerInRaw('id', $idsChunk)->get() as $mirrorEntity) yield $mirrorEntity;
                $idsChunk = [];
            }
        }
        if(!empty($idsChunk)){
            foreach($builder->whereIntegerInRaw('id', $idsChunk)->get() as $mirrorEntity) yield $mirrorEntity;
        }
    }

    public static function getCompanyById(int $id): ?MirrorCompanies
    {
        return MirrorCompanies::find($id);
    }

//    public static function AmoLeadToMirrorLead(AmoLead $amoLead, ?MirrorLeads $mirrorLead = null): MirrorLeads
//    {
//        //todo проверки на обязательные поля. мы не должны допускать создания модели которую потом нельзя будет сохранить
//        // потому что база будет ругаться на пустые поля там где должно быть not null
//        $mirrorLead = $mirrorLead ?? new MirrorLeads();
//        $mirrorLead->id = $amoLead->getId();
//        $mirrorLead->name = $amoLead->getName();
//        $mirrorLead->price = $amoLead->getPrice();
//        $mirrorLead->created_at = $amoLead->getCreatedAt() ? $amoLead->getCreatedAt()->format('Y-m-d H:i:s') : null;
//        $mirrorLead->updated_at = $amoLead->getUpdatedAt() ? $amoLead->getUpdatedAt()->format('Y-m-d H:i:s') : null;
//        $mirrorLead->closed_at = $amoLead->getClosedAt() ? $amoLead->getClosedAt()->format('Y-m-d H:i:s') : null;
//        $mirrorLead->closest_task_at = $amoLead->getClosestTaskAt() ? $amoLead->getClosestTaskAt()->format('Y-m-d H:i:s') : null;
//
//        $mirrorLead->status_id = $amoLead->getStatusId();
//        $mirrorLead->pipeline_id = $amoLead->getPipelineId();
//        $mirrorLead->responsible_user_id = $amoLead->getResponsibleUserId();
//        $mirrorLead->created_by = $amoLead->getCreatedBy();
//        $mirrorLead->updated_by = $amoLead->getUpdatedBy();
//        $mirrorLead->loss_reason_id = $amoLead->getLossReasonId();
//
//        $amoCFs = collect($amoLead->getCFList());
//        $mirrorCfs = $mirrorLead->cfs;
//        $mirrorCfsUpdated = self::amoCfsToMirrorCfs($amoCFs, $mirrorCfs);
//
//
//
//        return $mirrorLead;
//    }

//    /**
//     * @param Collection<AmoCF> $amoCFs
//     * @param Collection<MirrorCfs> $mirrorCFs
//     * @return Collection<MirrorCfs>
//     */
//    private static function amoCfsToMirrorCfs(Collection $amoCFs, Collection $mirrorCFs): Collection
//    {
//        //todo необходимо сравнить две коллекции, если в amoCF нет каких-то полей которые есть в mirrorCF
//        // то в зеркале нужно будет удалить ненужные поля
//        // с другой стороны это метод преобразования и он не должен трогать базу воообще
//
//        $amoCFs = $amoCFs->mapWithKeys(function(AmoCF $cf){ return [$cf->getId() => $cf]; });
//        $mirrorCFs = $mirrorCFs->keyBy('cf_id');
//
//        // нужно удалить из амо те которых нет
//
////        /** @var AmoCF $amoCF */
////        foreach($amoCFs as $amoCF){
//////            $amoCF->getId()
////        }
//
//    }



}
