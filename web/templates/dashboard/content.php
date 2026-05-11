<?php
if (empty($_SESSION['api_token'])) {
	header('Location: /?route=login');
	exit;
}

// Запрашиваем контент (мы можем использовать публичный endpoint)
$response = $api->get('/content');
$contentBlocks = [];
if ($response['success'] ?? false) {
    $contentBlocks = $response['data'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Тексты сайта — WorkBangers CRM</title>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">
	<script>
		// Устанавливаем тему до рендера
		(function(){
			const t = localStorage.getItem('theme');
			if(t) document.documentElement.setAttribute('data-theme', t);
			else {
				const sys = window.matchMedia('(prefers-color-scheme: dark)').matches;
				document.documentElement.setAttribute('data-theme', sys ? 'dark' : 'light');
			}
			if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
		})();
	</script>
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>
		
		<!-- Подключаем sidebar -->
		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<?php $pageTitle = 'Управление мультиязычным контентом'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="card">
					<div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
						<h2>Список блоков</h2>
						<button class="btn btn-primary" onclick="openContentModal()">+ Добавить текст</button>
					</div>
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th>ID</th>
									<th>Ключ (Key)</th>
									<th>Доступ</th>
									<th>Контент (EN)</th>
									<th>Контент (RU)</th>
									<th>Контент (UK)</th>
									<th>Действия</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($contentBlocks as $block): ?>
								<tr>
									<td class="text-muted">#<?= htmlspecialchars($block['id']) ?></td>
									<td><strong><?= htmlspecialchars($block['key']) ?></strong></td>
									<td>
										<?php if (($block['access_level'] ?? 'public') === 'public'): ?>
											<span class="badge" style="background: var(--success-color);">Public</span>
										<?php elseif ($block['access_level'] === 'auth'): ?>
											<span class="badge" style="background: var(--primary-color);">Auth</span>
										<?php else: ?>
											<span class="badge" style="background: var(--danger-color);">Admin</span>
										<?php endif; ?>
									</td>
									<td>
										<div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
											<?= htmlspecialchars($block['content']['en'] ?? '') ?>
										</div>
									</td>
									<td>
										<div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
											<?= htmlspecialchars($block['content']['ru'] ?? '') ?>
										</div>
									</td>
									<td>
										<div style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
											<?= htmlspecialchars($block['content']['uk'] ?? '') ?>
										</div>
									</td>
									<td>
										<div style="display: flex; gap: 8px;">
											<button class="btn btn-outline" style="padding: 4px 8px;" onclick='editBlock(<?= htmlspecialchars(json_encode($block), ENT_QUOTES, "UTF-8") ?>)'>
												Редактировать
											</button>
											<button class="btn btn-outline" style="padding: 4px 8px; color: var(--danger-color); border-color: var(--danger-color);" onclick='deleteBlock(<?= $block['id'] ?>)'>
												Удалить
											</button>
										</div>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</main>
	</div>

	<!-- Modal -->
	<div class="modal" id="contentModal">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h3 class="modal-title" id="contentModalTitle">Добавить текст</h3>
					<button type="button" class="modal-close" onclick="closeContentModal()">&times;</button>
				</div>
				<div class="modal-body">
					<form id="contentForm">
						<input type="hidden" id="blockId" name="id">
						
						<div class="form-floating" style="margin-bottom: 1rem;">
							<input type="text" id="blockKey" name="key" class="form-control" required placeholder="example_key">
							<label for="blockKey">Ключ (Key)</label>
						</div>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="blockAccess" style="display:block; margin-bottom: 5px;">Права доступа</label>
							<select id="blockAccess" class="form-control" style="width:100%;">
								<option value="public">Публичный (Всем)</option>
								<option value="auth">Авторизованным</option>
								<option value="admin">Только админам</option>
							</select>
						</div>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="blockEn" style="display:block; margin-bottom: 5px;">Текст (English)</label>
							<textarea id="blockEn" name="en" class="form-control" rows="4" style="height:auto; resize:vertical; background-color: var(--bg-body); border: 1px solid rgba(100,100,100,0.2); color: var(--text-main); border-radius: 4px; padding: 10px; width:100%;"></textarea>
						</div>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="blockRu" style="display:block; margin-bottom: 5px;">Текст (Русский)</label>
							<textarea id="blockRu" name="ru" class="form-control" rows="4" style="height:auto; resize:vertical; background-color: var(--bg-body); border: 1px solid rgba(100,100,100,0.2); color: var(--text-main); border-radius: 4px; padding: 10px; width:100%;"></textarea>
						</div>

						<div class="form-group" style="margin-bottom: 1rem;">
							<label for="blockUk" style="display:block; margin-bottom: 5px;">Текст (Українська)</label>
							<textarea id="blockUk" name="uk" class="form-control" rows="4" style="height:auto; resize:vertical; background-color: var(--bg-body); border: 1px solid rgba(100,100,100,0.2); color: var(--text-main); border-radius: 4px; padding: 10px; width:100%;"></textarea>
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline" onclick="closeContentModal()">Отмена</button>
					<button type="button" class="btn btn-primary" id="saveBtn" onclick="saveBlock()">Сохранить</button>
				</div>
			</div>
		</div>
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

		// Функционал модалок
		const modal = document.getElementById('contentModal');
		function openContentModal() {
			document.getElementById('contentForm').reset();
			document.getElementById('blockId').value = '';
			document.getElementById('blockAccess').value = 'public';
			document.getElementById('contentModalTitle').textContent = 'Добавить текст';
			modal.classList.add('active');
		}
		function closeContentModal() {
			modal.classList.remove('active');
		}

		function editBlock(block) {
			document.getElementById('blockId').value = block.id;
			document.getElementById('blockKey').value = block.key;
			document.getElementById('blockAccess').value = block.access_level || 'public';
			document.getElementById('blockEn').value = block.content?.en || '';
			document.getElementById('blockRu').value = block.content?.ru || '';
			document.getElementById('blockUk').value = block.content?.uk || '';
			
			document.getElementById('contentModalTitle').textContent = 'Редактировать текст';
			modal.classList.add('active');
		}

		async function saveBlock() {
			const id = document.getElementById('blockId').value;
			const key = document.getElementById('blockKey').value;
			const access_level = document.getElementById('blockAccess').value;
			const en = document.getElementById('blockEn').value;
			const ru = document.getElementById('blockRu').value;
			const uk = document.getElementById('blockUk').value;

			if(!key) { alert('Введите ключ'); return; }

			const btn = document.getElementById('saveBtn');
			btn.disabled = true;

			try {
				const method = id ? 'PUT' : 'POST';
				const url = id ? `/api/v1/content/${id}` : `/api/v1/content`;
				
				const res = await fetch(url, {
					method: method,
					headers: {
						'Content-Type': 'application/json',
						'Authorization': `Bearer ${API_TOKEN}`
					},
					body: JSON.stringify({
						key: key,
						access_level: access_level,
						content: { en, ru, uk }
					})
				});

				const json = await res.json();
				if(json.success) {
					window.location.reload();
				} else {
					alert('Ошибка: ' + (json.message || 'Неизвестная ошибка'));
					btn.disabled = false;
				}
			} catch (e) {
				alert('Ошибка соединения');
				btn.disabled = false;
			}
		}

		async function deleteBlock(id) {
			if(!confirm('Удалить этот текстовый блок?')) return;

			try {
				const res = await fetch(`/api/v1/content/${id}`, {
					method: 'DELETE',
					headers: {
						'Authorization': `Bearer ${API_TOKEN}`
					}
				});
				const json = await res.json();
				if(json.success) {
					window.location.reload();
				} else {
					alert('Ошибка: ' + json.message);
				}
			} catch(e) {
				alert('Ошибка соединения');
			}
		}
	</script>
</body>
</html>
