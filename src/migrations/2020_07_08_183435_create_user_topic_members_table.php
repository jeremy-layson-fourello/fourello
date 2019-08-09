<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserTopicMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_topic_members', function (Blueprint $table) {
            $table->increments('id');

            $table->char('arn', 150);
            $table->char('topic_arn', 150);

            $table->unsignedInteger('user_topic_id')->nullable()->index('user_topic_members_user_topic_id_foreign');
            $table->foreign('user_topic_id')->references('id')->on('users')->onDelete('SET NULL');

            $table->unsignedInteger('user_device_id')->nullable()->index('user_topic_members_user_device_id_foreign');
            $table->foreign('user_device_id')->references('id')->on('users')->onDelete('SET NULL');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_topic_members', function (Blueprint $table) {
            $table->dropForeign('user_topic_members_user_topic_id_foreign');
            $table->dropForeign('user_topic_members_user_device_id_foreign');
        });
        Schema::dropIfExists('user_topic_members');
    }
}
