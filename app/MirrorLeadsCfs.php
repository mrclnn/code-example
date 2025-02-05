<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null id
 * @property int|null cf_id
 * @property string|null cf_code
 * @property int|null lead_id
 * @property int|null enum_id
 * @property string|null enum_code
 * @property string|null value
 * @property string|null value_json
 */
class MirrorLeadsCfs extends MirrorCfs
{
    public function lead(): BelongsTo
    {
        return $this->belongsTo(MirrorLeads::class);
    }
}
