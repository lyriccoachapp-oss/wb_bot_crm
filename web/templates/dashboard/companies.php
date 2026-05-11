<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

$user = $_SESSION['user_info'];

// Получаем список компаний через API
$response = $api->get('/companies');
$companies = $response['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Компании — WorkBangers CRM</title>
	<script>
		const savedTheme = localStorage.getItem('theme') || 'dark';
		document.documentElement.setAttribute('data-theme', savedTheme);
		if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
	</script>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">

	<!-- Favicons & OpenGraph -->
	<link rel="apple-touch-icon" sizes="180x180" href="https://workbangers.com/wp-content/themes/workbangers/img/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-16x16.png">
	<link rel="shortcut icon" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon.ico">
	<meta name="theme-color" content="#5578B4" media="(prefers-color-scheme: dark)">
	<meta name="theme-color" content="#5578B4" media="(prefers-color-scheme: light)">
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>

		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<?php $pageTitle = 'Справочник компаний'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="card">
					<div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
						<h2 style="margin: 0;">Список компаний</h2>
						<button class="btn btn-primary btn-sm" onclick="openModal()">+ Добавить компанию</button>
					</div>
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th>ID</th>
									<th>Название компании</th>
									<th>Аббревиатура (Slug)</th>
									<th>Действия</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($companies)): ?>
									<tr>
										<td colspan="4" style="text-align: center; padding: 20px;">Нет компаний</td>
									</tr>
								<?php else: ?>
									<?php foreach ($companies as $c): ?>
									<tr>
										<td class="text-muted"><?= htmlspecialchars($c['id']) ?></td>
										<td><span class="cell-name"><?= htmlspecialchars($c['name']) ?></span></td>
										<td><span class="badge" style="background: var(--primary);"><?= htmlspecialchars($c['slug']) ?></span></td>
										<td>
											<button class="btn btn-secondary btn-sm" onclick='editCompany(<?= json_encode($c) ?>)'>Редактировать</button>
										</td>
									</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</main>
	</div>

	<!-- Модальное окно -->
	<div class="modal" id="companyModal">
		<div class="modal-content" style="max-width: 400px;">
			<h2 id="modalTitle">Новая компания</h2>
			
			<input type="hidden" id="companyId" value="">
			
			<div class="form-group">
				<label>Название компании</label>
				<input type="text" id="companyName" class="form-control" placeholder="Например, WorkBangers">
			</div>

			<div class="form-group">
				<label>Аббревиатура (Slug)</label>
				<input type="text" id="companySlug" class="form-control" placeholder="Например, wb">
				<small class="text-muted" style="display: block; margin-top: 5px;">Используется для поля receipt_org в чеках.</small>
			</div>

			<div class="modal-footer">
				<button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
				<button class="btn btn-primary" onclick="saveCompany()" id="btnSave">Сохранить</button>
			</div>
		</div>
	</div>

	<script>
		function toggleSidebarCollapse() {
			const root = document.documentElement;
			const isCollapsed = root.getAttribute('data-sidebar') === 'collapsed';
			if (isCollapsed) root.removeAttribute('data-sidebar'); else root.setAttribute('data-sidebar', 'collapsed');
			localStorage.setItem('sidebarCollapsed', !isCollapsed ? 'true' : 'false');
			if (typeof map !== 'undefined' && map) setTimeout(() => map.invalidateSize(), 300);
		}
		

		function toggleSidebarMobile() {
			document.getElementById('sidebar').classList.toggle('open');
			document.getElementById('sidebarOverlay').classList.toggle('active');
		}

		function toggleTheme() {
			const root = document.documentElement;
			const newTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			root.setAttribute('data-theme', newTheme);
			localStorage.setItem('theme', newTheme);
			updateThemeIcon(newTheme);
		}

		function updateThemeIcon(theme) {
			const icon = document.getElementById('themeIcon');
			if (theme === 'dark') {
				icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
			} else {
				icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
			}
		}
		updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'dark');

		function openModal() {
			document.getElementById('modalTitle').innerText = 'Добавить компанию';
			document.getElementById('companyId').value = '';
			document.getElementById('companyName').value = '';
			document.getElementById('companySlug').value = '';
			document.getElementById('companyModal').classList.add('active');
		}

		function closeModal() { document.getElementById('companyModal').classList.remove('active'); }

		function editCompany(company) {
			document.getElementById('modalTitle').innerText = 'Редактировать компанию';
			document.getElementById('companyId').value = company.id;
			document.getElementById('companyName').value = company.name;
			document.getElementById('companySlug').value = company.slug;
			document.getElementById('companyModal').classList.add('active');
		}

		async function saveCompany() {
			var id = document.getElementById('companyId').value;
			var name = document.getElementById('companyName').value;
			var slug = document.getElementById('companySlug').value;
			var btn = document.getElementById('btnSave');

			if (!name || !slug) { alert("Заполните все поля"); return; }
			
			btn.innerText = 'Сохранение...'; btn.disabled = true;
			try {
				var url = '/api/v1/companies'; var method = 'POST';
				if (id) { url += '/' + id; method = 'PUT'; }
				var res = await fetch(url, {
					method: method,
					headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer <?= $_SESSION["api_token"] ?? "" ?>' },
					body: JSON.stringify({ name: name, slug: slug })
				});
				var data = await res.json();
				if (data.success) window.location.reload();
				else { alert(data.error || 'Ошибка'); btn.innerText = 'Сохранить'; btn.disabled = false; }
			} catch (err) { alert("Ошибка"); btn.innerText = 'Сохранить'; btn.disabled = false; }
		}
	</script>
</body>
</html>
