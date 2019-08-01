<?php
namespace Fourello\Push\Libraries\Messaging;

use Aws\Sns\Exception\SnsException;
use Illuminate\Support\Facades\App;
use Fourello\Push\Models\UserDevice;
use Fourello\Push\Models\UserTopic;
use App\Models\User;
use Aws\Sns\SnsClient;
use Fourello\Push\Libraries\Messaging\Message;
use Fourello\Push\Events\FourelloPushed;

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

    /**
     * 1. Creates an SNS Platform End Point using the given device token
     * 2. Saves the registered information into the database
     */
    public function registerDeviceToken($deviceToken, $platform = 'IOS')
    {
        // check user

        if (is_null($this->user) === TRUE) {
            return 'User is not set';
        }
        $platformApplicationArn = '';

        if (strtoupper($platform) == 'ANDROID') {
            $platformApplicationArn = config('fourello-push.arn.android_arn');
        } else {
            $platformApplicationArn = config('fourello-push.arn.ios_arn');
        }

        try {
            $result = $this->client->createPlatformEndpoint(array(
                'PlatformApplicationArn' => $platformApplicationArn,
                'Token' => $deviceToken,
            ));

            $device = new UserDevice();

            $device->create([
                'device_token'  => $deviceToken,
                'platform'      => $platform,
                'arn'           => $result['EndpointArn'],
                'user_id'       => $this->user->id,
            ]);

            \Log::info($result);
            
            return $result;
        } catch (Exception $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }
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
        foreach ($this->user->Devices as $device) {
            $this->publishToArn($message, $device);
        }

        event(new FourelloPushed($message, $this->user));
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
            if (strtoupper($device->platform) == 'ANDROID') {
                $platformApplicationArn = config('fourello-push.arn.android_arn');
            } else {
                $platformApplicationArn = config('fourello-push.arn.ios_arn');
            }

            $message = $message->generatePayload($device->platform);

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

    public function publishToTopic()
    {

    }

    public function getAllTopics() // tested
    {
        return $this->topic->get();
    }

    public function getSNSAllTopics() // tested
    {
        try {
            $list = $this->client->listTopics([]);

            return $list['Topics'];
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return [];
        }

        return [];
    }

    /**
     * Get all topics using the AWS credential
     */
    public function createTopic($name, $label = 'Unlabelled Topic') //tested
    {
        try {
            $data = $this->client->createTopic([
                'Name'  => $name
            ]);

            $topic = $this->topic
                ->create([
                    'label' => $label,
                    'arn'   => $data['TopicArn']
                ]);

            return $topic;
        } catch (Exception $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }

        return FALSE;
    }

    /**
     * Get all topics using the AWS credential
     */
    public function deleteTopic($id) // tested
    {
        try {

            $topic = $this->topic->findOrfail($id);

            if (is_null($topic) === FALSE) {
                $result = $this->client->deleteTopic([
                    'TopicArn' => $topic->arn,
                ]);

                if ((int)$result['@metadata']['statusCode'] === 200) {
                    $topic->delete();

                    return TRUE;
                }
                return FALSE;
            }
            return FALSE;
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return FALSE;
        } 
    }

    public function unregisterTokenFromSNS()
    {

    }

    public function subscribeDeviceToTopic(UserDevice $device, $topicArn)
    {
        try {
            // $topicArn = env('AWS_SNS_TOPIC', AWS_SNS_TOPIC);
            $sns = App::make('aws')->createClient('sns');
            $result = $sns->subscribe([
                'Endpoint' => $device->arn,
                'Protocol' => 'application',
                'TopicArn' => $topicArn,
            ]);

            $data = [
                '@metadata' => $result['@metadata'],
                'SubscriptionArn'   => $result['subscriptionArn']
            ];
            
            return $data;
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }
    }

    public function unsubscribeDeviceToTopic(UserDevice $device, $subscriptionArn)
    {
        try {
            $result = $this->client->unsubscribe([
                'SubscriptionArn' => $subscriptionArn,
            ]);

            $data['@metadata'] = $result['@metadata'];

            // $device->delete();

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