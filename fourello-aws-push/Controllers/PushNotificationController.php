<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Libraries\Messaging\Push;
use Illuminate\Support\Facades\Auth;

/**
 * @author Jeremy Layson<jeremy.b.layson@gmail.com>
 * @since 07.08.2019
 * @version 1.0
 */
class PushNotificationController extends Controller
{
    private $user;

    private $device;\

    public function __construct(UserDevice $device)
    {
        $this->device = $device;
        $this->user = Auth::guard('api')->user();
    }

    public function push(Request $request)
    {
        $message = $request->get('message');

        $devices = $this->user->devices;

        foreach ($devices as $device) {
            Push::sendToArn($message, $device);
        }

        return response(['Success'], 200);
    }

    public function register(Request $request)
    {
        // first or create the platform
        $device = $this->device
            ->where('device_token', trim($request->get('device_token')))
            ->first();

        if ($device === null) {
            $device = $this->device->create([
                'device_token'  => $request->get('device_token'),
                'platform'      => $request->get('platform'),
            ]);
        }

        $device->user_id = $this->user->id;
        $device->save();

        // register to SNS
        $data = Push::registerToken($device->device_token, $device->platform);

        // update arn
        $device->arn = $data['EndpointArn'];
        $device->save();

        $data = Push::subscribe($device);

        $device->subscription_arn = $data;
        $device->save();

        return response(['Success'], 200);
    }

    public function unregister(Request $request)
    {
        $device = $this->device
            ->where('device_token', $request->get('device_token'))
            ->where('user_id', $this->user->id)
            ->first();

        Push::deleteToken($device->arn);

        $device->user_id = null;
        $device->save();

        return response(['Success'], 200);
    }

    public function sendToAll(Request $request)
    {
        $message = $request->get('message');
        $title = $request->get('title');

        $data = Push::pushToTopic($message, $title, 'expee_to_all', [], env('AWS_SNS_TOPIC'));

        return response([$data], 200);
    }
}
