<?php
namespace App\Libraries\Messaging;

use Aws\Sns\Exception\SnsException;
use Illuminate\Support\Facades\App;
use App\Models\UserDevice;
use App\Models\User;
use Aws\Sns\SnsClient; 

class Push {

    protected $client;

    protected $targetARN = NULL;

    /**
     * Create the client object that will be used throughout the class
     */
    public function __construct()
    {
        $this->client = App::make('aws')->createClient('sns');
    }

    /**
     * message = ['title' => '', 'content'  => '', 'category'   => ''];
     */
    public function publishToUser($message, UserDevice $device)
    {

    }

    public function publishToArn()
    {

    }

    public function publishToTopic()
    {

    }

    /**
     * Get a list of registered devices of user
     */
    public function getRegisteredDevices()
    {

    }

    public function getAllTopics()
    {
        try {
            $list = $this->client->listTopics([]);

            return $list;
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return [];
        }
    }

    /**
     * Get all topics using the AWS credential
     */
    public function createTopic($name)
    {
        try {
            $data = $this->client->createTopic([
                'Name'  => $name
            ]);

            return $data;
        } catch (Exception $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }
    }

    /**
     * Get all topics using the AWS credential
     */
    public function deleteTopic($arn)
    {
        try {
            $result = $this->client->deleteTopic([
                'TopicArn' => $arn,
            ]);
            
            return $result;
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return FALSE;
        } 
    }


    /**
     * Get all topics using the AWS credential
     */
    public function registerTokenToSNS()
    {

    }

    public function unregisterTokenFromSNS()
    {

    }

    public function subscribeDeviceToTopic()
    {

    }

    public function unsubscribeDeviceToTopic()
    {

    }





    public static function sendToArn($message, $device, $title = 'Expee', $category = 'expee', $payload = [])
    {
        try {

            $default = $message;

            $client = App::make('aws')->createClient('sns');

            // enable first
            $result = $client->setEndpointAttributes([
                'Attributes' => ['Enabled' => 'true'],
                'EndpointArn' => $device->arn, 
            ]);

            $apnsPayload = json_encode(
                (object) [
                    'aps' => 
                    (object) [
                        'alert' => (object) [
                            'title' => $title,
                            'body' => $message
                        ],
                        'category' => $category,
                        'sound' => 'default',
                        'data' => (object) $payload
                    ]
                ]);

            $gcmPayload = json_encode(
                (object) [
                    'notification' => (object) [
                        'body' => $message,
                        'title' => $title,
                        'sound' => 'default',
                    ],
                    'category' => $category,
                    'data' => (object) $payload,
                    'time_to_live'      => 3600,
                ]);

            $platformApplicationArn = '';
            if ($device->platform == 'android') {
                $default = ($gcmPayload);
                $platformApplicationArn = config('fourello-push.arn.android_arn');
            } else {
                $default = ($apnsPayload);
                $platformApplicationArn = config('fourello-push.arn.ios_arn');
            }

            $message = json_encode(['GCM' => $gcmPayload, 'APNS' => $apnsPayload, 'APNS_SANDBOX' => $apnsPayload, 'default' => $default]);

            $client->publish(array(
                'TargetArn'         => $device->arn,
                'Message'           => $message,
                'ttl'               => 86400,
                'MessageStructure'  => 'json'
            ));

            // re-enable first
            $result = $client->setEndpointAttributes([
                'Attributes' => ['Enabled' => 'true'],
                'EndpointArn' => $device->arn, 
            ]);

        } catch (SnsException $e) {
            \Log::error($e->getMessage());
            return response()->json(['error' => "Unexpected Error"], 500);
        }

        return response()->json(["status" => "Device token processed"], 200);
    }

    public static function deleteToken($arn)
    {
        $sns = App::make('aws')->createClient('sns');

        $sns->unsubscribe([
            'SubscriptionArn' => $arn,
        ]);

        $sns->deleteEndpoint([
            'EndpointArn'   => $arn
        ]);
    }

    public static function registerToken($deviceToken, $platform = 'ios')
    {
        try {
            $platformApplicationArn = '';
            if ($platform == 'android') {
                $platformApplicationArn = env('AWS_SNS_ANDROID_ARN', AWS_SNS_ANDROID_ARN);
            } else {
                $platformApplicationArn = env('AWS_SNS_IOS_ARN', AWS_SNS_IOS_ARN);
            }

            $client = App::make('aws')->createClient('sns');

            try {
                $result = $client->createPlatformEndpoint(array(
                    'PlatformApplicationArn' => $platformApplicationArn,
                    'Token' => $deviceToken,
                ));
                
                return $result;
            } catch (Exception $e) {
                \Log::error($e->getMessage());

                return FALSE;
            }

        } catch (Exception $e) {
            \Log::info($e->getMessage());
        }
    }

    public static function subscribe($device)
    {
        $topicArn = env('AWS_SNS_TOPIC', AWS_SNS_TOPIC);
        $sns = App::make('aws')->createClient('sns');
        $result = $sns->subscribe([
            'Endpoint' => $device->arn,
            'Protocol' => 'application',
            'TopicArn' => $topicArn,
        ]);

        return $result['SubscriptionArn'] ?? '';
    }

    public static function pushToTopic($message, $title, $category, $payload, $topicArn)
    {
        $client = App::make('aws')->createClient('sns');

        $apnsPayload = json_encode(
            (object) [
                'aps' => 
                (object) [
                    'alert' => (object) [
                        'title' => $title,
                        'body' => $message
                    ],
                    'category' => $category,
                    'sound' => 'default',
                    'data' => (object) $payload
                ]
            ]);

        $gcmPayload = json_encode(
            (object) [
                'notification' => (object) [
                    'body' => $message,
                    'title' => $title,
                    'sound' => 'default',
                ],
                'category' => $category,
                'data' => (object) $payload,
                'time_to_live'      => 3600,
            ]);

        $default = $message;

        $message = json_encode(['GCM' => $gcmPayload, 'APNS' => $apnsPayload, 'APNS_SANDBOX' => $apnsPayload, 'default' => $default]);

        $client->publish(array(
            'TopicArn'         => $topicArn,
            'Message'           => $message,
            'ttl'               => 60,
            'MessageStructure'  => 'json'
        ));
    }
}