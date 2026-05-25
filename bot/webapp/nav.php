<?php
// Получаем имя текущего файла скрипта
$currentPage = basename($_SERVER['PHP_SELF']);
// Устанавливаем язык по умолчанию
$langParam = isset($userLanguage) ? $userLanguage : 'en';
// Версия приложения для сброса кэша статики
$versionParam = '1.0.34';

// Краткая функция для перевода в меню
function _menu($key) {
	return __('webapp_menu.'.$key);
}
?>
<style>
.bottom-nav {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	background: #fff;
	display: flex;
	box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
	padding: 0.5rem;
	gap: 0.5rem;
	z-index: 1000;
}
.bottom-nav::-webkit-scrollbar { display: none; }

.nav-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	background: none;
	border: none;
	padding: 0.5rem 0;
	color: #64748b;
	text-decoration: none;
	font-size: 0.75rem;
	border-radius: 8px;
	min-width: 60px;
	transition: all 0.2s ease;
}
.nav-item.active {
	color: #0b66ff;
	background: #f0f6ff;
	font-weight: 600;
}
.nav-item svg {
	width: 24px;
	height: 24px;
	margin-bottom: 4px;
	stroke-width: 1.8;
}
body { padding-bottom: 5rem !important; }
</style>

<!-- Контейнер нижней навигационной панели Mini App -->
<nav class="bottom-nav" id="bottomNav">
	<a href="wt.php?lang=<?= htmlspecialchars($langParam) ?>&v=<?= $versionParam ?>" class="nav-item <?= $currentPage == 'wt.php' ? 'active' : '' ?>">
		<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
		<span><?= _menu('worktime') ?></span>
	</a>
	<a href="wt_history.php?lang=<?= htmlspecialchars($langParam) ?>&v=<?= $versionParam ?>" class="nav-item <?= $currentPage == 'wt_history.php' ? 'active' : '' ?>">
		<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><polyline points="3 3 3 8 8 8"></polyline><polyline points="12 7 12 12 15 15"></polyline></svg>
		<span><?= _menu('history') ?></span>
	</a>
	<a href="wt_receipt.php?lang=<?= htmlspecialchars($langParam) ?>&v=<?= $versionParam ?>" class="nav-item <?= $currentPage == 'wt_receipt.php' ? 'active' : '' ?>">
		<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
		<span><?= _menu('receipts') ?></span>
	</a>
	<a href="wt_objects.php?lang=<?= htmlspecialchars($langParam) ?>&v=<?= $versionParam ?>" class="nav-item <?= $currentPage == 'wt_objects.php' ? 'active' : '' ?>" id="navAddObject" style="display:none;">
		<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
		<span><?= _menu('objects') ?></span>
	</a>
</nav>

<script>
// Навешиваем обработчики кликов с сохранением хэша URL
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.nav-item').forEach(function(link) {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			var href = this.getAttribute('href');
			if (window.location.hash) {
				href += window.location.hash;
			}
			window.location.href = href;
		});
	});
});
</script>
