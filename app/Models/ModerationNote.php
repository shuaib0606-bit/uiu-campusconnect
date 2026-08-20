<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationNote extends Model
{
    public $timestamps = false;
    protected $fillable = ['moderator_id', 'report_type', 'report_id', 'target_user_id', 'action_taken', 'note', 'created_at'];
}
