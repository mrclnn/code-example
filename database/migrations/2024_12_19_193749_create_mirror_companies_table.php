<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_companies', function (Blueprint $table) {

            $table->id();
            $table->string('name');

            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at');
            $table->timestamp('closest_task_at')->nullable();
            $table->timestamp('created_at_mirror')->useCurrent();
            $table->timestamp('updated_at_mirror')->useCurrent();

            $table->integer('responsible_user_id')->index();
            $table->integer('created_by');
            $table->integer('updated_by');

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_companies');
    }
}
