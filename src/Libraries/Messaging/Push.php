<?php
namespace Fourello\Push\Libraries\Messaging;

use Aws\Sns\Exception\SnsException;
use Illuminate\Support\Facades\App;
use Fourello\Push\Models\UserDevice;
use Fourello\Push\Models\UserTopic;
use App\Models\User;
use Aws\Sns\SnsClient;
use Fourello\Push\Messaging\Message;
class Push {

    protected $client;

    protected $targetARN = NULL;

    protected $user = NULL;

    private $topic;

    protected $devices = [];

    /**
     * Create the client object that will be used throughout the class
     */
    public function __construct(UserTopic $topic)
    {
        $this->topic = $topic;
        $this->client = App::make('aws')->createClient('sns');
    }

    public function setUser(User $user)
    {
        $this->user = $user;

        $this->devices = $user->Devices;

        return $this;
    }

    public function hasUser()
    {
        return !is_null($this->user);
    }

    /**
     * message = ['title' => '', 'content'  => '', 'category'   => ''];
     */
    public function publishToUser(Message $message)
    {

    }
 
    /**
     * @todo  test
     */
    public function publishToArn(Message $message, UserDevice $device)
    {
        try {
            $client = App::make('aws')->createClient('sns');

            // enable first
            $result = $client->setEndpointAttributes([
                'Attributes' => ['Enabled' => 'true'],
                'EndpointArn' => $device->arn, 
            ]);

            $platformApplicationArn = '';
            if ($device->platform == 'android') {
                $platformApplicationArn = config('fourello-push.arn.android_arn');
            } else {
                $platformApplicationArn = config('fourello-push.arn.ios_arn');
            }

            $message = $messsage->generatePayload($device->platform);

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

            \Log::info($result);

        } catch (SnsException $e) {
            \Log::error($e->getMessage());
            return response()->json(['error' => "Unexpected Error"], 500);
        }

        return response()->json(["status" => "Device token processed"], 200);
    }

    public function publishToTopic()
    {

    }

    /**
     * Get a list of registered devices of user
     */
    public function getRegisteredDevices()
    {
        try {
            $result = $this->client->listSubscriptions([]);

            return $result;
        } catch (AwsException $e) {
            // output error message if fails
            \Log::info($e->getMessage());
            return $e->getMessage();
        }
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
    public function registerTokenToSNS(UserDevice $device, $topic)
    {
        try {
            $result = $this->client->subscribe([
                'Endpoint' => $device->arn,
                'Protocol' => 'application',
                'TopicArn' => $topic->arn,
            ]);
        } catch (Exception $e) {
            
        }
    }

    public function unregisterTokenFromSNS()
    {

    }

    public function subscribeDeviceToTopic(UserDevice $device)
    {
        try {
            $topicArn = env('AWS_SNS_TOPIC', AWS_SNS_TOPIC);
            $sns = App::make('aws')->createClient('sns');
            $result = $sns->subscribe([
                'Endpoint' => $device->arn,
                'Protocol' => 'application',
                'TopicArn' => $topicArn,
            ]);

        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }
        
        return $result['SubscriptionArn'] ?? '';
    }

    public function unsubscribeDeviceToTopic($subscriptionArn)
    {
        try {
            $result = $this->client->unsubscribe([
                'SubscriptionArn' => $subscriptionArn,
            ]);

            $data['@metadata'] = $result['@metadata'];

            return $data;
        } catch (AwsException $e) {
            // output error message if fails
            \Log::info($e->getMessage());
            return $e->getMessage();
        } 
    }

















    /**
     * @todo for deletion
     */
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