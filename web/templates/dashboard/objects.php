<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

$user = $_SESSION['user_info'];

// Получаем список объектов через API
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$params = ['page' => $page, 'limit' => $limit];
$allowedFilters = ['active', 'search'];
foreach ($allowedFilters as $f) {
	if (isset($_GET[$f]) && $_GET[$f] !== '') $params[$f] = $_GET[$f];
}
if (!empty($_GET['sort_by'])) {
	$params['sort_by'] = $_GET['sort_by'];
	$params['sort_dir'] = $_GET['sort_dir'] ?? 'asc';
}

$response = $api->get('/objects', $params);

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
	$html = "<select onchange=\"var q = new URLSearchParams(window.location.search); if(this.value !== '') q.set('$name', this.value); else q.delete('$name'); q.set('page', 1); window.location.search = q.toString();\" style=\"display: block; width: 100%; margin-top: 8px; padding: 4px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.8rem; box-sizing: border-box;\">";
	$html .= "<option value=\"\">Все</option>";
	foreach ($options as $val => $label) {
		$sel = (string)$val === (string)$selected ? 'selected' : '';
		$html .= "<option value=\"".htmlspecialchars($val)."\" $sel>".htmlspecialchars($label)."</option>";
	}
	$html .= "</select>";
	return $html;
}

$objects = $response['data']['items'] ?? [];
$pagination = [
	'current_page' => $response['data']['current_page'] ?? 1,
	'last_page' => $response['data']['last_page'] ?? 1,
	'total' => $response['data']['total'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Объекты — WorkBangers CRM</title>
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
			<?php $pageTitle = 'Управление объектами и локациями'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="card">
					<div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
						<h2 style="margin: 0;">Список объектов</h2>
						<div style="display: flex; gap: 8px; flex-wrap: wrap;">
							<button type="button" class="btn btn-success btn-sm" onclick="exportObjects('xlsx')">Экспорт в XLSX</button>
							<button type="button" class="btn btn-secondary btn-sm" onclick="exportObjects('csv')">Экспорт в CSV</button>
							<button class="btn btn-primary btn-sm" onclick="openObjModal()">+ Добавить объект</button>
						</div>
					</div>
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th><?= sortLink('id', 'ID') ?></th>
									<th>
										<?= sortLink('place_name', 'Название') ?>
										<input type="text" placeholder="Поиск..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" oninput="debounceSearch(this.value)" style="display: block; width: 100%; margin-top: 8px; padding: 4px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.8rem; box-sizing: border-box;">
									</th>
									<th>Тип работ</th>
									<th>
										<?= sortLink('active', 'Статус') ?>
										<?php
											echo filterSelect('active', ['1' => 'Активен', '0' => 'Завершен']);
										?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($objects as $obj): ?>
								<tr class="clickable" onclick='editObject(<?= htmlspecialchars(json_encode($obj), ENT_QUOTES, "UTF-8") ?>)'>
									<td class="text-muted">#<?= $obj['id'] ?></td>
									<td><span class="cell-name"><?= htmlspecialchars($obj['name']) ?></span></td>
									<td>
										<span class="text-muted"><?= htmlspecialchars($obj['works_type'] ?: '—') ?></span>
									</td>
									<td>
										<?php if ($obj['active']): ?>
											<span class="badge badge--success">Активен</span>
										<?php else: ?>
											<span class="badge badge--secondary">Завершен</span>
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

	<div class="modal" id="objectModal">
		<div class="modal-content">
			<h2 id="objModalTitle">Редактировать объект</h2>
			<input type="hidden" id="objId" value="">
			
			<div class="row-grid">
				<div class="form-group">
					<label>Название объекта</label>
					<input type="text" id="objName" class="form-control">
				</div>
				<div class="form-group">
					<label>Адрес (необязательно)</label>
					<input type="text" id="objAddress" class="form-control">
				</div>
			</div>
			
			<div class="row-grid">
				<div class="form-group">
					<label>Тип работ</label>
					<input type="text" id="objWorksType" class="form-control" placeholder="Например: Крыша, Электрика...">
				</div>
				<div class="form-group">
					<label>Статус</label>
					<select id="objActive" class="form-control">
						<option value="1">Активен</option>
						<option value="0">Завершен</option>
					</select>
				</div>
			</div>

			<div class="modal-footer">
				<button class="btn btn-secondary" onclick="closeObjModal()">Отмена</button>
				<button class="btn btn-primary" onclick="saveObject()" id="btnSaveObj">Сохранить</button>
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

		function closeObjModal() { document.getElementById('objectModal').classList.remove('active'); }

		function openObjModal() {
			document.getElementById('objModalTitle').innerText = 'Новый объект';
			document.getElementById('objId').value = '';
			document.getElementById('objName').value = '';
			document.getElementById('objAddress').value = '';
			document.getElementById('objActive').value = '1';
			document.getElementById('objWorksType').value = '';
			document.getElementById('objectModal').classList.add('active');
		}

		function editObject(obj) {
			document.getElementById('objModalTitle').innerText = 'Редактировать: ' + (obj.name || '');
			document.getElementById('objId').value = obj.id;
			document.getElementById('objName').value = obj.name || '';
			document.getElementById('objAddress').value = obj.address || '';
			document.getElementById('objActive').value = obj.active ? '1' : '0';
			document.getElementById('objWorksType').value = obj.works_type || '';
			
			document.getElementById('objectModal').classList.add('active');
		}

		async function saveObject() {
			var id = document.getElementById('objId').value;
			var btn = document.getElementById('btnSaveObj');
			
			var payload = {
				place_name: document.getElementById('objName').value,
				place_address: document.getElementById('objAddress').value,
				works_type: document.getElementById('objWorksType').value,
				active: document.getElementById('objActive').value === '1'
			};

			if (!payload.place_name) { alert("Заполните название объекта"); return; }

			btn.innerText = 'Сохранение...'; btn.disabled = true;
			try {
				var url = id ? '/api/v1/objects/' + id : '/api/v1/objects';
				var method = id ? 'PUT' : 'POST';
				var res = await fetch(url, {
					method: method,
					headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer <?= $_SESSION["api_token"] ?? "" ?>' },
					body: JSON.stringify(payload)
				});
				var data = await res.json();
				if (data.success) {
					window.location.reload();
				}
				else { alert(data.error || 'Ошибка'); btn.innerText = 'Сохранить'; btn.disabled = false; }
			} catch (err) { alert("Ошибка"); btn.innerText = 'Сохранить'; btn.disabled = false; }
		}

		async function toggleObjActive() {
			var id = document.getElementById('objId').value;
			if (!id) return;
			var btn = document.getElementById('btnToggleActive');
			btn.disabled = true;
			try {
				var res = await fetch('/api/v1/objects/' + id + '/toggle', {
					method: 'PATCH',
					headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer <?= $_SESSION["api_token"] ?? "" ?>' }
				});
				var data = await res.json();
				if (data.success) window.location.reload();
				else { alert(data.error || 'Ошибка'); btn.disabled = false; }
			} catch (err) { alert("Ошибка"); btn.disabled = false; }
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

		function exportObjects(format) {
			const btn = event.target;
			const urlParams = new URLSearchParams(window.location.search);
			urlParams.delete('page');
			urlParams.delete('limit');
			
			const query = urlParams.toString();
			const url = '/api/v1/objects/export/' + format + (query ? '?' + query : '');
			const dateStr = new Date().toISOString().split('T')[0];
			authDownload(url, 'objects_export_' + dateStr + '.' + format, btn);
		}
	</script>
</body>
</html>
