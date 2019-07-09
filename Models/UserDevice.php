<?php
namespace App\Models;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @author Jeremy Layson<jeremy.b.layson@gmail.com>
 * @since 07.08.2019
 * @version 1.0
 */
class UserDevice extends AbstractModel
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
        'device_token',
        'platform',
        'arn',
        'subscription_arn',
        'user_id',
    ];

    /**
     * hidden fields
     * @var array
     */
    protected $hidden = [

    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
