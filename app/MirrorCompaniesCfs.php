<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int|null id
 * @property int|null cf_id
 * @property string|null cf_code
 * @property int|null company_id
 * @property int|null enum_id
 * @property string|null enum_code
 * @property string|null value
 * @property string|null value_json
 */
class MirrorCompaniesCfs extends MirrorCfs
{
    public $timestamps = false;
    public function company(): BelongsTo
    {
        return $this->belongsTo(MirrorCompanies::class);
    }
}
