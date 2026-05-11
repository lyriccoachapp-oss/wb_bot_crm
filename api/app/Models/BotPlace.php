<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель объекта (рабочая площадка)
 *
 * Read-only маппинг таблицы bot_places.
 * Структуру таблицы НЕ изменять!
 *
 * Поля: id_place, active, place_name, place_address, gdrive_id, status
 */
class BotPlace extends Model
{
	protected $table      = 'bot_places';
	protected $primaryKey = 'id_place';
	public $timestamps    = false;

	/**
	 * Разрешаем запись (объекты можно создавать/редактировать через API)
	 */
	protected $fillable = [
		'active',
		'place_name',
		'place_address',
		'gdrive_id',
		'works_type',
		'date_add',
		'id_telegram',
	];

	/**
	 * Приведение типов
	 */
	protected $casts = [
		'active' => 'boolean',
		'status' => 'integer',
	];

	/**
	 * Записи рабочего времени для этого объекта
	 */
	public function worktimes(): HasMany
	{
		return $this->hasMany(BotWorktime::class, 'id_place', 'id_place');
	}

	/**
	 * Чеки для этого объекта
	 */
	public function receipts(): HasMany
	{
		return $this->hasMany(BotReceipt::class, 'id_place', 'id_place');
	}
}
