<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'companies';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function users()
    {
        return $this->hasMany(BotUser::class, 'company_slug', 'slug');
    }
}
