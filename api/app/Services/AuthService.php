<?php

namespace App\Services;

use App\Models\BotUser;
use App\Models\CrmToken;
use App\Repositories\BotUserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use RuntimeException;
use Carbon\Carbon;

/**
 * Сервис авторизации
 *
 * Отвечает за JWT-авторизацию, регистрацию через Telegram,
 * refresh-токены и сброс пароля.
 */
class AuthService
{
	public function __construct(
		private readonly BotUserRepository $userRepo,
		private readonly MailService       $mailService
	) {
	}

	/**
	 * Авторизация по email/паролю
	 *
	 * @param  string $email
	 * @param  string $password
	 * @return array  [access_token, token_type, expires_in, user]
	 *
	 * @throws RuntimeException
	 */
	public function login(string $email, string $password): array
	{
		$user = $this->userRepo->findByEmail($email);

		if (!$user || $user->deleted || $user->isQuit() || $user->isInProgress() || !$user->password) {
			throw new RuntimeException('Неверный email или пароль либо аккаунт не активен.');
		}

		if (!Hash::check($password, $user->password)) {
			throw new RuntimeException('Неверный email или пароль.');
		}

		return $this->issueTokens($user);
	}

	/**
	 * Авторизация через Telegram (по telegram_id)
	 *
	 * Если пользователь CRM не найден — создаём его автоматически
	 * из bot_users (если таковой зарегистрирован).
	 *
	 * @param  int    $telegramId
	 * @param  string $hash       HMAC-SHA256 проверка от Telegram
	 * @param  array  $authData   Данные от Telegram Login Widget
	 * @return array
	 *
	 * @throws RuntimeException
	 */
	public function loginWithTelegram(int $telegramId, string $hash, array $authData): array
	{
		// Проверяем подпись Telegram
		$this->verifyTelegramHash($hash, $authData);

		$botUser = $this->userRepo->findByTelegramId($telegramId);

		if (!$botUser || !$botUser->isRegistered()) {
			throw new RuntimeException('Пользователь не зарегистрирован в системе.');
		}

		if ($botUser->deleted || $botUser->isQuit()) {
			throw new RuntimeException('Пользователь заблокирован (удален или уволен).');
		}

		if ($botUser->isInProgress()) {
			throw new RuntimeException('Регистрация пользователя еще не завершена.');
		}

		return $this->issueTokens($botUser);
	}

	/**
	 * Обновить access-токен по refresh-токену
	 *
	 * @param  string $refreshToken
	 * @return array
	 *
	 * @throws RuntimeException
	 */
	public function refresh(string $refreshToken): array
	{
		$hash = hash('sha256', $refreshToken);

		$tokenRecord = CrmToken::where('token_hash', $hash)->first();

		if (!$tokenRecord || $tokenRecord->isExpired()) {
			// Удаляем устаревший токен
			$tokenRecord?->delete();
			throw new RuntimeException('Недействительный или истёкший refresh-токен.');
		}

		$user = $this->userRepo->findById($tokenRecord->user_id);

		if (!$user || $user->deleted || $user->isQuit()) {
			throw new RuntimeException('Пользователь не найден или заблокирован.');
		}

		// Удаляем старый refresh-токен (ротация)
		$tokenRecord->delete();

		return $this->issueTokens($user);
	}

	/**
	 * Выйти из системы (отозвать токены)
	 *
	 * @param  CrmUser $user
	 */
	public function logout(BotUser $user): void
	{
		try {
			JWTAuth::invalidate(JWTAuth::getToken());
		} catch (JWTException) {
			// Игнорируем — токен уже недействителен
		}

		// Удаляем все refresh-токены пользователя
		CrmToken::where('user_id', $user->id)->delete();
	}

