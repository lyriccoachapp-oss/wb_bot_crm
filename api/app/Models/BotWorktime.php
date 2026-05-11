<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель записи рабочего времени
 *
 * Маппинг таблицы bot_wp_worktime.
 * Структуру таблицы НЕ изменять!
 *
 * Поля: id_worktime, id_telegram, id_place, workday,
 *        checkin, checkout, lunchin, lunchout,
 *        gas_costs, latitude, longitude, photo, work_desc, status
 */
class BotWorktime extends Model
{
	protected $table      = 'bot_wp_worktime';
	protected $primaryKey = 'id_worktime';
	public $timestamps    = false;

	protected $fillable = [
		'id_telegram',
		'id_place',
		'workday',
		'checkin',
		'checkout',
		'lunchin',
		'lunchout',
		'status',
		'workdone',
	];

	/**
	 * Приведение типов
	 */
	protected $casts = [
		'id_telegram' => 'integer',
		'id_place'    => 'integer',
		'gas_costs'   => 'integer',
		'latitude'    => 'float',
		'longitude'   => 'float',
		'status'      => 'integer',
	];

	/**
	 * Объект (рабочая площадка)
	 */
	public function place(): BelongsTo
	{
		return $this->belongsTo(BotPlace::class, 'id_place', 'id_place');
	}

	/**
	 * Пользователь бота
	 */
	public function botUser(): BelongsTo
	{
		return $this->belongsTo(BotUser::class, 'id_telegram', 'id_telegram');
	}

	/**
	 * Рассчитать количество рабочих секунд (с вычетом обеда)
	 */
	public function getWorkSecondsAttribute(): int
	{
		if (!$this->checkin || !$this->checkout) return 0;
		if (str_starts_with($this->checkin,  '0000-00-00')) return 0;
		if (str_starts_with($this->checkout, '0000-00-00')) return 0;

		$work  = strtotime($this->checkout) - strtotime($this->checkin);
		$lunch = 0;

		if ($this->lunchin && $this->lunchout
			&& !str_starts_with($this->lunchin,  '0000-00-00')
			&& !str_starts_with($this->lunchout, '0000-00-00')
		) {
			$lunch = strtotime($this->lunchout) - strtotime($this->lunchin);
		} elseif ($this->lunchin && !str_starts_with($this->lunchin, '0000-00-00')) {
			// Обед начат, но не завершён — считаем 30 минут
			$lunch = 1800;
		}

		return max(0, $work - $lunch);
	}

	/**
	 * Рабочее время в минутах, округлённое до 5
	 */
	public function getWorkMinutesRoundedAttribute(): int
	{
		$minutes = (int)round($this->work_seconds / 60 / 5) * 5;

		return $minutes;
	}
}
