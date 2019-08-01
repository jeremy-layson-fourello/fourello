<?php

namespace Fourello\Push\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use App\Models\User;
use Fourello\Push\Libraries\Messaging\Message;

class FourelloPushed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $message, $user;

    public function __construct(Message $message, User $user)
    {
        $this->message = $message;
        $this->user = $user;
    }
}
