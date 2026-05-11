<?php
// Обработка POST-запроса
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'] ?? '';

	if ($email) {
		$response = $api->post('/auth/forgot-password', [
			'email' => $email
		]);

		if ($response['success'] ?? false) {
			$success = 'Инструкция по сбросу пароля отправлена на ваш E-mail.';
		} else {
			$error = $response['error'] ?? 'Не удалось найти пользователя с таким E-mail.';
		}
	} else {
		$error = 'Пожалуйста, введите ваш E-mail.';
	}
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Восстановление пароля — WorkBangers CRM</title>
	<script>document.documentElement.setAttribute('data-theme', 'dark');</script>
	<link rel="stylesheet" href="/public/css/style.css?v=1776834571">
	<link rel="stylesheet" href="/public/css/components.css?v=1776834571">

	<!-- Favicons & OpenGraph -->
	<link rel="apple-touch-icon" sizes="180x180" href="https://workbangers.com/wp-content/themes/workbangers/img/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-16x16.png">
	<link rel="shortcut icon" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon.ico">
	<meta name="theme-color" content="#5578B4" media="(prefers-color-scheme: dark)">
	<meta name="theme-color" content="#5578B4" media="(prefers-color-scheme: light)">
	<meta property="og:image" content="https://workbangers.com/wp-content/themes/workbangers/img/wb.png">
	<meta property="og:title" content="WorkBangers CRM">
	<meta property="og:description" content="All renovation works. Roofing and decking services.">
	<meta name="twitter:card" content="summary_large_image">
</head>
<body>
	<div class="auth-page">
		<div class="auth-card">
			<div class="auth-logo">
				<div class="auth-logo-icon">
					<img src="https://workbangers.com/wp-content/themes/workbangers/img/wb1.svg" style="width:100%; height:100%;" alt="logo">
				</div>
				<h1>Reset Password</h1>
			</div>

			<p class="auth-subtitle">
				Enter your email address and we'll send you instructions to reset your password.
			</p>

			<?php if ($error): ?>
				<div class="alert error"><?= htmlspecialchars($error) ?></div>
			<?php endif; ?>

			<?php if ($success): ?>
				<div class="alert success"><?= htmlspecialchars($success) ?></div>
				<div style="margin-top: 1.5rem; text-align: center;">
					<a href="/?route=login" class="auth-link">Вернуться ко входу</a>
				</div>
			<?php else: ?>
				<form method="POST" action="/?route=forgot-password" id="forgotForm">
					<div class="form-group input-group">
						<label for="email">Email</label>
						<input type="email" id="email" name="email" class="input-field" required autofocus placeholder="admin@example.com">
					</div>

					<button type="submit" class="btn-auth" id="submitBtn">
						<span>Send Instructions</span>
						<div class="spinner" id="spinner" style="display: none; width:20px; height:20px; border: 2px solid rgba(0,0,0,0.2); border-top-color:#000; border-radius:50%; animation: spin 1s linear infinite; margin-left: 10px;"></div>
					</button>

					<div style="margin-top: 1.5rem; text-align: center;">
						<a href="/?route=login" class="auth-link">Back to login</a>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>
	<script>
		var form = document.getElementById('forgotForm');
		if(form) {
			form.addEventListener('submit', function() {
				var btn = document.getElementById('submitBtn');
				var spinner = document.getElementById('spinner');
				btn.style.opacity = '0.8';
				btn.style.pointerEvents = 'none';
				spinner.style.display = 'block';
			});
		}
	</script>
</body>
</html>
