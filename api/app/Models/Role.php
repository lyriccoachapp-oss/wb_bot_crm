<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель Роли (crm_roles)
 */
class Role extends Model
{
	protected $table = 'crm_roles';

	protected $guarded = [];

	protected $casts = [
		'permissions' => 'array',
	];

	/**
	 * Пользователи с этой ролью
	 */
	public function users(): HasMany
	{
		return $this->hasMany(BotUser::class, 'role_id', 'id');
	}
}
