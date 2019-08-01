<?php
namespace Fourello\Push\Models;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @author Jeremy Layson<jeremy.b.layson@gmail.com>
 * @since 07.08.2019
 * @version 1.0
 */
class UserTopicMember extends AbstractModel
{

    /**
     * Disable timestamps
     */
    public $timestamps = TRUE;

    /**
     * Relationships that gets loaded by default
     */
    protected $with = [];

    /**
     * fillable
     */
    protected $fillable = [
        'arn',
        'topic_arn',
        'user_topic_id',
        'user_device_id'
    ];

    /**
     * hidden fields
     * @var array
     */
    protected $hidden = [

    ];

    public function userDevice()
    {
        return $this->belongsTo('Fourello\Push\Models\UserDevice;', 'user_id');
    }

    public function userTopic()
    {
        return $this->belongsTo('Fourello\Push\Models\UserTopic', 'user_topic_id');
    }
}
