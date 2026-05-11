<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentBlock extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'content', 'access_level'];

    protected $casts = [
        'content' => 'array',
    ];

    /**
     * Переопределяем метод для сохранения JSON без экранирования кириллицы
     */
    protected function asJson($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
