<?php

if (!defined('AWS_SNS_IOS_ARN')) define('AWS_SNS_IOS_ARN', $_SERVER['AWS_SNS_IOS_ARN'] ?? '');
if (!defined('AWS_SNS_ANDROID_ARN')) define('AWS_SNS_ANDROID_ARN', $_SERVER['AWS_SNS_ANDROID_ARN'] ?? '');

return [

    'arn'   => [
        'ios_arn'           => env('AWS_SNS_IOS_ARN', AWS_SNS_IOS_ARN),
        'android_arn'       => env('AWS_SNS_ANDROID_ARN', AWS_SNS_ANDROID_ARN),
    ]
];