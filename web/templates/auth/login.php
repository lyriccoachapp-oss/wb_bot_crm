<?php
// Обработка POST-запроса на вход
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'] ?? '';
	$password = $_POST['password'] ?? '';

	if ($email && $password) {
		$response = $api->post('/auth/login', [
			'email' => $email,
			'password' => $password
		]);

		if ($response['success'] ?? false) {
			$api->setToken($response['data']['access_token'], $response['data']['refresh_token'] ?? null);
			$_SESSION['user_info'] = $response['data']['user'];
			header('Location: /?route=dashboard');
			exit;
		} else {
			$error = $response['error'] ?? 'Ошибка авторизации. Проверьте логин и пароль.';
		}
	} else {
		$error = 'Пожалуйста, заполните все поля.';
	}
}

// Определение языка
$availableLangs = ['ru', 'en', 'uk'];
$lang = 'en'; // default

if (isset($_GET['lang']) && in_array($_GET['lang'], $availableLangs)) {
    $lang = $_GET['lang'];
    setcookie('lang', $lang, time() + (86400 * 30), "/");
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $availableLangs)) {
    $lang = $_COOKIE['lang'];
} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $prefLocales = array_reduce(
        explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']), 
        function ($res, $el) { 
            list($l, $q) = array_merge(explode(';q=', $el), [1]); 
            $res[$l] = (float) $q; 
            return $res; 
        }, []);
    arsort($prefLocales);
    foreach ($prefLocales as $l => $q) {
        $l = substr($l, 0, 2);
        if (in_array($l, $availableLangs)) {
            $lang = $l;
            break;
        }
    }
}

// Запрашиваем контент из публичного API
$contentBlocksResponse = $api->get('/content');
$contentBlocks = $contentBlocksResponse['data'] ?? [];

