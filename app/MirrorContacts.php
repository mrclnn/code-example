<?php

namespace App;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int|null id
 * @property string|null name
 * @property string|null first_name
 * @property string|null last_name
 *
 * @property string|null created_at
 * @property string|null updated_at
 * @property string|null closest_task_at
 * @property string|null created_at_mirror
 * @property string|null updated_at_mirror
 *
 * @property int|null responsible_user_id
 * @property int|null created_by
 * @property int|null updated_by
 * @property int|null deleted_at
 *
 * @property Collection<MirrorLeads> leads
 * @property Collection<MirrorCompanies> companies
 * @property Collection<MirrorContactsCfs> cfs
 * @property Collection<MirrorContactsTags> tags
 */
class MirrorContacts extends MirrorModel
{
    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->cfs.
     * Оно будет содержать коллекцию MirrorContactsCfs которые привязаны к текущему контакту.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#one-to-many
     *
     * @return HasMany
     */
    public function cfs(): HasMany
    {
        return $this->hasMany(MirrorContactsCfs::class, 'contact_id', 'id');
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->tags.
     * Оно будет содержать коллекцию MirrorContactsTags которые привязаны к текущему контакту.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorContactsTags::class,
            'mirror_relations_contacts_tags',
            'contact_id',
            'tag_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->companies.
     * Оно будет содержать коллекцию MirrorCompanies которые привязаны к текущему контакту.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorCompanies::class,
            'mirror_relations_contacts_companies',
            'contact_id',
            'company_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->leads.
     * Оно будет содержать коллекцию MirrorLeads которые привязаны к текущему контакту.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorLeads::class,
            'mirror_relations_leads_contacts',
            'contact_id',
            'lead_id',
        );
    }
}
