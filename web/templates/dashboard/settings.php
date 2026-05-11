<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

// Получим актуальные данные через API
$meResponse = $api->get('/auth/me');

if (!empty($meResponse['status']) && $meResponse['status'] === 401) {
	header('Location: /?route=login');
	exit;
}

if (!empty($meResponse['data'])) {
	$_SESSION['user_info'] = $meResponse['data'];
}

$user = $_SESSION['user_info'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Настройки — WorkBangers CRM</title>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">
	<script>
		const savedTheme = localStorage.getItem('theme') || 'dark';
		document.documentElement.setAttribute('data-theme', savedTheme);
		if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
	</script>
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>
		
		<!-- Подключаем sidebar -->
		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<!-- Подключаем topbar -->
			<?php $pageTitle = 'Настройки профиля'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="card" style="max-width: 600px; margin: 0 auto;">
					<h2 style="margin-bottom: 20px;">Ваши данные</h2>
					<form id="settingsForm">
						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="firstname">Имя</label>
							<input type="text" id="firstname" name="firstname" class="form-control" value="<?= htmlspecialchars($user['profile']['firstname'] ?? '') ?>">
						</div>
						
						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="lastname">Фамилия</label>
							<input type="text" id="lastname" name="lastname" class="form-control" value="<?= htmlspecialchars($user['profile']['lastname'] ?? '') ?>">
						</div>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="email">Email (Логин)</label>
							<input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
						</div>

						<div class="form-group" style="margin-bottom: 2rem;">
							<label for="phone">Телефон</label>
							<input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['profile']['phone'] ?? '') ?>">
						</div>

						<hr style="border: none; border-top: 1px solid var(--border-color); margin-bottom: 2rem;">
						<h3 style="margin-bottom: 1rem;">Внешний вид профиля</h3>

						<div class="form-group" style="margin-bottom: 2rem;">
							<label for="reset_password_validity">Аватар (эмодзи)</label>
							<input type="text" id="reset_password_validity" name="reset_password_validity" class="form-control" maxlength="10" placeholder="Например: 👨‍💻 или 🐯" value="<?= htmlspecialchars($user['reset_password_validity'] ?? '') ?>">
						</div>

						<hr style="border: none; border-top: 1px solid var(--border-color); margin-bottom: 2rem;">
						<h3 style="margin-bottom: 1rem;">Смена пароля</h3>
						<p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1rem;">Заполните, только если хотите изменить текущий пароль.</p>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="password">Новый пароль</label>
							<input type="password" id="password" name="password" class="form-control" placeholder="Минимум 8 символов">
						</div>

						<div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
							<button type="submit" class="btn btn-primary" id="saveBtn">Сохранить изменения</button>
						</div>
					</form>
				</div>
			</div>
		</main>
	</div>

	<script>
		// Общие функции сайдбара и темы
		const sidebar = document.getElementById('sidebar');
		const overlay = document.getElementById('sidebarOverlay');
		function toggleSidebarMobile() {
			sidebar.classList.toggle('open');
			overlay.classList.toggle('active');
		}
		function toggleSidebarCollapse() {
			const root = document.documentElement;
			const isCollapsed = root.getAttribute('data-sidebar') === 'collapsed';
			if (isCollapsed) root.removeAttribute('data-sidebar'); else root.setAttribute('data-sidebar', 'collapsed');
			localStorage.setItem('sidebarCollapsed', !isCollapsed ? 'true' : 'false');
			if (typeof map !== 'undefined' && map) setTimeout(() => map.invalidateSize(), 300);
		}
		function toggleTheme() {
			const current = document.documentElement.getAttribute('data-theme');
			const next = current === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			localStorage.setItem('theme', next);
		}

		// API Token
		const API_TOKEN = '<?= $_SESSION['api_token'] ?? '' ?>';

		document.getElementById('settingsForm').addEventListener('submit', async function(e) {
			e.preventDefault();
			const btn = document.getElementById('saveBtn');
			btn.disabled = true;
			btn.textContent = 'Сохранение...';

			const formData = {
				firstname: document.getElementById('firstname').value,
				lastname: document.getElementById('lastname').value,
				email: document.getElementById('email').value,
				phone: document.getElementById('phone').value,
				reset_password_validity: document.getElementById('reset_password_validity').value,
			};

			const password = document.getElementById('password').value;
			if (password) {
				formData.password = password;
			}

			try {
				const res = await fetch('/api/v1/auth/profile', {
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						'Authorization': `Bearer ${API_TOKEN}`
					},
					body: JSON.stringify(formData)
				});
				const json = await res.json();
				
				if (json.success) {
					alert('Профиль успешно обновлен!');
					document.getElementById('password').value = ''; // Очищаем поле пароля
					
					// Так как email мог измениться, нужно обновить данные сессии на бэкенде 
					// путем обновления страницы (чтобы сработал /auth/me)
					window.location.reload();
				} else {
					alert('Ошибка: ' + (json.error || json.message || 'Неизвестная ошибка'));
				}
			} catch(e) {
				alert('Сетевая ошибка');
			} finally {
				btn.disabled = false;
				btn.textContent = 'Сохранить изменения';
			}
		});
	</script>
</body>
</html>