function t($key, $lang, $blocks) {
    if (isset($blocks[$key]['content'][$lang])) {
        return htmlspecialchars($blocks[$key]['content'][$lang]);
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Вход — WorkBangers CRM</title>
	<meta name="description" content="Авторизация в системе управления WorkBangers CRM">
	<script>
		// Принудительно устанавливаем темную тему для страницы авторизации, 
		// как на референсе loginform-01
		document.documentElement.setAttribute('data-theme', 'dark');
	</script>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">

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
	<div class="auth-page" style="position: relative;">
		<div class="lang-switcher" style="position: absolute; top: 1rem; right: 1rem; z-index: 20;">
			<select onchange="window.location.href='/?route=login&lang='+this.value" style="background: rgba(0,0,0,0.5); color: #fff; border: 1px solid rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 4px; outline: none; cursor: pointer;">
				<option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>EN</option>
				<option value="ru" <?= $lang === 'ru' ? 'selected' : '' ?>>RU</option>
				<option value="uk" <?= $lang === 'uk' ? 'selected' : '' ?>>UK</option>
			</select>
		</div>

		<div class="auth-card">
			<!-- Логотип (по центру) -->
			<div class="auth-logo" style="justify-content: center; margin-bottom: 2rem;">
				<img src="/public/img/wb1.svg" style="height: 48px; width: auto;" alt="logo">
			</div>

			<p class="auth-subtitle">
				<?= t('login_subtitle', $lang, $contentBlocks) ?>
			</p>

			<?php if ($error): ?>
				<div class="alert error"><?= htmlspecialchars($error) ?></div>
			<?php endif; ?>

			<!-- Форма входа -->
			<form method="POST" action="/?route=login" id="loginForm">
				<div class="form-floating">
					<input type="email" id="email" name="email" class="form-control" required autofocus autocomplete="email" placeholder="admin@example.com">
					<label for="email"><?= t('email_label', $lang, $contentBlocks) ?: 'Email' ?></label>
				</div>

				<div class="form-floating" style="margin-bottom: 0.5rem;">
					<input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" placeholder="••••••••">
					<label for="password"><?= t('password', $lang, $contentBlocks) ?: 'Password' ?></label>
				</div>

				<!-- Ссылка на восстановление пароля -->
				<div style="text-align: right; margin-bottom: 1.5rem; font-size: 0.85rem;">
					<a href="/?route=forgot-password" style="color: var(--primary-color); text-decoration: none;"><?= t('forgot_password', $lang, $contentBlocks) ?: 'Forgot password?' ?></a>
				</div>

				<button type="submit" class="btn-auth" id="submitBtn" style="background-color: var(--primary-color); color: #fff;">
					<span><?= t('sign_in', $lang, $contentBlocks) ?: 'Sign in' ?></span>
					<div class="spinner" id="spinner" style="display: none; width:20px; height:20px; border: 2px solid rgba(255,255,255,0.2); border-top-color:#fff; border-radius:50%; animation: spin 1s linear infinite; margin-left: 10px;"></div>
				</button>
			</form>

			<div style="display: flex; align-items: center; margin: 1.5rem 0; color: #666; font-size: 0.85rem;">
				<hr style="flex: 1; border-color: #444; border-style: solid; border-width: 1px 0 0 0;">
				<span style="padding: 0 10px;"><?= t('or_separator', $lang, $contentBlocks) ?: 'or' ?></span>
				<hr style="flex: 1; border-color: #444; border-style: solid; border-width: 1px 0 0 0;">
			</div>

			<!-- Кнопка Google (Заглушка) -->
			<button type="button" class="btn-auth" style="background-color: #fff; color: #000; margin-bottom: 1rem;">
				<svg style="width: 18px; height: 18px; margin-right: 8px;" viewBox="0 0 24 24">
					<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
					<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
					<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
					<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
				</svg>
				<?= t('continue_google', $lang, $contentBlocks) ?: 'Continue with Google' ?>
			</button>
			
			<div class="auth-footer-text">
				<?= t('agree_terms', $lang, $contentBlocks) ?: 'By continuing, you agree to the ' ?><a href="#" onclick="openModal('termsModal'); return false;"><?= t('terms_link', $lang, $contentBlocks) ?: 'Terms and Conditions' ?></a><?= t('and_word', $lang, $contentBlocks) ?: ' and ' ?><a href="#" onclick="openModal('privacyModal'); return false;"><?= t('privacy_link', $lang, $contentBlocks) ?: 'Privacy Policy' ?></a>.
			</div>
		</div>
	</div>

	<!-- Модальные окна -->
	<div id="termsModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
		<div style="background: var(--bg-card, #222); padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; position: relative; color: var(--text-main, #fff); box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
			<span onclick="closeModal('termsModal')" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:1.5rem; color: #888;">&times;</span>
			<h2 style="margin-top:0;">Terms and Conditions</h2>
			<p style="font-size: 0.95rem; line-height: 1.6; color: #ccc;"><?= nl2br(t('terms', $lang, $contentBlocks)) ?></p>
		</div>
	</div>

	<div id="privacyModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
		<div style="background: var(--bg-card, #222); padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; position: relative; color: var(--text-main, #fff); box-shadow: 0 4px 20px rgba(0,0,0,0.5);">
			<span onclick="closeModal('privacyModal')" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:1.5rem; color: #888;">&times;</span>
			<h2 style="margin-top:0;">Privacy Policy</h2>
			<p style="font-size: 0.95rem; line-height: 1.6; color: #ccc;"><?= nl2br(t('privacy', $lang, $contentBlocks)) ?></p>
		</div>
	</div>

	<style>
		@keyframes spin { 100% { transform: rotate(360deg); } }
	</style>

	<script>
		document.getElementById('loginForm').addEventListener('submit', function() {
			var btn = document.getElementById('submitBtn');
			var spinner = document.getElementById('spinner');
			
			btn.style.opacity = '0.8';
			btn.style.pointerEvents = 'none';
			// btn.querySelector('span').style.display = 'none'; // Оставляем текст
			spinner.style.display = 'block';
		});

		// Функционал модалок
		function openModal(id) {
			var el = document.getElementById(id);
			if(el) {
				el.style.display = 'flex';
			}
		}
		function closeModal(id) {
			var el = document.getElementById(id);
			if(el) {
				el.style.display = 'none';
			}
		}
		// Закрытие при клике вне окна
		window.onclick = function(event) {
			if (event.target.classList.contains('modal-overlay')) {
				event.target.style.display = "none";
			}
		}
	</script>
</body>
</html>
