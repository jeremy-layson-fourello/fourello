<?php
namespace Fourello\Push\Models;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @author Jeremy Layson<jeremy.b.layson@gmail.com>
 * @since 07.08.2019
 * @version 1.0
 */
class UserTopic extends AbstractModel
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
        'label',
        'arn',
        'argument',
    ];

    /**
     * hidden fields
     * @var array
     */
    protected $hidden = [

    ];

    public function members()
    {
        return $this->hasMany('Fourello\Push\Models\UserTopicMember');
    }
}
