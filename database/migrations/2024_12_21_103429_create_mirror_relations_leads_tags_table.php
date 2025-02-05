<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorRelationsLeadsTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_relations_leads_tags', function (Blueprint $table) {

            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->foreign('lead_id')->references('id')->on('mirror_leads')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('mirror_leads_tags')->cascadeOnUpdate()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_relations_leads_tags');
    }
}
