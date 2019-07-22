<?php

namespace Fourello\Push\Libraries\Messaging;

/**
 * Message object for push notification
 */
class Message {

    public $message = '';

    public $category = '';

    public $title = '';

    public $data = [];

    public function __construct($category = NULL, $title = NULL)
    {
        // set defaults;
        $this->category = $category;
        $this->title = $title;

        if (is_null($category) === TRUE) {
            $this->category = config('fourello-push.default.category');
        }

        if (is_null($title) === TRUE) {
            $this->title = config('fourello-push.default.title');
        }
    }

    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function generatePayload()
    {
        $payload = [
            'apns'  => json_encode(
                (object) [
                    'aps' => 
                    (object) [
                        'alert' => (object) [
                            'title' => $this->title,
                            'body' => $this->message
                        ],
                        'category' => $this->category,
                        'sound' => 'default',
                        'data' => (object) $data
                    ]
                ]),
            'gcm'   => json_encode(
                (object) [
                    'notification' => (object) [
                        'body' => $this->message,
                        'title' => $this->title,
                        'sound' => 'default',
                    ],
                    'category' => $this->category,
                    'data' => (object) $data,
                    'time_to_live'      => 3600,
                ])
        ];

        return $payload;
    }
}