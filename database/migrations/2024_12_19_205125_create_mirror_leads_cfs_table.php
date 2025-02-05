<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorLeadsCfsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_leads_cfs', function (Blueprint $table) {
            $table->id();

            $table->integer('cf_id')->index();
            $table->string('cf_code')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->foreign('entity_id')->references('id')->on('mirror_leads')->cascadeOnUpdate()->cascadeOnDelete();
            $table->integer('enum_id')->nullable()->index();
            $table->string('enum_code')->nullable()->index();
            $table->text('value')->nullable(); // нужно уточнить, возможно text это перебор
            $table->json('value_json')->nullable(); // это нужно для сложных полей типа supplier, payer, file, chained_list, linked_entity, items, legal_entity
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_leads_cfs');
    }
}
