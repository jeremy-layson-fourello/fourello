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

    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    public function getData() { return $this->data; }
    public function getMessage() { return $this->message; }
    public function getCategory() { return $this->category; }
    public function getTitle() { return $this->title; }

    public function payloadToArray()
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->data,
            'category' => $this->category,
        ];
    }

    public function generatePayload($platform = 'IOS')
    {
        $apns = '';
        $gcm = '';
        $default = '';

        $apns = json_encode(
            (object) [
                'aps' => 
                (object) [
                    'alert' => (object) [
                        'title' => $this->title,
                        'body' => $this->message
                    ],
                    'category' => $this->category,
                    'sound' => 'default',
                    'data' => (object) $this->data
                ]
            ]);
        $gcm = json_encode(
            (object) [
                'notification' => (object) [
                    'body' => $this->message,
                    'title' => $this->title,
                    'sound' => 'default',
                ],
                'category' => $this->category,
                'data' => (object) $this->data,
                'time_to_live'      => 3600,
            ]);

        if (strtoupper($platform) === 'IOS') {
            $default = $apns;
        } else {
            $default = $gcm;
        }

        $payload = json_encode([
            'APNS'  => $apns,
            'APNS_SANDBOX' => $apns,
            'default' => $default,
            'GCM'   => $gcm
        ]);

        

        return $payload;
    }
}