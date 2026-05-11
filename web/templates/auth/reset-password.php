<?php
$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
	header('Location: /?route=login');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$password = $_POST['password'] ?? '';
	$password_confirmation = $_POST['password_confirmation'] ?? '';

	if ($password && $password === $password_confirmation) {
		$response = $api->post('/auth/reset-password', [
			'token' => $token,
			'password' => $password,
			'password_confirmation' => $password_confirmation
		]);

		if ($response['success'] ?? false) {
			$success = 'Пароль успешно изменен. Теперь вы можете войти.';
		} else {
			$error = $response['error'] ?? 'Неверный или устаревший токен сброса.';
		}
	} else {
		$error = 'Пароли не совпадают или не заполнены.';
	}
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Новый пароль — WorkBangers CRM</title>
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
				<h1>Set New Password</h1>
			</div>

			<p class="auth-subtitle">
				Please enter your new password below.
			</p>

			<?php if ($error): ?>
				<div class="alert error"><?= htmlspecialchars($error) ?></div>
			<?php endif; ?>

			<?php if ($success): ?>
				<div class="alert success"><?= htmlspecialchars($success) ?></div>
				<div style="margin-top: 1.5rem; text-align: center;">
					<a href="/?route=login" class="btn-auth" style="text-decoration:none; display:inline-block; padding:0.85rem 2rem;">Go to Login</a>
				</div>
			<?php else: ?>
				<form method="POST" action="/?route=reset-password&token=<?= htmlspecialchars($token) ?>" id="resetForm">
					<div class="form-group input-group">
						<label for="password">New Password</label>
						<input type="password" id="password" name="password" class="input-field" required minlength="6" placeholder="••••••••">
					</div>

					<div class="form-group input-group">
						<label for="password_confirmation">Confirm Password</label>
						<input type="password" id="password_confirmation" name="password_confirmation" class="input-field" required minlength="6" placeholder="••••••••">
					</div>

					<button type="submit" class="btn-auth" id="submitBtn">
						<span>Change Password</span>
						<div class="spinner" id="spinner" style="display: none; width:20px; height:20px; border: 2px solid rgba(0,0,0,0.2); border-top-color:#000; border-radius:50%; animation: spin 1s linear infinite; margin-left: 10px;"></div>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>
	<script>
		var form = document.getElementById('resetForm');
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
