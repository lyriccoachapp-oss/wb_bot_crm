<?php

namespace App\Repositories;

use App\Models\BotReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Repository для чеков
 *
 * Читает и записывает в таблицу bot_receipts.
 */
class BotReceiptRepository
{
	/**
	 * Получить чеки с фильтрами и пагинацией
	 *
	 * @param  array $filters  [telegram_id, place_id, date_from, date_to]
	 * @param  int   $page
	 * @param  int   $limit
	 * @return LengthAwarePaginator
	 */
	public function paginate(array $filters = [], int $page = 1, int $limit = 20): LengthAwarePaginator
	{
		$query = BotReceipt::with(['place', 'botUser']);

		$this->applyFilters($query, $filters);

		return $query->orderBy('receipt_date', 'desc')
			->orderBy('id_receipt', 'desc')
			->paginate($limit, ['*'], 'page', $page);
	}

	/**
	 * Найти чек по ID
	 */
	public function findById(int $id): ?BotReceipt
	{
		return BotReceipt::with(['place', 'botUser'])->find($id);
	}

	/**
	 * Создать чек
	 *
	 * @param  array $data
	 * @return BotReceipt
	 */
	public function create(array $data): BotReceipt
	{
		return BotReceipt::create($data);
	}

	/**
	 * Обновить чек (после OCR сохранения)
	 *
	 * @param  BotReceipt $receipt
	 * @param  array      $data
	 * @return BotReceipt
	 */
	public function update(BotReceipt $receipt, array $data): BotReceipt
	{
		$receipt->update($data);

		return $receipt->fresh(['place', 'botUser']);
	}

	/**
	 * Получить чеки для отчёта
	 *
	 * @param  array $filters
	 * @return Collection
	 */
	public function getForReport(array $filters = []): Collection
	{
		$query = BotReceipt::with(['place', 'botUser']);

		$this->applyFilters($query, $filters);

		return $query->orderBy('receipt_date')
			->orderBy('id_receipt')
			->get();
	}

	/**
	 * Применить фильтры к запросу
	 */
	private function applyFilters(Builder $query, array $filters): void
	{
		if (!empty($filters['telegram_id'])) {
			if (is_array($filters['telegram_id'])) {
				$query->whereIn('id_telegram', $filters['telegram_id']);
			} else {
				$query->where('id_telegram', $filters['telegram_id']);
			}
		}

		if (!empty($filters['place_id'])) {
			if (is_array($filters['place_id'])) {
				$query->whereIn('id_place', $filters['place_id']);
			} else {
				$query->where('id_place', $filters['place_id']);
			}
		}

		if (!empty($filters['date_from'])) {
			$query->where('receipt_date', '>=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$query->where('receipt_date', '<=', $filters['date_to']);
		}
	}
}
