<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Сервис интеграции с Google Drive
 *
 * Реализует те же функции, что и старый tob_gdrive.php:
 * - NewFolder  → createFolder()
 * - EditFolder → renameFolder()
 * - UpFile     → uploadFile()
 *
 * Авторизация через Service Account (JSON-ключ).
 * Конфигурация через .env:
 *   GOOGLE_APPLICATION_CREDENTIALS — путь к JSON
 *   GDRIVE_ROOT_DIR                — ID корневой папки объектов
 *   GDRIVE_RECEIPT_DIR             — ID папки чеков
 */
class GoogleDriveService
{
	/**
	 * Google Drive API клиент
	 */
	private ?Drive $drive = null;

	/**
	 * Создать и вернуть инициализированный Drive клиент
	 *
	 * @throws RuntimeException
	 */
	private function getDrive(): Drive
	{
		if ($this->drive) {
			return $this->drive;
		}

		$credPath = config('services.gdrive.credentials');

		if (!$credPath || !file_exists($credPath)) {
			throw new RuntimeException(
				'Google Drive: файл credentials не найден: ' . ($credPath ?? 'не указан')
			);
		}

		$client = new Client();
		$client->setAuthConfig($credPath);
		$client->addScope('https://www.googleapis.com/auth/drive');

		$this->drive = new Drive($client);

		return $this->drive;
	}

	/**
	 * Создать папку в Google Drive
	 *
	 * Аналог NewFolder() из tob_gdrive.php.
	 *
	 * @param  string      $name       Название папки
	 * @param  string      $description Описание
	 * @param  string|null $parentId   ID родительской папки (по умолчанию — корень объектов)
	 * @return string|null             ID созданной папки или null при ошибке
	 */
	public function createFolder(string $name, string $description = '', ?string $parentId = null): ?string
	{
		$parentId = $parentId ?? config('services.gdrive.root_dir');

		try {
			$metadata = new DriveFile([
				'name'        => $name,
				'parents'     => [$parentId],
				'description' => $description,
				'mimeType'    => 'application/vnd.google-apps.folder',
			]);

			$file = $this->getDrive()->files->create($metadata, ['fields' => 'id']);

			Log::info('GDrive: папка создана', ['name' => $name, 'id' => $file->id]);

			return $file->id;

		} catch (\Exception $e) {
			Log::error('GDrive: ошибка создания папки', [
				'name'  => $name,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	/**
	 * Переименовать/обновить папку в Google Drive
	 *
	 * Аналог EditFolder() из tob_gdrive.php.
	 *
	 * @param  string $folderId   ID папки
	 * @param  string $newName    Новое название
	 * @param  string $description Новое описание
	 * @return bool
	 */
	public function renameFolder(string $folderId, string $newName, string $description = ''): bool
	{
		try {
			$metadata = new DriveFile([
				'name'        => $newName,
				'description' => $description,
				'mimeType'    => 'application/vnd.google-apps.folder',
			]);

			$this->getDrive()->files->update($folderId, $metadata);

			Log::info('GDrive: папка переименована', ['id' => $folderId, 'name' => $newName]);

			return true;

		} catch (\Exception $e) {
			Log::error('GDrive: ошибка переименования папки', [
				'id'    => $folderId,
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

	/**
	 * Загрузить файл на Google Drive
	 *
	 * Аналог UpFile() из tob_gdrive.php.
	 *
	 * @param  string      $filePath   Локальный путь к файлу
	 * @param  string      $name       Имя файла на Drive
	 * @param  string      $description Описание
	 * @param  string|null $parentId   ID папки (по умолчанию — папка чеков)
	 * @param  string      $mimeType   MIME-тип файла
	 * @return string|null             ID загруженного файла или null при ошибке
	 */
	public function uploadFile(
		string $filePath,
		string $name,
		string $description = '',
		?string $parentId = null,
		string $mimeType = 'image/jpeg'
	): ?string {
		$parentId = $parentId ?? config('services.gdrive.receipt_dir');

		if (!file_exists($filePath)) {
			Log::error('GDrive: файл не найден', ['path' => $filePath]);
			return null;
		}

		try {
			$metadata = new DriveFile([
				'name'        => $name,
				'parents'     => [$parentId],
				'description' => $description,
			]);

			$content = file_get_contents($filePath);

			$file = $this->getDrive()->files->create($metadata, [
				'data'       => $content,
				'mimeType'   => $mimeType,
				'uploadType' => 'multipart',
				'fields'     => 'id',
			]);

			Log::info('GDrive: файл загружен', ['name' => $name, 'id' => $file->id]);

			return $file->id;

		} catch (\Exception $e) {
			Log::error('GDrive: ошибка загрузки файла', [
				'name'  => $name,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	/**
	 * Удалить файл с Google Drive
	 *
	 * @param  string $fileId ID файла на Drive
	 * @return bool
	 */
	public function deleteFile(string $fileId): bool
	{
		try {
			$this->getDrive()->files->delete($fileId);
			Log::info('GDrive: файл удален', ['id' => $fileId]);
			return true;
		} catch (\Exception $e) {
			Log::error('GDrive: ошибка удаления файла', [
				'id'    => $fileId,
				'error' => $e->getMessage(),
			]);
			return false;
		}
	}

	/**
	 * Скачать файл с Google Drive
	 *
	 * @param  string $fileId ID файла на Drive
	 * @return string|null    Контент файла
	 */
	public function downloadFile(string $fileId): ?string
	{
		try {
			$response = $this->getDrive()->files->get($fileId, ['alt' => 'media']);
			return $response->getBody()->getContents();
		} catch (\Exception $e) {
			Log::error('GDrive: ошибка скачивания файла', [
				'id'    => $fileId,
				'error' => $e->getMessage(),
			]);
			return null;
		}
	}

	/**
	 * Получить публичную ссылку на файл
	 *
	 * @param  string $fileId  ID файла на Drive
	 * @return string
	 */
	public function getFileUrl(string $fileId): string
	{
		return "https://drive.google.com/file/d/{$fileId}/view";
	}

	/**
	 * Получить ссылку для встраивания превью
	 *
	 * @param  string $fileId
	 * @return string
	 */
	public function getPreviewUrl(string $fileId): string
	{
		return "https://drive.google.com/thumbnail?id={$fileId}&sz=w400";
	}

	/**
	 * Проверить доступность Drive (для health-check)
	 *
	 * @return bool
	 */
	public function isAvailable(): bool
	{
		try {
			$this->getDrive()->files->listFiles(['pageSize' => 1, 'fields' => 'files(id)']);
			return true;
		} catch (\Exception) {
			return false;
		}
	}
}
