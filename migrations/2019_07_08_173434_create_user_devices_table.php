<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->char('device_token', 150);
            $table->char('platform', 25);
            $table->char('arn', 150);
            $table->unsignedInteger('user_id')->nullable()->index('user_devices_user_id_foreign');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('SET NULL');
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
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign('user_devices_user_id_foreign');
            $table->dropColumn(['user_id']);
        });
        Schema::dropIfExists('user_devices');
    }
}
