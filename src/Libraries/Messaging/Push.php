<?php
namespace Fourello\Push\Libraries\Messaging;

use Aws\Sns\Exception\SnsException;
use Illuminate\Support\Facades\App;
use Fourello\Push\Models\UserDevice;
use Fourello\Push\Models\UserTopic;
use Fourello\Push\Models\UserTopicMember;
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

    public function publishToTopic(Message $message, $id)
    {
        $topic = $this->topic->findOrFail($id);

        $client = App::make('aws')->createClient('sns');

        $message = $message->generatePayload();

        $client->publish(array(
            'TopicArn'         => $topic->arn,
            'Message'           => $message,
            'ttl'               => 3600,
            'MessageStructure'  => 'json'
        ));
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
    public function createTopic($name, $label = 'Unlabelled Topic', $argument = '[{\"column\": \"id\",\"operator\": \"!=\",\"type\": \"single_argument\",\"value\": null}]') //tested
    {
        try {
            $data = $this->client->createTopic([
                'Name'  => $name
            ]);

            $topic = $this->topic
                ->create([
                    'label' => $label,
                    'arn'   => $data['TopicArn'],
                    'argument'  => $argument
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

    public function subscribeDeviceToTopic(UserDevice $device, UserTopic $topic)
    {
        try {

            $sns = App::make('aws')->createClient('sns');
            $result = $sns->subscribe([
                'Endpoint' => $device->arn,
                'Protocol' => 'application',
                'TopicArn' => $topic->arn,
            ]);

            $data = [
                '@metadata' => $result['@metadata'],
                'SubscriptionArn'   => $result['SubscriptionArn']
            ];

            $member = new UserTopicMember();
            $member->create([
                'arn'       => $result['SubscriptionArn'],
                'topic_arn' => $topic->arn,
                'user_id'   => $device->user_id,
                'user_topic_id' => $topic->id
            ]);

            return $data;
        } catch (AwsException $e) {
            \Log::error($e->getMessage());

            return FALSE;
        }
    }

    public function unsubscribeDeviceToTopic(UserTopicMember $membership)
    {
        try {
            $result = $this->client->unsubscribe([
                'SubscriptionArn' => $membership->arn,
            ]);

            $data['@metadata'] = $result['@metadata'];

            $membership->delete();

            return $data;
        } catch (AwsException $e) {
            // output error message if fails
            \Log::info($e->getMessage());
            return $e->getMessage();
        } 
    }
}