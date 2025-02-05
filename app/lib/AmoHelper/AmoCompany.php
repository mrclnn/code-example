<?php

namespace App\lib\AmoHelper;

use App\lib\AmoMapping\samolet\CF_COMPANY;

class AmoCompany extends AmoEntity
{
    public function setInternalNumberDB(string $internalNumberDB): self
    {
        $this->setCF(CF_COMPANY::INTERNAL_NUMBER_DB, $internalNumberDB);
        return $this;
    }

    public function setPRLeadCreated(): self
    {
        $this->setCF(CF_COMPANY::PR_LEAD_CREATED, true);
        return $this;
    }

    public function setCategory(?int $enum): self
    {
        if(is_null($enum)) return $this;
        $this->setCF(CF_COMPANY::CATEGORY_M, [['enum_id' => $enum]]);
        return $this;
    }

    public function setSamoletFranchise(?bool $isSamoletFranchise): self
    {
        if(is_null($isSamoletFranchise)) return $this;
        $this->setCF(CF_COMPANY::SAMOLET_FRANCHISE, $isSamoletFranchise);
        return $this;
    }

    public function setAdditionalINN(?string $inn): self
    {
        if(empty($inn)) return $this;
        $this->setCF(CF_COMPANY::INN_ADDITIONAL, $inn);
        return $this;
    }
    public function setINN(?string $inn): self
    {
        if(empty($inn)) return $this;
        $this->setCF(CF_COMPANY::INN_MAIN, $inn);
        return $this;
    }

    public function getRegion() { return $this->getCFV(CF_COMPANY::REGION); }
    public function getCity() { return $this->getCFV(CF_COMPANY::CITY); }
    public function getInternalCompanyId() { return $this->getCFV(CF_COMPANY::INTERNAL_NUMBER_DB); }
}