	/**
	 * Запрос сброса пароля
	 *
	 * @param  string $email
	 *
	 * @throws RuntimeException
	 */
	public function forgotPassword(string $email): void
	{
		$user = $this->userRepo->findByEmail($email);

		// Не раскрываем информацию о существовании email
		if (!$user) return;

		// Генерируем токен сброса
		$token     = Str::random(64);
		$tokenHash = hash('sha256', $token);

		// Сохраняем (с TTL 1 час)
		\DB::table('crm_password_resets')->insert([
			'email'      => $email,
			'token_hash' => $tokenHash,
			'expires_at' => Carbon::now()->addHour(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]);

		// Ссылка для сброса на Web-админку
		$resetUrl = config('app.url') . '/?route=reset-password&token=' . $token;

		// Отправляем письмо
		$this->mailService->sendPasswordReset($email, $resetUrl);
	}

	/**
	 * Сброс пароля
	 *
	 * @param  string $token
	 * @param  string $newPassword
	 *
	 * @throws RuntimeException
	 */
	public function resetPassword(string $token, string $newPassword): void
	{
		$tokenHash = hash('sha256', $token);

		$record = \DB::table('crm_password_resets')
			->where('token_hash', $tokenHash)
			->where('expires_at', '>', Carbon::now())
			->first();

		if (!$record) {
			throw new RuntimeException('Недействительная или истёкшая ссылка для сброса пароля.');
		}

		$user = $this->userRepo->findByEmail($record->email);

		if (!$user) {
			throw new RuntimeException('Пользователь не найден.');
		}

		// Обновляем пароль
		$this->userRepo->update($user, ['password' => Hash::make($newPassword)]);

		// Удаляем использованный токен
		\DB::table('crm_password_resets')->where('token_hash', $tokenHash)->delete();
	}

	/**
	 * Выдать access + refresh токены
	 *
	 * @param  CrmUser $user
	 * @return array
	 */
	private function issueTokens(BotUser $user): array
	{
		// Access token (JWT)
		$accessToken = JWTAuth::fromUser($user);
		$ttl         = (int)config('jwt.ttl', 60); // минуты

		// Refresh token
		$refreshToken = Str::random(80);
		$refreshHash  = hash('sha256', $refreshToken);

		CrmToken::create([
			'user_id'    => $user->id,
			'token_hash' => $refreshHash,
			'expires_at' => Carbon::now()->addMinutes((int)config('jwt.refresh_ttl', 20160)),
			'ip'         => request()->ip(),
			'user_agent' => substr(request()->userAgent() ?? '', 0, 500),
		]);

		return [
			'access_token'  => $accessToken,
			'token_type'    => 'bearer',
			'expires_in'    => $ttl * 60,
			'refresh_token' => $refreshToken,
			'user'          => [
				'id'          => $user->id,
				'email'       => $user->email,
				'name'        => trim($user->firstname . ' ' . $user->lastname),
				'telegram_id' => $user->id_telegram,
				'role'        => $user->isAdmin() ? 'admin' : 'user',
				'reset_password_validity' => $user->reset_password_validity,
			],
		];
	}

	/**
	 * Проверить HMAC-подпись данных от Telegram
	 *
	 * @param  string $hash
	 * @param  array  $data
	 *
	 * @throws RuntimeException
	 */
	private function verifyTelegramHash(string $hash, array $data): void
	{
		$token  = config('services.telegram.token');
		
		$isWebApp = isset($data['query_id']) || isset($data['chat_type']) || isset($data['chat_instance']);
		if ($isWebApp) {
			$secret = hash_hmac('sha256', $token, 'WebAppData', true);
		} else {
			$secret = hash('sha256', $token, true);
		}

		// Строка для проверки: ключи отсортированы и соединены \n
		$checkArr = $data;
		unset($checkArr['hash']);
		ksort($checkArr);

		$checkString = implode("\n", array_map(
			fn ($k, $v) => "$k=$v",
			array_keys($checkArr),
			array_values($checkArr)
		));

		$expectedHash = hash_hmac('sha256', $checkString, $secret);

		if (!hash_equals($expectedHash, $hash)) {
			throw new RuntimeException('Приватная авторизация Telegram не прошла проверку.');
		}

		// Проверяем, что данные не устарели (1 день)
		if (isset($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) {
			throw new RuntimeException('Данные авторизации Telegram устарели.');
		}
	}
}
