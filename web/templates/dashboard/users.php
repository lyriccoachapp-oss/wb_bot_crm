<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

$user = $_SESSION['user_info'];

// Получаем список пользователей через API
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$params = ['page' => $page, 'limit' => $limit];
$allowedFilters = ['status', 'company_slug', 'search'];
foreach ($allowedFilters as $f) {
	if (isset($_GET[$f]) && $_GET[$f] !== '') $params[$f] = $_GET[$f];
}
if (!empty($_GET['sort_by'])) {
	$params['sort_by'] = $_GET['sort_by'];
	$params['sort_dir'] = $_GET['sort_dir'] ?? 'asc';
}

$response = $api->get('/users', $params);

function sortLink($column, $label) {
	$q = $_GET;
	$currentDir = 'asc';
	$indicator = '';
	if (isset($q['sort_by']) && $q['sort_by'] === $column) {
		$currentDir = (isset($q['sort_dir']) && $q['sort_dir'] === 'asc') ? 'desc' : 'asc';
		$indicator = $currentDir === 'asc' ? ' &uarr;' : ' &darr;';
	}
	$q['sort_by'] = $column;
	$q['sort_dir'] = $currentDir;
	return "<a href=\"/?" . htmlspecialchars(http_build_query($q)) . "\" style=\"color: inherit; text-decoration: none; white-space: nowrap;\">" . htmlspecialchars($label) . $indicator . "</a>";
}

function filterSelect($name, $options) {
	$q = $_GET;
	$selected = $q[$name] ?? '';
	$html = "<select onchange=\"var q = new URLSearchParams(window.location.search); if(this.value) q.set('$name', this.value); else q.delete('$name'); q.set('page', 1); window.location.search = q.toString();\" style=\"display: block; width: 100%; margin-top: 8px; padding: 4px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.8rem; box-sizing: border-box;\">";
	$html .= "<option value=\"\">Все</option>";
	foreach ($options as $val => $label) {
		$sel = $val === $selected ? 'selected' : '';
		$html .= "<option value=\"".htmlspecialchars($val)."\" $sel>".htmlspecialchars($label)."</option>";
	}
	$html .= "</select>";
	return $html;
}


$users = $response['data']['items'] ?? [];
$pagination = [
	'current_page' => $response['data']['current_page'] ?? 1,
	'last_page' => $response['data']['last_page'] ?? 1,
	'total' => $response['data']['total'] ?? 0
];

// Получаем список ролей
$rolesResponse = $api->get('/roles');
$roles = $rolesResponse['data'] ?? [];

