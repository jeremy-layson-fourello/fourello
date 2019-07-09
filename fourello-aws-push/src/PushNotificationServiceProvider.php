<?php
namespace Fourello;

use Illuminate\Support\ServiceProvider;

class PushNotificationServiceProvider extends ServiceProvider {

    public function boot()
    {


        // publish migrations
        $this->publishes([
            __DIR__ . '/../migrations/2019_07_08_173434_create_user_devices_table.php' => database_path('migrations/' . $file),
            __DIR__ . '/../migrations/2019_07_08_173435_create_user_topics_table.php' => database_path('migrations/' . $file),
        ]);

        // publish other files
        $this->publishes([
            __DIR__ . '/Models' => base_path('app/Models'),
            __DIR__ . '/Controllers' => base_path('app/Http/Controllers'),
            __DIR__ . '/Libraries' => base_path('app/Libraries'),
            __DIR__ . '/config/fourello-push' => config_path('fourello-push.php'),
        ]);
    }

    public function register()
    {
        $this->app->alias('fourello-push-notification', 'Fourello');

    }

    public function provides()
    {
        return ['fourello-push-notification', 'Fourello'];
    }
}