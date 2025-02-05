<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorRelationsLeadsContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_relations_leads_contacts', function (Blueprint $table) {

            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('contact_id')->index();
            $table->foreign('lead_id')->references('id')->on('mirror_leads')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('mirror_contacts')->cascadeOnUpdate()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //todo нужно ли тут дропать отдельно индексы и внешние индексы?
        Schema::dropIfExists('mirror_relations_leads_contacts');
    }
}
