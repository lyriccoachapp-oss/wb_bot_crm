<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель чека
 *
 * Маппинг таблицы bot_receipts.
 * Структуру таблицы НЕ изменять!
 *
 * Поля: id_receipt, id_telegram, id_place, receipt_date, receipt_time,
 *        merchant_name, merchant_address, receipt_amount, receipt_type,
 *        payment_method, card_last4, gdrive_id, items_json, ocr_text,
 *        currency, subtotal, tax
 */
class BotReceipt extends Model
{
	protected $table      = 'bot_receipts';
	protected $primaryKey = 'id_receipt';
	public $timestamps    = false;

	protected $fillable = [
		'id_telegram',
		'id_place',
		'receipt_date',
		'receipt_time',
		'merchant_name',
		'merchant_address',
		'receipt_amount',
		'receipt_type',
		'payment_method',
		'card_last4',
		'gdrive_id',
		'amount_subtotal',
		'amount_tax',
		'receipt_org',
		'comment',
	];

	/**
	 * Приведение типов
	 */
	protected $casts = [
		'id_telegram'    => 'integer',
		'id_place'       => 'integer',
		'receipt_amount' => 'float',
		'amount_subtotal'=> 'float',
		'amount_tax'     => 'float',
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
	 * Ссылка на Google Drive
	 */
	public function getGdriveUrlAttribute(): ?string
	{
		if (!$this->gdrive_id) return null;

		return "https://drive.google.com/file/d/{$this->gdrive_id}/view";
	}
}
