<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

$user = $_SESSION['user_info'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Дашборд — WorkBangers CRM</title>
	<meta name="description" content="Главная панель управления WorkBangers CRM">
	<script>
		const savedTheme = localStorage.getItem('theme') || 'dark';
		document.documentElement.setAttribute('data-theme', savedTheme);
		if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
	</script>
	<link rel="stylesheet" href="/public/css/style.css?v=1776834571">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=1776834571">
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
	<div class="app-layout">
		<!-- Overlay для мобильного сайдбара -->
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>

		<!-- Sidebar — Боковая навигация -->
		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<!-- Основной контент -->
		<main class="main-content">
			<!-- Topbar -->
			<?php $pageTitle = 'Главная панель управления'; require __DIR__ . '/components/topbar.php'; ?>

			<!-- Содержимое страницы -->
			<div class="content-wrapper">
				<div class="card mb-3">
					<h2>Обзор системы</h2>
					<p class="text-muted mt-1">
						Вы успешно авторизовались. API токен получен и сохранен в сессии.
					</p>
				</div>
				
				<div class="dashboard-grid">
					<div class="stat-card">
						<h3>Объекты в работе</h3>
						<div class="stat-value">5</div>
					</div>
					<div class="stat-card">
						<h3>Сотрудники на смене</h3>
						<div class="stat-value">12</div>
					</div>
					<div class="stat-card">
						<h3>Чеки за сегодня</h3>
						<div class="stat-value">3</div>
					</div>
				</div>
			</div>
		</main>
	</div>

	<script>
		// --- Сворачивание сайдбара (Desktop) ---
		function toggleSidebarCollapse() {
			const root = document.documentElement;
			const isCollapsed = root.getAttribute('data-sidebar') === 'collapsed';
			if (isCollapsed) root.removeAttribute('data-sidebar'); else root.setAttribute('data-sidebar', 'collapsed');
			localStorage.setItem('sidebarCollapsed', !isCollapsed ? 'true' : 'false');
			if (typeof map !== 'undefined' && map) setTimeout(() => map.invalidateSize(), 300);
		}

		// Восстановление состояния сайдбара
		if (localStorage.getItem('sidebarCollapsed') === 'true') {
			document.getElementById('sidebar').classList.add('collapsed');
		}

		// --- Мобильный бургер (Mobile) ---
		function toggleSidebarMobile() {
			document.getElementById('sidebar').classList.toggle('open');
			document.getElementById('sidebarOverlay').classList.toggle('active');
		}

		// --- Переключение тем ---
		function toggleTheme() {
			const root = document.documentElement;
			const currentTheme = root.getAttribute('data-theme');
			const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', newTheme);
			localStorage.setItem('theme', newTheme);
			updateThemeIcon(newTheme);
		}

		function updateThemeIcon(theme) {
			const icon = document.getElementById('themeIcon');
			if (theme === 'dark') {
				icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'; // Sun
			} else {
				icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'; // Moon
			}
		}

		// Установка правильной иконки при загрузке
		updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'dark');
	</script>
</body>
</html>
