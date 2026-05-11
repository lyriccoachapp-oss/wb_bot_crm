<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;
use RuntimeException;

/**
 * Сервис отправки email
 *
 * Использует SMTP Yandex через PHPMailer.
 * Конфигурация берётся из .env.
 */
class MailService
{
	/**
	 * Отправить письмо для сброса пароля
	 *
	 * @param  string $toEmail   Email получателя
	 * @param  string $resetUrl  Ссылка для сброса
	 *
	 * @throws RuntimeException
	 */
	public function sendPasswordReset(string $toEmail, string $resetUrl): void
	{
		$subject = 'WorkBangers CRM — Сброс пароля';

		$html = $this->buildPasswordResetHtml($resetUrl);
		$text = "Для сброса пароля перейдите по ссылке:\n{$resetUrl}\n\nСсылка действительна 1 час.";

		$this->send($toEmail, $subject, $html, $text);
	}

	/**
	 * Отправить произвольное письмо
	 *
	 * @param  string $toEmail
	 * @param  string $subject
	 * @param  string $htmlBody
	 * @param  string $textBody
	 *
	 * @throws RuntimeException
	 */
	public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): void
	{
		$mail = new PHPMailer(true);

		try {
			// Сервер
			$mail->isSMTP();
			$mail->Host       = gethostbyname('smtp.yandex.ru'); // Принудительное разрешение IPv4
			$mail->SMTPAuth   = true;
			$mail->Username   = config('mail.mailers.smtp.username');
			$mail->Password   = config('mail.mailers.smtp.password');
			$mail->SMTPSecure = config('mail.mailers.smtp.encryption') === 'ssl'
				? PHPMailer::ENCRYPTION_SMTPS
				: PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port       = (int)config('mail.mailers.smtp.port', 465);
			
			// Игнорируем несовпадение имени хоста, так как мы подставляем IP-адрес напрямую
			$mail->SMTPOptions = [
				'ssl' => [
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true
				]
			];

			// Таймауты для предотвращения 504 ошибки, если SMTP сервер не отвечает
			$mail->Timeout    = 5;
			$mail->SMTPDebug  = 0;

			// Кодировка
			$mail->CharSet = 'UTF-8';

			// Отправитель
			$mail->setFrom(
				config('mail.from.address'),
				config('mail.from.name')
			);

			// Получатель
			$mail->addAddress($toEmail);

			// Контент
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $htmlBody;
			$mail->AltBody = $textBody ?: strip_tags($htmlBody);

			$mail->send();
		} catch (\Exception $e) {
			Log::error('Ошибка отправки email', [
				'to'    => $toEmail,
				'error' => $e->getMessage(),
			]);
			throw new RuntimeException('Не удалось отправить письмо (SMTP не отвечает): ' . $e->getMessage());
		}
	}

	/**
	 * Сгенерировать HTML для письма сброса пароля
	 *
	 * @param  string $resetUrl
	 * @return string
	 */
	private function buildPasswordResetHtml(string $resetUrl): string
	{
		$escapedUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

		return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Сброс пароля</title>
</head>
<body style="margin:0;padding:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);overflow:hidden;">
        <!-- Шапка -->
        <tr>
          <td style="background:#1a1a2e;padding:32px 40px;text-align:center;">
            <h1 style="color:#e94560;margin:0;font-size:24px;letter-spacing:1px;">WorkBangers CRM</h1>
          </td>
        </tr>
        <!-- Тело -->
        <tr>
          <td style="padding:40px 40px 32px;">
            <h2 style="color:#1a1a2e;margin:0 0 16px;font-size:20px;">Сброс пароля</h2>
            <p style="color:#444;line-height:1.6;margin:0 0 24px;">
              Мы получили запрос на сброс пароля для вашей учётной записи.<br>
              Нажмите кнопку ниже, чтобы установить новый пароль.
            </p>
            <p style="text-align:center;margin:0 0 24px;">
              <a href="{$escapedUrl}"
                 style="display:inline-block;background:#e94560;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:16px;font-weight:bold;">
                Сбросить пароль
              </a>
            </p>
            <p style="color:#888;font-size:13px;margin:0 0 8px;">
              Ссылка действительна <strong>1 час</strong>.
            </p>
            <p style="color:#888;font-size:13px;margin:0;">
              Если вы не запрашивали сброс пароля — проигнорируйте это письмо.
            </p>
          </td>
        </tr>
        <!-- Подвал -->
        <tr>
          <td style="background:#f6f7f9;padding:20px 40px;text-align:center;">
            <p style="color:#aaa;font-size:12px;margin:0;">
              © 2026 WorkBangers CRM. Это автоматическое сообщение.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
	}
}