// Получаем список компаний
$companiesResponse = $api->get('/companies');
$companies = $companiesResponse['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Пользователи — WorkBangers CRM</title>
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
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>

		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<?php $pageTitle = 'Управление доступом и персоналом'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="card">
					<div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
						<h2 style="margin: 0;">Список пользователей</h2>
						<div style="display: flex; gap: 8px;">
							<button type="button" class="btn btn-success btn-sm" onclick="exportUsers('xlsx')">Экспорт в XLSX</button>
							<button type="button" class="btn btn-primary btn-sm" onclick="exportUsers('csv')">Экспорт в CSV</button>
						</div>
					</div>
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th><?= sortLink('id', 'ID') ?></th>
									<th>
										<?= sortLink('firstname', 'Имя') ?>
										<input type="text" placeholder="Поиск..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" oninput="debounceSearch(this.value)" style="display: block; width: 100%; margin-top: 8px; padding: 4px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.8rem; box-sizing: border-box;">
									</th>
									<th><?= sortLink('phone', 'Телефон') ?></th>
									<th>
										<?= sortLink('company_slug', 'Компания') ?>
										<?php
											$compOpts = ['__none__' => 'Без компании'];
											foreach($companies as $c) $compOpts[$c['slug']] = $c['name'];
											echo filterSelect('company_slug', $compOpts);
										?>
									</th>
									<th>
										<?= sortLink('status', 'Статус') ?>
										<?php
											echo filterSelect('status', ['registred' => 'Зарегистрирован', 'quit' => 'Уволен', 'inprogress' => 'Ожидает подтверждения']);
										?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($users as $u): ?>
								<tr class="clickable" onclick='editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, "UTF-8") ?>)'>
									<td class="text-muted">#<?= htmlspecialchars($u['id']) ?></td>
									<td>
										<span class="cell-name"><?= htmlspecialchars($u['full_name']) ?></span>
										<?php if($u['email']): ?>
											<span class="cell-sub" style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($u['email']) ?></span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
									<td>
										<?php if ($u['company_slug']): ?>
											<span class="badge" style="background: var(--primary);"><?= htmlspecialchars($u['company_slug']) ?></span>
										<?php else: ?>
											<span class="text-muted">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ($u['status'] === 'работает'): ?>
											<span class="badge badge--success">Работает</span>
										<?php elseif ($u['status'] === 'обед'): ?>
											<span class="badge badge--warning">На обеде</span>
										<?php else: ?>
											<span class="badge badge--secondary" style="text-transform: uppercase;"><?= htmlspecialchars($u['status']) ?></span>
										<?php endif; ?>

										<?php if ($u['admin']): ?>
											<span class="badge badge--primary" style="margin-left: 5px;">ADMIN</span>
										<?php endif; ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($pagination['last_page'] > 1 || $pagination['total'] > 25): ?>
				<div class="pagination-wrapper">
					<div class="pagination-controls">
						<label>Показывать по:</label>
						<select class="form-control form-control-sm" onchange="window.location.href=this.value">
							<?php foreach ([25, 50, 100, 200] as $l): ?>
								<?php 
									$q = $_GET; $q['limit'] = $l; $q['page'] = 1; 
									$link = '/?' . http_build_query($q);
								?>
								<option value="<?= htmlspecialchars($link) ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="pagination">
						<?php
							$pages = [];
							$current = $pagination['current_page'];
							$last = $pagination['last_page'];

							if ($last <= 7) {
								for ($i = 1; $i <= $last; $i++) $pages[] = $i;
							} else {
								if ($current <= 4) {
									$pages = [1, 2, 3, 4, 5, '...', $last];
								} elseif ($current >= $last - 3) {
									$pages = [1, '...', $last - 4, $last - 3, $last - 2, $last - 1, $last];
								} else {
									$pages = [1, '...', $current - 1, $current, $current + 1, '...', $last];
								}
							}
						?>
						<?php foreach ($pages as $p): ?>
							<?php if ($p === '...'): ?>
								<span class="ellipsis">...</span>
							<?php else: ?>
								<?php 
									$q = $_GET;
									$q['page'] = $p;
									$link = '/?' . http_build_query($q);
								?>
								<a href="<?= htmlspecialchars($link) ?>" class="<?= $p === $current ? 'active' : '' ?>">
									<?= $p ?>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>

					<div class="pagination-controls">
						<label>Перейти на:</label>
						<select class="form-control form-control-sm" onchange="window.location.href=this.value">
							<?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
								<?php 
									$q = $_GET; $q['page'] = $i; 
									$link = '/?' . http_build_query($q);
								?>
								<option value="<?= htmlspecialchars($link) ?>" <?= $i === $current ? 'selected' : '' ?>><?= $i ?></option>
							<?php endfor; ?>
						</select>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</main>
	</div>

	<!-- Modal Code Omitted For Brevity (just CSS classes) -->
	<div class="modal" id="userModal">
		<div class="modal-content">
			<h2 id="modalTitle">Редактировать пользователя</h2>
			<input type="hidden" id="userId" value="">
			
			<div class="row-grid">
				<div class="form-group">
					<label>Telegram ID</label>
					<input type="text" id="userTelegramId" class="form-control" readonly style="background: rgba(0,0,0,0.05);">
				</div>
				<div class="form-group" style="visibility: hidden;">
					<!-- Placeholder для сетки -->
				</div>
			</div>

			<div class="row-grid">
				<div class="form-group">
					<label>Имя</label>
					<input type="text" id="userFirstname" class="form-control">
				</div>
				<div class="form-group">
					<label>Фамилия</label>
					<input type="text" id="userLastname" class="form-control">
				</div>
			</div>
			
			<!-- Остальные инпуты (сокр. для примера, но лучше полностью) -->
			<!-- Для простоты я оставляю их тут как были -->
			<div class="row-grid">
				<div class="form-group">
					<label>Ник (Username)</label>
					<input type="text" id="userUsername" class="form-control">
				</div>
				<div class="form-group">
					<label>Язык</label>
					<select id="userLcode" class="form-control">
						<option value="ru">Русский</option>
						<option value="uk">Украинский</option>
						<option value="en">Английский</option>
					</select>
				</div>
			</div>

			<div class="row-grid">
				<div class="form-group">
					<label>Email</label>
					<input type="email" id="userEmail" class="form-control">
				</div>
				<div class="form-group">
					<label>Телефон</label>
					<input type="text" id="userPhone" class="form-control">
				</div>
			</div>

			<div class="row-grid">
				<div class="form-group">
					<label>Статус</label>
					<select id="userStatus" class="form-control">
						<option value="inprogress">Ожидает подтверждения</option>
						<option value="registred">Зарегистрирован</option>
						<option value="quit">Уволен</option>
					</select>
				</div>
				<div class="form-group">
					<label>Роль в системе</label>
					<select id="userRole" class="form-control">
						<option value="">-- Без роли --</option>
						<?php foreach($roles as $r): ?>
							<option value="<?= htmlspecialchars($r['id']) ?>"><?= htmlspecialchars($r['name']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="row-grid">
				<div class="form-group">
					<label>Компания</label>
					<select id="userCompany" class="form-control">
						<option value="">-- Без компании --</option>
						<?php foreach($companies as $c): ?>
							<option value="<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['slug']) ?>)</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label>Новый пароль CRM</label>
					<input type="password" id="userPassword" class="form-control">
				</div>
			</div>

			<div class="modal-footer">
				<button class="btn btn-secondary" onclick="closeModal()">Отмена</button>
				<button class="btn btn-primary" onclick="saveUser()" id="btnSave">Сохранить</button>
			</div>
		</div>
	</div>

	<script>
		let searchTimeout;
		function debounceSearch(val) {
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(() => {
				var q = new URLSearchParams(window.location.search);
				if (val) q.set('search', val); else q.delete('search');
				q.set('page', 1);
				window.location.search = q.toString();
			}, 500);
		}

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

		function closeModal() { document.getElementById('userModal').classList.remove('active'); }

		function editUser(user) {
			document.getElementById('modalTitle').innerText = 'Редактировать: ' + (user.full_name || '');
			document.getElementById('userId').value = user.id;
			document.getElementById('userTelegramId').value = user.id_telegram || 'Не привязан';
			document.getElementById('userFirstname').value = user.firstname || '';
			document.getElementById('userLastname').value = user.lastname || '';
			document.getElementById('userUsername').value = user.username || '';
			document.getElementById('userLcode').value = user.lcode || 'ru';
			document.getElementById('userEmail').value = user.email || '';
			document.getElementById('userPhone').value = user.phone || '';
			
			var statusSelect = document.getElementById('userStatus');
			if (user.status && user.status !== 'registred' && user.status !== 'quit') {
				if(!Array.from(statusSelect.options).find(o => o.value === user.status)) {
					var opt = document.createElement('option');
					opt.value = user.status; opt.text = user.status; statusSelect.add(opt, 0);
				}
			}
			statusSelect.value = user.status || 'registred';
			document.getElementById('userRole').value = user.role_id || '';
			document.getElementById('userCompany').value = user.company_slug || '';
			document.getElementById('userPassword').value = '';
			document.getElementById('userModal').classList.add('active');
		}

		async function saveUser() {
			var id = document.getElementById('userId').value;
			if (!id) return;
			var btn = document.getElementById('btnSave');
			var payload = {
				firstname: document.getElementById('userFirstname').value,
				lastname: document.getElementById('userLastname').value,
				username: document.getElementById('userUsername').value,
				lcode: document.getElementById('userLcode').value,
				email: document.getElementById('userEmail').value,
				phone: document.getElementById('userPhone').value,
				status: document.getElementById('userStatus').value,
				role_id: document.getElementById('userRole').value || null,
				company_slug: document.getElementById('userCompany').value || null,
			};
			var pw = document.getElementById('userPassword').value;
			if (pw) payload.password = pw;

			btn.innerText = 'Сохранение...'; btn.disabled = true;
			try {
				var res = await fetch('/api/v1/users/' + id, {
					method: 'PUT',
					headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer <?= $_SESSION["api_token"] ?? "" ?>' },
					body: JSON.stringify(payload)
				});
				var data = await res.json();
				if (data.success) window.location.reload();
				else { alert(data.error || 'Ошибка'); btn.innerText = 'Сохранить'; btn.disabled = false; }
			} catch (err) { alert("Ошибка"); btn.innerText = 'Сохранить'; btn.disabled = false; }
		}

		async function authDownload(url, filename, btn) {
			try {
				const oldText = btn.innerText;
				btn.innerText = 'Генерация...'; btn.disabled = true;

				const res = await fetch(url, { headers: { 'Authorization': 'Bearer <?= $_SESSION["api_token"] ?? "" ?>' } });
				if (!res.ok) { alert('Ошибка скачивания'); btn.innerText = oldText; btn.disabled = false; return; }
				const blob = await res.blob();
				const dl = document.createElement('a');
				dl.href = window.URL.createObjectURL(blob);
				dl.download = filename;
				document.body.appendChild(dl);
				dl.click(); dl.remove();
				btn.innerText = oldText; btn.disabled = false;
			} catch(e) { alert('Ошибка сети при скачивании'); btn.innerText = oldText; btn.disabled = false; }
		}

		function exportUsers(format) {
			const btn = event.target;
			const urlParams = new URLSearchParams(window.location.search);
			urlParams.delete('page');
			urlParams.delete('limit');
			
			const query = urlParams.toString();
			const url = '/api/v1/users/export/' + format + (query ? '?' + query : '');
			const dateStr = new Date().toISOString().split('T')[0];
			authDownload(url, 'users_export_' + dateStr + '.' + format, btn);
		}
	</script>
</body>
</html>
