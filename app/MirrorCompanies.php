<?php

namespace App;


use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int|null id
 * @property string|null name
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

 * @property Collection<MirrorContacts> contacts
 * @property Collection<MirrorLeads> leads
 * @property Collection<MirrorCompaniesCfs> cfs
 * @property Collection<MirrorCompaniesTags> tags
 */
class MirrorCompanies extends MirrorModel
{
    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->cfs.
     * Оно будет содержать коллекцию MirrorCompaniesCfs которые привязаны к текущей компании.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#one-to-many
     *
     * @return HasMany
     */
    public function cfs(): HasMany
    {
        return $this->hasMany(MirrorCompaniesCfs::class, 'company_id', 'id');
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->tags.
     * Оно будет содержать коллекцию MirrorCompaniesTags которые привязаны к текущей компании.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorCompaniesTags::class,
            'mirror_relations_companies_tags',
            'company_id',
            'tag_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->contacts.
     * Оно будет содержать коллекцию MirrorContacts которые привязаны к текущей компании.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorContacts::class,
            'mirror_relations_contacts_companies',
            'company_id',
            'contact_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->leads.
     * Оно будет содержать коллекцию MirrorLeads которые привязаны к текущей компании.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorLeads::class,
            'mirror_relations_leads_companies',
            'company_id',
            'lead_id',
        );
    }
}
