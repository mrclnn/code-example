<?php

namespace App\Console\Commands;

use App\lib\AmoHelper\AmoHelper;
use App\lib\FORMAT;
use App\lib\MirrorHelper\MirrorHelper;
use App\MirrorCompanies;
use App\MirrorCompaniesCfs;
use App\MirrorContacts;
use App\MirrorContactsCfs;
use App\MirrorLeads;
use App\MirrorLeadsCfs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class MirrorEntitiesSync extends CronCommand
{
    protected $signature = 'mirror:companies_sync';
    protected $description = 'Синхронизирует ';
    const CHUNK_TO_INSERT_IN_DB_LIMIT = 500; //todo кайнда бесполезно
    const INSERT_CHUNK_LIMIT = 10000;
    const WHERE_IN_CHUNK_LIMIT = 5000;
    public function handler()
    {

        $beginTime = microtime(true);

        AmoHelper::setDebugMode(true);
        $this->retrieveCompanies();
//        $this->retrieveLeads();
//        $this->retrieveContacts();




        dump('end time: '. round(microtime(true) - $beginTime, 4));
    }

    private function retrieveContacts()
    {
        $lastUpdated = MirrorHelper::getContactsMirrorLastUpdate();
        $entitiesIterator = AmoHelper::getContacts([
            'filter' => ['updated_at' => ['from' => ++$lastUpdated]],
            'with' => 'companies,contacts,tags',
        ]);
        $entitiesChunkSize = min(AmoHelper::ENTITIES_QUERY_LIMIT * 5, self::CHUNK_TO_INSERT_IN_DB_LIMIT);
        $entitiesChunk = new Collection();
        foreach($entitiesIterator as $entity){
            $entitiesChunk->put($entity->id, $entity);
            if($entitiesChunk->count() === $entitiesChunkSize){
                dump("Receiving $entitiesChunkSize contacts. try to write to db");
                $writeBegin = microtime(true);
                $this->syncContactsToMirror($entitiesChunk);
                dump("to write $entitiesChunkSize contacts to db seconds: ". round(microtime(true) - $writeBegin, 4));
                $entitiesChunk = new Collection();
            }
        }

        $this->syncContactsToMirror($entitiesChunk);

    }

// todo если руки дойдут
//
//    private function retrieveEntities(string $entityType)
//    {
//        AmoHelper::checkType($entityType);
//        $lastUpdated = MirrorHelper::getEntitiesMirrorLastUpdate($entityType);
//        $entitiesIterator = AmoHelper::getAllEntities([
//            'filter' => ['updated_at' => ['from' => ++$lastUpdated]],
//            'with' => 'leads,companies,contacts,tags',
//        ], $entityType);
//
//    }


    private function retrieveLeads()
    {
        $lastUpdated = MirrorHelper::getLeadsMirrorLastUpdate();
        $entitiesIterator = AmoHelper::getLeads([
            'filter' => ['updated_at' => ['from' => ++$lastUpdated]],
            'with' => 'companies,contacts,tags',
        ]);
        $entitiesChunkSize = min(AmoHelper::ENTITIES_QUERY_LIMIT * 5, self::CHUNK_TO_INSERT_IN_DB_LIMIT);
        $entitiesChunk = new Collection();
        foreach($entitiesIterator as $entity){
            $entitiesChunk->put($entity->id, $entity);
            if($entitiesChunk->count() === $entitiesChunkSize){
                dump("Receiving $entitiesChunkSize leads. try to write to db");
                $writeBegin = microtime(true);
                $this->syncLeadsToMirror($entitiesChunk);
                dump("to write $entitiesChunkSize leads to db seconds: ". round(microtime(true) - $writeBegin, 4));
                $entitiesChunk = new Collection();
            }
        }

        $this->syncLeadsToMirror($entitiesChunk);

    }

    private function retrieveCompanies()
    {
        // timestamp в секундах самого свежего updated_at из зеркала
        $lastUpdated = MirrorHelper::getCompaniesMirrorLastUpdate();
        // фильтр from работает от даты ВКЛЮЧИТЕЛЬНО
        // т.е. по фильтру updated_at[from]=123 мы получим все сделки у которых updated_at=123 и более новые,
        // а не updated_at=124 и более новые

        //todo мы теряем изменения. предположим что запросили у амо за timestamp 123 она нам вернула 5 лидов измененных в 123 таймстемп
        // позже в амо произошло еще 2 изменения в 123 таймстемп которые мы уже не получим.
        // надо брать тот же таймстемп и через базу убирать повторы которые не нужно обновлять повторно.
        $companiesIterator = AmoHelper::getCompanies([
            'filter' => ['updated_at' => ['from' => ++$lastUpdated]],
            'with' => 'leads,contacts,tags',
        ]);

        //todo потестить что лучше, но ясно что должно быть кратно количеству запросов к амо.
        // чем больше число тем быстрее идет синхронизация при огромном количестве данных,
        // но тем труднее базе переварить такой объем.
        // 2500 это потолок потому что больше база не переварит или не целесообразно,
        // это нужно если изменится entities_query_limit в бОльшую сторону и мы будем к этому не готовы
        $companiesChunkSize = min(AmoHelper::ENTITIES_QUERY_LIMIT * 5, self::CHUNK_TO_INSERT_IN_DB_LIMIT);
        $leadsChunk = new Collection();

        //todo нужно записать stdObject в зеркало
        // запрашиваем

        // todo 1 запрос всех моделей по id
        // todo 2 сравнение (заполнение всех полей полями из запроса. проверять на isClean не обязательно если позже будем использовать метод save, т.к. он все равно не дёргает лишний раз базу данных)
        // todo 3 тех моделей которых нет просто создаем и тоже save

        foreach($companiesIterator as $company){
            $leadsChunk->put($company->id, $company);
            if($leadsChunk->count() === $companiesChunkSize){
                dump("Receiving $companiesChunkSize companies. try to write to db");
                $writeBegin = microtime(true);
                $this->syncCompaniesToMirror($leadsChunk);
                dump("to write $companiesChunkSize companies to db seconds: ". round(microtime(true) - $writeBegin, 4));
                $leadsChunk = new Collection();
//                die('test die');
            }
        }

        $this->syncCompaniesToMirror($leadsChunk);
    }

    private function syncCompaniesToMirror(Collection $companiesAmo)
    {

        $companiesAmoIds = FORMAT::toIDArray($companiesAmo->keys());

        $companiesMirror = collect(MirrorHelper::getCompaniesByIds($companiesAmoIds))->keyBy('id');
        $companiesToInsert = $companiesAmo->diffKeys($companiesMirror); // добавляем в зеркале все компании которые не найдены в зеркале
        $companiesToUpdate = $companiesAmo->only($companiesMirror->keys()); // обновляем только те компании которые найдены в зеркале

        $this->insertNewCompaniesToMirror($companiesToInsert);
        $this->updateOldCompaniesToMirror($companiesToUpdate, $companiesMirror);

    }

    private function syncContactsToMirror(Collection $contactsAmo)
    {

        $contactsAmoIds = FORMAT::toIDArray($contactsAmo->keys());

        $contactsMirror = collect(MirrorHelper::getContactsByIds($contactsAmoIds))->keyBy('id');
        $contactsToInsert = $contactsAmo->diffKeys($contactsMirror); // добавляем в зеркале все компании которые не найдены в зеркале
        $contactsToUpdate = $contactsAmo->only($contactsMirror->keys()); // обновляем только те компании которые найдены в зеркале

        $this->insertNewContactsToMirror($contactsToInsert);
        $this->updateOldContactsToMirror($contactsToUpdate, $contactsMirror);

    }
    private function syncLeadsToMirror(Collection $leadsAmo)
    {

        $leadsAmoIds = FORMAT::toIDArray($leadsAmo->keys());

        $leadsMirror = collect(MirrorHelper::getLeadsByIds($leadsAmoIds))->keyBy('id');
        $leadsToInsert = $leadsAmo->diffKeys($leadsMirror); // добавляем в зеркале все компании которые не найдены в зеркале
        $leadsToUpdate = $leadsAmo->only($leadsMirror->keys()); // обновляем только те компании которые найдены в зеркале

        $this->insertNewLeadsToMirror($leadsToInsert);
        $this->updateOldLeadsToMirror($leadsToUpdate, $leadsMirror);
        $this->prepareLeadsLinks($leadsAmo);

    }

    private function prepareLeadsLinks(Collection $leadsAmo): array
    {
        $links = [];
        foreach($leadsAmo as $leadAmo){
            foreach(collect($leadAmo->_embedded->contacts)->pluck('id') as $contactId){
                $links[] = ['lead_id' => $leadAmo->id, 'contact_id' => $contactId];
            }
        }
        return $links;
    }

    private function prepareContactsLinks(Collection $contactsAmo): array
    {
        $links = [];
        foreach($contactsAmo as $contactAmo){
            foreach(collect($contactAmo->_embedded->companies)->pluck('id') as $companyId){
                $links[] = ['contact_id' => $contactAmo->id, 'company_id' => $companyId];
            }
        }
        return $links;
    }

    private function updateOldContactsToMirror(Collection $contactsAmo, Collection $contactsMirror)
    {
        $insertContactsCfs = new Collection();
        $deleteContactsCfs = new Collection();

        /** @var stdClass $contactsAmo */
        foreach($contactsAmo as $contactAmo){

            $newCfs = collect($this->entityCFsToMirrorCFs($contactAmo))->keyBy(function(array $cf){
                return $cf['cf_id'].$cf['enum_id'];
            });

            $oldCfs = $contactsMirror->get($contactAmo->id)->cfs->keyBy(function(MirrorLeadsCfs $cf){
                return $cf->cf_id.$cf->enum_id;
            });

            $cfsToInsert = $newCfs->diffKeys($oldCfs); // если в амо но нет в зеркале. надо добавить в зеркало, в амо они добавлены
            $insertContactsCfs = $insertContactsCfs->merge($cfsToInsert);

            $cfsToDelete = $oldCfs->diffKeys($newCfs); // есть в зеркале но нет в амо. надо убрать из зеркала, в амо они удалены
            $deleteContactsCfs = $deleteContactsCfs->merge($cfsToDelete);

            $cfsToUpdate = $newCfs->only($oldCfs->keys());
            foreach($cfsToUpdate as $key => $cfToUpdate){
                // если изменений не было то дёргать базу не будет, это под капотом у save зашито
                $oldCfs->get($key)->fill($cfToUpdate)->save();
            }

            $mirrorContact = $contactsMirror->get($contactAmo->id);
            $updateContact = $this->contactToMirrorContact($contactAmo, $mirrorContact);
            // если изменений не было то дёргать базу не будет. хотя изменения будут всегда т.к. поле updated_at
            $updateContact->save();
        }

        DB::transaction(function() use ($insertContactsCfs, $deleteContactsCfs){

            $deleteContactsCfsIds = collect(FORMAT::toIDArray($deleteContactsCfs->pluck('id')));
            $deleteContactsCfsIds->chunk(self::WHERE_IN_CHUNK_LIMIT)->each(function($idsChunk){
                dump('delete query limit: '.self::WHERE_IN_CHUNK_LIMIT);
                MirrorContactsCfs::query()->whereIntegerInRaw('id', $idsChunk->all())->delete();
            });
            $insertContactsCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorContactsCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('updated successfully');
    }

    private function updateOldLeadsToMirror(Collection $leadsAmo, Collection $leadsMirror)
    {
        $insertLeadsCfs = new Collection();
        $deleteLeadsCfs = new Collection();

        /** @var stdClass $companyAmo */
        foreach($leadsAmo as $leadAmo){

            $newCfs = collect($this->entityCFsToMirrorCFs($leadAmo))->keyBy(function(array $cf){
                return $cf['cf_id'].$cf['enum_id'];
            });

            $oldCfs = $leadsMirror->get($leadAmo->id)->cfs->keyBy(function(MirrorLeadsCfs $cf){
                return $cf->cf_id.$cf->enum_id;
            });

            $cfsToInsert = $newCfs->diffKeys($oldCfs); // если в амо но нет в зеркале. надо добавить в зеркало, в амо они добавлены
            $insertLeadsCfs = $insertLeadsCfs->merge($cfsToInsert);

            $cfsToDelete = $oldCfs->diffKeys($newCfs); // есть в зеркале но нет в амо. надо убрать из зеркала, в амо они удалены
            $deleteLeadsCfs = $deleteLeadsCfs->merge($cfsToDelete);

            $cfsToUpdate = $newCfs->only($oldCfs->keys());
            foreach($cfsToUpdate as $key => $cfToUpdate){
                // если изменений не было то дёргать базу не будет, это под капотом у save зашито
                $oldCfs->get($key)->fill($cfToUpdate)->save();
            }

            $mirrorLead = $leadsMirror->get($leadAmo->id);
            $updateLead = $this->leadToMirrorLead($leadAmo, $mirrorLead);
            // если изменений не было то дёргать базу не будет. хотя изменения будут всегда т.к. поле updated_at
            $updateLead->save();
        }

        DB::transaction(function() use ($insertLeadsCfs, $deleteLeadsCfs){

            $deleteCompaniesCfsIds = collect(FORMAT::toIDArray($deleteLeadsCfs->pluck('id')));
            $deleteCompaniesCfsIds->chunk(self::WHERE_IN_CHUNK_LIMIT)->each(function($idsChunk){
                MirrorCompaniesCfs::query()->whereIntegerInRaw('id', $idsChunk->all())->delete();
            });
            $insertLeadsCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorCompaniesCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('updated successfully');
//        dump('companies to update: ');
//        dump($companiesToInsert);
    }

    private function updateOldCompaniesToMirror(Collection $companiesAmo, Collection $companiesMirror)
    {
        $insertCompaniesCfs = new Collection();
        $deleteCompaniesCfs = new Collection();

        /** @var stdClass $companyAmo */
        foreach($companiesAmo as $companyAmo){

            $newCfs = collect($this->entityCFsToMirrorCFs($companyAmo))->keyBy(function(array $cf){
                return $cf['cf_id'].$cf['enum_id'];
            });

            $oldCfs = $companiesMirror->get($companyAmo->id)->cfs->keyBy(function(MirrorCompaniesCfs $cf){
                return $cf->cf_id.$cf->enum_id;
            });

            $cfsToInsert = $newCfs->diffKeys($oldCfs); // если в амо но нет в зеркале. надо добавить в зеркало, в амо они добавлены
            $insertCompaniesCfs = $insertCompaniesCfs->merge($cfsToInsert);

            $cfsToDelete = $oldCfs->diffKeys($newCfs); // есть в зеркале но нет в амо. надо убрать из зеркала, в амо они удалены
            $deleteCompaniesCfs = $deleteCompaniesCfs->merge($cfsToDelete);

            $cfsToUpdate = $newCfs->only($oldCfs->keys());
            foreach($cfsToUpdate as $key => $cfToUpdate){
                // если изменений не было то дёргать базу не будет, это под капотом у save зашито
                $oldCfs->get($key)->fill($cfToUpdate)->save();
            }

            $mirrorCompany = $companiesMirror->get($companyAmo->id);
            $updateCompany = $this->companyToMirrorCompany($companyAmo, $mirrorCompany);
            // если изменений не было то дёргать базу не будет. хотя изменения будут всегда т.к. поле updated_at
            $updateCompany->save();

        }

        DB::transaction(function() use ($insertCompaniesCfs, $deleteCompaniesCfs){

            $deleteCompaniesCfsIds = collect(FORMAT::toIDArray($deleteCompaniesCfs->pluck('id')));
            $deleteCompaniesCfsIds->chunk(self::WHERE_IN_CHUNK_LIMIT)->each(function($idsChunk){
                MirrorCompaniesCfs::query()->whereIntegerInRaw('id', $idsChunk->all())->delete();
            });
            $insertCompaniesCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorCompaniesCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('updated successfully');
//        dump('companies to update: ');
//        dump($companiesToInsert);
    }

    private function insertNewContactsToMirror(Collection $contactsToInsert)
    {
        $insertContactsCfs = new Collection();
        $insertContacts = new Collection();

        /** @var stdClass $company */
        foreach($contactsToInsert as $contact){
            $insertContactsCfs = $insertContactsCfs->merge($this->entityCFsToMirrorCFs($contact));
            $insertContacts->push($this->contactToMirrorContact($contact)->toArray());
        }

        dump('before transaction start, count of arrays: ', [$insertContactsCfs->count(), $insertContacts->count()]);

        DB::transaction(function() use ($insertContactsCfs, $insertContacts){

            $insertContacts->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorContacts::query()->insert($chunk->all());
            });
            $insertContactsCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorContactsCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('end work');
    }
    private function insertNewLeadsToMirror(Collection $leadsToInsert)
    {
        $insertLeadsCfs = new Collection();
        $insertLeads = new Collection();

        /** @var stdClass $company */
        foreach($leadsToInsert as $lead){
            $insertLeadsCfs = $insertLeadsCfs->merge($this->entityCFsToMirrorCFs($lead));
            $insertLeads->push($this->leadToMirrorLead($lead)->toArray());
        }

        dump('before transaction start, count of arrays: ', [$insertLeadsCfs->count(), $insertLeads->count()]);

        DB::transaction(function() use ($insertLeadsCfs, $insertLeads){

            $insertLeads->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorLeads::query()->insert($chunk->all());
            });
            $insertLeadsCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorLeadsCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('end work');
    }
    private function insertNewCompaniesToMirror(Collection $companiesToInsert)
    {
        $insertCompaniesCfs = new Collection();
        $insertCompanies = new Collection();

        /** @var stdClass $company */
        foreach($companiesToInsert as $company){
            $insertCompaniesCfs = $insertCompaniesCfs->merge($this->entityCFsToMirrorCFs($company));
            $insertCompanies->push($this->companyToMirrorCompany($company)->toArray());
        }

        dump('before transaction start, count of arrays: ', [$insertCompaniesCfs->count(), $insertCompanies->count()]);

        DB::transaction(function() use ($insertCompaniesCfs, $insertCompanies){

            $insertCompanies->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorCompanies::query()->insert($chunk->all());
            });
            $insertCompaniesCfs->chunk(self::INSERT_CHUNK_LIMIT)->each(function($chunk){
                dump('insert query limit: '.self::INSERT_CHUNK_LIMIT);
                MirrorCompaniesCfs::query()->insert($chunk->all());
            });

        }, 5);

        dump('end work');
    }

    /**
     * Преобразует stdClass из апи амо в модель MirrorCompanies.
     * Если передана модель, то обновляет ее полями из stdClass, если нет - создает новую модель
     *
     * @param stdClass $company
     * @param MirrorCompanies|null $mirrorCompany
     * @return MirrorCompanies
     */
    private function companyToMirrorCompany(stdClass $company, ?MirrorCompanies $mirrorCompany = null): MirrorCompanies
    {
        $company = collect($company);
        $mirrorCompany = $mirrorCompany ?? new MirrorCompanies;
        $mirrorCompany->fill([
            'id' => $company->get('id'),
            'name' => $company->get('name'),
            'created_at' => date('Y-m-d H:i:s', $company->get('created_at')),
            'updated_at' => date('Y-m-d H:i:s', $company->get('updated_at')),
            'closest_task_at' => $company->get('closest_task_at') ? date('Y-m-d H:i:s', $company->get('closest_task_at')) : null,
            'responsible_user_id' => $company->get('responsible_user_id'),
            'created_by' => $company->get('created_by'),
            'updated_by' => $company->get('updated_by'),
        ]);
        return $mirrorCompany;
    }

    private function leadToMirrorLead(stdClass $lead, ?MirrorLeads $mirrorLead = null): MirrorLeads
    {
        $lead = collect($lead);
        $mirrorLead = $mirrorLead ?? new MirrorLeads;
        $mirrorLead->fill([
            'id' => $lead->get('id'),
            'name' => $lead->get('name'),
            'price' => $lead->get('price'),
            'created_at' => date('Y-m-d H:i:s', $lead->get('created_at')),
            'updated_at' => date('Y-m-d H:i:s', $lead->get('updated_at')),
            'closed_at' => $lead->get('closed_at') ? date('Y-m-d H:i:s', $lead->get('closed_at')) : null,
            'closest_task_at' => $lead->get('closest_task_at') ? date('Y-m-d H:i:s', $lead->get('closest_task_at')) : null,
            'status_id' => $lead->get('status_id'),
            'pipeline_id' => $lead->get('pipeline_id'),
            'responsible_user_id' => $lead->get('responsible_user_id'),
            'created_by' => $lead->get('created_by'),
            'updated_by' => $lead->get('updated_by'),
            'loss_reason_id' => $lead->get('loss_reason_id'),
        ]);
        return $mirrorLead;
    }

    private function contactToMirrorContact(stdClass $contact, ?MirrorLeads $mirrorContact = null): MirrorLeads
    {
        $contact = collect($contact);
        $mirrorContact = $mirrorContact ?? new MirrorLeads;
        $mirrorContact->fill([
            'id' => $contact->get('id'),
            'name' => $contact->get('name'),
            'first_name' => $contact->get('first_name'),
            'last_name' => $contact->get('last_name'),
            'created_at' => date('Y-m-d H:i:s', $contact->get('created_at')),
            'updated_at' => date('Y-m-d H:i:s', $contact->get('updated_at')),
            'closest_task_at' => $contact->get('closest_task_at') ? date('Y-m-d H:i:s', $contact->get('closest_task_at')) : null,
            'responsible_user_id' => $contact->get('responsible_user_id'),
            'created_by' => $contact->get('created_by'),
            'updated_by' => $contact->get('updated_by'),
        ]);
        return $mirrorContact;
    }

    /**
     * Преобразует stdClass из апи амо в массив для DB::table()->insert(...)
     *
     * @param stdClass $entity
     * @return array
     */
    private function entityCFsToMirrorCFs(stdClass $entity): array
    {
        $mirrorCfs = [];
        $entity = collect($entity);
        $entityId = $entity->get('id');
        foreach($entity->get('custom_fields_values') ?? [] as $cf){
            foreach($cf->values as $value){
                $cfv = [
                    'cf_id' => $cf->field_id,
                    'cf_code' => $cf->field_code,
                    'entity_id' => $entityId,
                    'enum_id' => $value->enum_id ?? null,
                    'enum_code' => $value->enum_code ?? null,
                ];
                if(in_array($cf->field_type, AmoHelper::CF_TYPES_SIMPLE)){
                    $cfv['value'] = $value->value ?? null;
                } else {
                    $cfv['value_json'] = json_encode($value);
                }
                $mirrorCfs[] = $cfv;
            }
        }
        return $mirrorCfs;
    }
}
