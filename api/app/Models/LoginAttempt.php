<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['email', 'ip', 'user_agent', 'successful', 'user_id'];

    protected function casts(): array
    {
        return ['successful' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
