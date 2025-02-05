<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMirrorLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mirror_leads', function (Blueprint $table) {

            $table->id();
            $table->string('name');
            $table->float('price', 12)->nullable();

            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('closest_task_at')->nullable();
            $table->timestamp('created_at_mirror')->useCurrent();;
            $table->timestamp('updated_at_mirror')->useCurrent();;

            $table->integer('status_id')->index();
            $table->integer('pipeline_id')->index();
            $table->integer('responsible_user_id')->index();
            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('loss_reason_id')->nullable();

            $table->softDeletes();

//            $table->integer('account_id'); // не создаем потому что бессмысленно. работаем только с одним аккаунтом.
//            $table->integer('group_id'); // не создаем потому что бессмысленно. можно получить эту инфо из инфо про ответственного.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mirror_leads');
    }
}
