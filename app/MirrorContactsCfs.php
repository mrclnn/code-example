<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null id
 * @property int|null cf_id
 * @property string|null cf_code
 * @property int|null contact_id
 * @property int|null enum_id
 * @property string|null enum_code
 * @property string|null value
 * @property string|null value_json
 */
class MirrorContactsCfs extends MirrorCfs
{
    public function contact(): BelongsTo
    {
        return $this->belongsTo(MirrorContacts::class);
    }
}
