<?php

namespace App;


use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @property int|null id
 * @property string|null name
 * @property double|null price
 *
 * @property string|null created_at
 * @property string|null updated_at
 * @property string|null closed_at
 * @property string|null closest_task_at
 * @property string|null created_at_mirror
 * @property string|null updated_at_mirror
 *
 * @property int|null status_id
 * @property int|null pipeline_id
 * @property int|null responsible_user_id
 * @property int|null created_by
 * @property int|null updated_by
 * @property int|null loss_reason_id
 * @property int|null deleted_at
 *
 * @property Collection<MirrorContacts> contacts
 * @property Collection<MirrorCompanies> companies
 * @property Collection<MirrorLeadsCfs> cfs
 * @property Collection<MirrorLeadsTags> tags
 */
class MirrorLeads extends MirrorModel
{

    /**
     * Проверка на то что модель можно создать в зеркале и база не будет ругаться.
     * Т.е. все поля которые в базе not nullable должны быть заполнены
     * Этод метод нужен если нас интересует какое конкретно поле не корректно
     *
     * @return void
     */
    public function checkValid(): void
    {
        parent::checkValid();
        if(empty($this->status_id)) throw new InvalidArgumentException("Missing required property status_id for MirrorModel");
        if(empty($this->pipeline_id)) throw new InvalidArgumentException("Missing required property pipeline_id for MirrorModel");
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->cfs.
     * Оно будет содержать коллекцию MirrorLeadsCfs которые привязаны к текущему лиду.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#one-to-many
     *
     * @return HasMany
     */
    public function cfs(): HasMany
    {
        return $this->hasMany(MirrorLeadsCfs::class, 'lead_id', 'id');
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->tags.
     * Оно будет содержать коллекцию MirrorLeadsTags которые привязаны к текущему лиду.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorLeadsTags::class,
            'mirror_relations_leads_tags',
            'lead_id',
            'tag_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->contacts.
     * Оно будет содержать коллекцию MirrorContacts которые привязаны к текущему лиду.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorContacts::class,
            'mirror_relations_leads_contacts',
            'lead_id',
            'contact_id',
        );
    }

    /**
     * Наличие этого метода позволяет пользоваться магическим свойством $this->companies.
     * Оно будет содержать коллекцию MirrorCompanies которые привязаны к текущему лиду.
     * Доки: https://laravel.com/docs/7.x/eloquent-relationships#many-to-many
     *
     * @return BelongsToMany
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            MirrorCompanies::class,
            'mirror_relations_leads_companies',
            'lead_id',
            'company_id',
        );
    }
}
