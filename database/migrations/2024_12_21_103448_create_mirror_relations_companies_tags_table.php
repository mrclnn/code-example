<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorRelationsCompaniesTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_relations_companies_tags', function (Blueprint $table) {

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->foreign('company_id')->references('id')->on('mirror_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('mirror_companies_tags')->cascadeOnUpdate()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_relations_companies_tags');
    }
}
