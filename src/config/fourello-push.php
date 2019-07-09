<?php

if (!defined('AWS_SNS_IOS_ARN')) define('AWS_SNS_IOS_ARN', $_SERVER['AWS_SNS_IOS_ARN'] ?? '');
if (!defined('AWS_SNS_ANDROID_ARN')) define('AWS_SNS_ANDROID_ARN', $_SERVER['AWS_SNS_ANDROID_ARN'] ?? '');

if (!defined('PUSH_DEFAULT_TITLE')) define('PUSH_DEFAULT_TITLE', $_SERVER['PUSH_DEFAULT_TITLE'] ?? '');
if (!defined('PUSH_DEFAULT_CATEGORY')) define('PUSH_DEFAULT_CATEGORY', $_SERVER['PUSH_DEFAULT_CATEGORY'] ?? '');

return [

    'arn'   => [
        'ios_arn'           => env('AWS_SNS_IOS_ARN',       AWS_SNS_IOS_ARN),
        'android_arn'       => env('AWS_SNS_ANDROID_ARN',   AWS_SNS_ANDROID_ARN),
    ],
    'default'  => [
        'title'     => env('PUSH_DEFAULT_TITLE',    PUSH_DEFAULT_TITLE), // title of the notification
        'category'  => env('PUSH_DEFAULT_CATEGORY', PUSH_DEFAULT_CATEGORY), // mobile-specific settings
    ]
];