<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Модель пользователя Telegram-бота (основная таблица bot_users)
 *
 * Вся аутентификация в API и Web-админке проходит через эту модель.
 */
class BotUser extends Authenticatable implements JWTSubject
{
	/**
	 * Таблица в БД
	 */
	protected $table = 'bot_users';

	/**
	 * Первичный ключ (мы используем id_telegram для связи, но id для Auth)
	 * Важно: Laravel по умолчанию ищет id. У bot_users также есть поле id (A.I.).
	 */
	protected $primaryKey = 'id';

	public $timestamps = false; // Таблица не имеет created_at и updated_at

	protected $guarded = []; // Разрешаем изменения

	/**
	 * Приведение типов
	 */
	protected $casts = [
		'id_telegram' => 'integer',
		'id_chat'     => 'integer',
		'admin'       => 'boolean',
		'tester'      => 'boolean',
		'menu_msg_id' => 'integer',
	];

	/**
	 * Скрытые поля (чтобы пароли не попадали в API-ответы)
	 */
	protected $hidden = [
		'password',
		'remember_token',
	];

	// ==========================================
	// JWT Auth Methods
	// ==========================================

	public function getJWTIdentifier()
	{
		return $this->getKey();
	}

	public function getJWTCustomClaims(): array
	{
		return [
			'email'       => $this->email,
			'telegram_id' => $this->id_telegram,
			'is_admin'    => $this->isAdmin(),
			'role'        => $this->isAdmin() ? 'admin' : 'user', // Имитируем роли для старого фронтенда
		];
	}

	// ==========================================
	// Helpers
	// ==========================================

	/**
	 * Роль пользователя
	 */
	public function role()
	{
		return $this->belongsTo(Role::class, 'role_id', 'id');
	}

	/**
	 * Компания пользователя
	 */
	public function company()
	{
		return $this->belongsTo(Company::class, 'company_slug', 'slug');
	}

	/**
	 * Получить полное имя
	 */
	public function getFullNameAttribute(): string
	{
		return trim($this->firstname . ' ' . $this->lastname);
	}

	/**
	 * Зарегистрирован ли пользователь (активный сотрудник)
	 */
	public function isRegistered(): bool
	{
		return in_array($this->status, ['registred', 'работает', 'обед']);
	}

	/**
	 * Уволен ли сотрудник
	 */
	public function isQuit(): bool
	{
		return $this->status === 'quit';
	}
	
	/**
	 * Находится ли в процессе регистрации
	 */
	public function isInProgress(): bool
	{
		return in_array($this->status, ['inprogress', 'new']);
	}

	/**
	 * Является ли администратором
	 */
	public function isAdmin(): bool
	{
		// Мы используем старый флаг admin или проверяем, является ли роль админской
		return ((bool)$this->admin) || ($this->role && $this->role->slug === 'admin');
	}

	/**
	 * Проверка прав доступа (простейшая)
	 */
	public function hasPermission(string $permission): bool
	{
		// Если старый флаг admin установлен, даем полные права
		if ((bool)$this->admin) {
			return true;
		}

		// Если привязана роль, проверяем ее массив permissions
		if ($this->role && is_array($this->role->permissions)) {
			return in_array($permission, $this->role->permissions);
		}

		return false;
	}
}
