<?php

namespace App\Services;

use App\Models\BotWorktime;
use App\Repositories\BotWorktimeRepository;
use App\Repositories\BotUserRepository;
use Carbon\Carbon;
use RuntimeException;

/**
 * Сервис учёта рабочего времени
 *
 * Логика check-in, check-out, обеда, геолокации.
 */
class TimeEntryService
{
	public function __construct(
		private readonly BotWorktimeRepository $worktimeRepo,
		private readonly BotUserRepository     $userRepo
	) {
	}

	/**
	 * Check-in — начало рабочего дня
	 *
	 * @param  int   $telegramId
	 * @param  array $data  [place_id, latitude, longitude]
	 * @return BotWorktime
	 *
	 * @throws RuntimeException
	 */
	public function checkIn(int $telegramId, array $data): BotWorktime
	{
		$today = Carbon::now()->format('Y-m-d');
		$now   = Carbon::now()->toDateTimeString();

		// Проверяем, нет ли уже открытого дня
		$existing = $this->worktimeRepo->findTodayActiveEntry($telegramId, $today);

		if ($existing) {
			throw new RuntimeException('Рабочий день уже начат и не завершён.');
		}

		$worktime = $this->worktimeRepo->create([
			'id_telegram' => $telegramId,
			'id_place'    => $data['place_id'] ?? null,
			'workday'     => $today,
			'checkin'     => $now,
		]);

		if (!empty($data['latitude']) && !empty($data['longitude'])) {
			\Illuminate\Support\Facades\DB::table('bot_wp_tracking')->insert([
				'id_worktime' => $worktime->id_worktime,
				'loc_time'    => $now,
				'latitude'    => $data['latitude'],
				'longitude'   => $data['longitude'],
			]);
		}

		return $worktime;
	}

	/**
	 * Check-out — завершение рабочего дня
	 *
	 * @param  int   $telegramId
	 * @param  array $data  [work_desc]
	 * @return BotWorktime
	 *
	 * @throws RuntimeException
	 */
	public function checkOut(int $telegramId, array $data): BotWorktime
	{
		$now   = Carbon::now()->toDateTimeString();

		if (!empty($data['id'])) {
			$entry = $this->worktimeRepo->findById((int)$data['id']);
			if ($entry && $entry->id_telegram !== $telegramId) {
				throw new RuntimeException('Нет доступа к этой смене.');
			}
		} else {
			$today = Carbon::now()->format('Y-m-d');
			$entry = $this->worktimeRepo->findTodayActiveEntry($telegramId, $today);
		}

		if (!$entry) {
			throw new RuntimeException('Нет активной смены для завершения.');
		}

		if (str_starts_with((string)$entry->checkout, '0000-00-00') === false
			&& $entry->checkout
			&& $entry->checkout !== '0000-00-00 00:00:00'
		) {
			throw new RuntimeException('Рабочий день уже завершён.');
		}

		if (!$entry->checkin || str_starts_with((string)$entry->checkin, '0000-00-00')) {
			throw new RuntimeException('Сначала выполните check-in.');
		}

		$this->worktimeRepo->update($entry, [
			'checkout' => $now,
			'workdone' => $data['work_desc'] ?? null,
		]);

		if (!empty($data['latitude']) && !empty($data['longitude'])) {
			\Illuminate\Support\Facades\DB::table('bot_wp_tracking')->insert([
				'id_worktime' => $entry->id_worktime,
				'loc_time'    => $now,
				'latitude'    => $data['latitude'],
				'longitude'   => $data['longitude'],
			]);
		}

		return $entry;
	}

	/**
	 * Начало обеда
	 *
	 * @param  int $telegramId
	 * @return BotWorktime
	 *
	 * @throws RuntimeException
	 */
	public function lunchIn(int $telegramId): BotWorktime
	{
		$today = Carbon::now()->format('Y-m-d');
		$now   = Carbon::now()->toDateTimeString();

		$entry = $this->getActiveEntry($telegramId, $today);

		if ($entry->lunchin && !str_starts_with((string)$entry->lunchin, '0000-00-00')) {
			throw new RuntimeException('Обед уже начат.');
		}

		return $this->worktimeRepo->update($entry, ['lunchin' => $now]);
	}

	/**
	 * Конец обеда
	 *
	 * @param  int $telegramId
	 * @return BotWorktime
	 *
	 * @throws RuntimeException
	 */
	public function lunchOut(int $telegramId): BotWorktime
	{
		$today = Carbon::now()->format('Y-m-d');
		$now   = Carbon::now()->toDateTimeString();

		$entry = $this->getActiveEntry($telegramId, $today);

		if (!$entry->lunchin || str_starts_with((string)$entry->lunchin, '0000-00-00')) {
			throw new RuntimeException('Обед не начат.');
		}

		if ($entry->lunchout && !str_starts_with((string)$entry->lunchout, '0000-00-00')) {
			throw new RuntimeException('Обед уже завершён.');
		}

		return $this->worktimeRepo->update($entry, ['lunchout' => $now]);
	}

	/**
	 * Сохранить геолокацию для записи
	 *
	 * @param  int   $entryId
	 * @param  float $latitude
	 * @param  float $longitude
	 * @return BotWorktime
	 *
	 * @throws RuntimeException
	 */
	public function saveLocation(int $entryId, float $latitude, float $longitude): BotWorktime
	{
		$entry = $this->worktimeRepo->findById($entryId);

		if (!$entry) {
			throw new RuntimeException('Запись не найдена.');
		}

		return $this->worktimeRepo->update($entry, [
			'latitude'  => $latitude,
			'longitude' => $longitude,
		]);
	}

	/**
	 * Получить активную запись (checkin есть, checkout нет)
	 *
	 * @throws RuntimeException
	 */
	private function getActiveEntry(int $telegramId, string $date): BotWorktime
	{
		$entry = $this->worktimeRepo->findTodayActiveEntry($telegramId, $date);

		if (!$entry) {
			throw new RuntimeException('Нет активной смены (или она уже завершена).');
		}

		if ($entry->checkout && !str_starts_with((string)$entry->checkout, '0000-00-00')) {
			throw new RuntimeException('Рабочий день уже завершён.');
		}

		return $entry;
	}
}
