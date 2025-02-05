<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorRelationsContactsTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_relations_contacts_tags', function (Blueprint $table) {

            $table->unsignedBigInteger('contact_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->foreign('contact_id')->references('id')->on('mirror_contacts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('mirror_contacts_tags')->cascadeOnUpdate()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_relations_contacts_tags');
    }
}
