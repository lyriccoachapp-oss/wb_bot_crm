<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Модель refresh-токена
 */
class CrmToken extends Model
{
	protected $table = 'crm_tokens';

	protected $fillable = [
		'user_id',
		'token_hash',
		'expires_at',
		'ip',
		'user_agent',
	];

	protected $casts = [
		'expires_at' => 'datetime',
	];

	/**
	 * Проверить, не истёк ли токен
	 */
	public function isExpired(): bool
	{
		return $this->expires_at->isPast();
	}
}
