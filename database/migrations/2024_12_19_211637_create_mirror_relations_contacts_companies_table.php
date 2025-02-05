<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorRelationsContactsCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_relations_contacts_companies', function (Blueprint $table) {

            $table->unsignedBigInteger('contact_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreign('contact_id')->references('id')->on('mirror_contacts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('mirror_companies')->cascadeOnUpdate()->cascadeOnDelete();

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
        Schema::dropIfExists('mirror_relations_contacts_companies');
    }
}
