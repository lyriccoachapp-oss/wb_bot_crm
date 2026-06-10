<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}

$user = $_SESSION['user_info'];

// Получаем списки сотрудников и объектов для фильтрации
$empRes = $api->get('/references/employees');
$rawEmps = $empRes['data'] ?? $empRes ?? [];
$employees = [];
if (is_array($rawEmps)) {
	foreach ($rawEmps as $it) {
		$id = $it['id_telegram'] ?? $it['telegram_id'] ?? $it['id'] ?? null;
		$name = $it['name'] ?? $it['full_name'] ?? $it['username'] ?? null;
		if ($id && $name) $employees[$id] = $name;
	}
}

$objRes = $api->get('/references/objects');
$rawObjs = $objRes['data'] ?? $objRes ?? [];
$objects = [];
if (is_array($rawObjs)) {
	foreach ($rawObjs as $it) {
		$id = $it['id'] ?? $it['id_place'] ?? null;
		$name = $it['name'] ?? $it['place_name'] ?? null;
		if ($id && $name) $objects[$id] = $name;
	}
}

// Получаем список чеков через API
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$params = ['page' => $page, 'limit' => $limit];
if (isset($_GET['telegram_id']) && $_GET['telegram_id'] !== '') {
	$params['telegram_id'] = (int)$_GET['telegram_id'];
}
if (isset($_GET['place_id']) && $_GET['place_id'] !== '') {
	$params['place_id'] = (int)$_GET['place_id'];
}

$response = $api->get('/receipts', $params);

$receipts = $response['data']['items'] ?? [];
$pagination = [
	'current_page' => $response['data']['current_page'] ?? 1,
	'last_page' => $response['data']['last_page'] ?? 1,
	'total' => $response['data']['total'] ?? 0
];

function filterSelect($name, $options) {
	$q = $_GET;
	$selected = $q[$name] ?? '';
	$html = "<select onchange=\"var q = new URLSearchParams(window.location.search); if(this.value) q.set('$name', this.value); else q.delete('$name'); q.set('page', 1); window.location.search = q.toString();\" style=\"display: block; width: 100%; margin-top: 8px; padding: 4px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-size: 0.8rem; box-sizing: border-box;\">";
	$html .= "<option value=\"\">Все</option>";
	foreach ($options as $val => $label) {
		$sel = (string)$val === (string)$selected ? 'selected' : '';
		$html .= "<option value=\"".htmlspecialchars($val)."\" $sel>".htmlspecialchars($label)."</option>";
	}
	$html .= "</select>";
	return $html;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Чеки — WorkBangers CRM</title>
	<script>
		const savedTheme = localStorage.getItem('theme') || 'dark';
		document.documentElement.setAttribute('data-theme', savedTheme);
		if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
	</script>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

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
	<style>
		.tabs-nav {
			display: flex;
			gap: 1rem;
			border-bottom: 1px solid var(--border-color);
			margin-bottom: 1.5rem;
		}
		.tab-btn {
			background: transparent;
			border: none;
			padding: 0.75rem 1.5rem;
			font-size: 1rem;
			font-weight: 500;
			color: var(--text-muted);
			cursor: pointer;
			border-bottom: 2px solid transparent;
			transition: all 0.2s ease;
		}
		.tab-btn:hover {
			color: var(--text-color);
		}
		.tab-btn.active {
			color: var(--primary-color);
			border-bottom-color: var(--primary-color);
		}
		.tab-content {
			display: none;
		}
		.tab-content.active {
			display: block;
		}

		/* Стили для OCR сканера */
		.ocr-toolbar {
			display: flex;
			gap: 12px;
			align-items: center;
			margin-bottom: 1.5rem;
		}
		.ocr-stats {
			display: flex;
			gap: 1rem;
			flex: 1;
		}
		.ocr-stats > div {
			flex: 1;
		}
		.ocr-bar {
			height: 8px;
			background: var(--border-color);
			border-radius: 4px;
			overflow: hidden;
			margin-top: 4px;
		}
		.ocr-bar > i {
			display: block;
			height: 100%;
			background: var(--primary-color);
			width: 0%;
			transition: width 0.3s ease;
		}
		
		.ocr-card {
			margin-bottom: 1.5rem;
			background: var(--card-bg);
			border-radius: var(--radius-md);
			padding: 1.5rem;
		}
		.ocr-card .file-head {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 1rem;
			padding-bottom: 0.5rem;
			border-bottom: 1px solid var(--border-color);
		}
		.ocr-grid {
			display: grid;
			grid-template-columns: 450px 1fr;
			gap: 1.5rem;
		}
		@media (max-width: 900px) {
			.ocr-grid { grid-template-columns: 1fr; }
		}
		.preview-box {
			position: relative;
			width: 100%;
			height: 500px;
			border: 1px solid var(--border-color);
			border-radius: var(--radius-md);
			background: #fff;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 1rem;
		}
		html[data-theme="dark"] .preview-box {
			background: #222;
		}
		.preview-box .thumb {
			max-width: 100%;
			max-height: 100%;
			display: block;
		}
		.magnifier-lens {
			width: 220px;
			height: 220px;
			border: 2px solid rgba(0,0,0,0.25);
			border-radius: 50%;
			box-shadow: 0 4px 16px rgba(0,0,0,0.25);
			position: absolute;
			pointer-events: none;
			overflow: hidden;
			display: none;
			z-index: 5;
		}
		.magnifier-lens img {
			position: absolute;
			top: 0;
			left: 0;
			transform-origin: 0 0;
			user-select: none;
			-webkit-user-drag: none;
		}
		.ocr-media .toolbar {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
			margin-bottom: 0.5rem;
		}
		.ocr-media .toolbar button.active {
			background: var(--primary-color) !important;
			color: #fff !important;
			border-color: var(--primary-color) !important;
		}

		/* Компактные карточки */
		.compact-card {
			display: flex;
			align-items: center;
			justify-content: space-between;
			background: var(--card-bg);
			border: 1px solid var(--border-color);
			border-radius: var(--radius-md);
			padding: 1rem;
			margin-bottom: 1rem;
			transition: all 0.2s ease;
			cursor: pointer;
			position: relative;
		}
		.compact-card:hover {
			border-color: var(--primary-color);
		}
		.compact-card.blurred {
			opacity: 0.8;
			cursor: default;
			pointer-events: none;
		}
		.compact-card.blurred .cc-thumb {
			filter: blur(4px);
			opacity: 0.7;
		}
		.compact-card .cc-thumb {
			width: 60px;
			height: 60px;
			object-fit: cover;
			border-radius: 4px;
			margin-right: 1rem;
		}
		.compact-card .cc-info {
			flex: 1;
			display: flex;
			flex-direction: column;
		}
		.compact-card .cc-title {
			font-weight: 600;
			font-size: 1rem;
			margin-bottom: 4px;
		}
		.compact-card .cc-meta {
			font-size: 0.85rem;
			color: var(--text-muted);
			display: flex;
			gap: 1rem;
		}
		.compact-card .cc-actions {
			display: flex;
			align-items: center;
			gap: 1rem;
			margin-right: 20px; /* space for absolute delete button */
		}
		.compact-card .cc-status {
			font-size: 0.85rem;
		}
		.cc-btn-save {
			position: absolute;
			bottom: 12px;
			right: 12px;
			background: transparent;
			border: none;
			color: var(--primary-color);
			cursor: pointer;
			padding: 4px;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: color 0.2s, transform 0.2s;
			opacity: 0.8;
		}
		.cc-btn-save:hover {
			transform: scale(1.1);
			opacity: 1;
		}
		.cc-btn-save:disabled {
			color: var(--text-muted);
			cursor: not-allowed;
			transform: none;
			opacity: 0.5;
		}
		.cc-btn-del {
			position: absolute;
			top: 8px;
			right: 8px;
			background: transparent;
			border: none;
			color: var(--text-muted);
			cursor: pointer;
			padding: 4px;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: color 0.2s, transform 0.2s;
			opacity: 0.6;
		}
		.cc-btn-del:hover {
			color: var(--danger-color);
			transform: scale(1.1);
			opacity: 1;
		}
		
		/* Глобальные селекторы */
		.global-selectors {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 1rem;
			margin-bottom: 1.5rem;
		}
		@media (max-width: 768px) {
			.global-selectors { grid-template-columns: 1fr; }
			.ocr-toolbar {
				flex-direction: column;
				align-items: stretch;
			}
			.ocr-toolbar > div:first-child {
				flex: 1 1 auto !important;
				width: 100%;
			}
			.ocr-stats {
				flex-direction: column;
				gap: 0.5rem;
			}
		}

		/* Форма редактирования в модалке */
		.ocr-form fieldset {
			border: none;
			padding: 0;
			margin: 0;
		}
		.ocr-form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 1rem;
			margin-bottom: 1rem;
		}
		@media (max-width: 768px) {
			.ocr-form-row { grid-template-columns: 1fr; gap: 0; margin-bottom: 0; }
			.ocr-form-row .form-group { margin-bottom: 1rem; }
		}
		.items-json {
			font-family: monospace;
			font-size: 0.85rem;
		}
		
		/* Модальное окно */
		.ocr-modal {
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.8);
			display: none;
			align-items: center;
			justify-content: center;
			z-index: 9999;
		}
		.ocr-modal.open {
			display: flex;
		}
		.ocr-modal-content {
			background: #111;
			padding: 1rem;
			border-radius: var(--radius-md);
			max-width: 96vw;
			max-height: 96vh;
			display: flex;
			flex-direction: column;
			gap: 1rem;
		}
		.ocr-modal-toolbar {
			display: flex;
			gap: 8px;
		}
		.ocr-canvas-wrap {
			position: relative;
			overflow: hidden;
			background: #000;
			border-radius: 4px;
			flex: 1;
			min-width: 60vw;
			min-height: 60vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.ocr-zoomimg {
			max-width: 100%;
			max-height: 100%;
			transition: transform 0.2s ease;
		}
	</style>
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>

		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<?php $pageTitle = 'Отслеживание расходов и чеков'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				
				<div class="tabs-nav">
					<button class="tab-btn active" onclick="switchTab('tab-reports', this)">Список чеков</button>
					<button class="tab-btn" onclick="switchTab('tab-add', this)">Добавление чеков</button>
				</div>

				<div id="tab-reports" class="tab-content active">
					<div class="card">
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th>
										Сотрудник
										<?= filterSelect('telegram_id', $employees) ?>
									</th>
									<th>
										Объект
										<?= filterSelect('place_id', $objects) ?>
									</th>
									<th>Тип затрат</th>
									<th>Дата / Время</th>
									<th>Сумма</th>
									<th style="width: 70px; text-align: right; padding-right: 30px;"></th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($receipts)): ?>
									<tr><td colspan="6" class="text-center text-muted">Нет чеков для отображения</td></tr>
								<?php endif; ?>
								
								<?php foreach ($receipts as $r): ?>
								<tr class="clickable" onclick='editSavedReceipt(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)'>
									<td><span class="cell-name"><?= htmlspecialchars($r['employee'] ?? 'Аноним') ?></span></td>
									<td><?= htmlspecialchars($r['place_name'] ?: '—') ?></td>
									<td>
										<?php 
											$catNames = ['fuel'=>'Топливо', 'materials'=>'Материалы', 'groceries'=>'Продукты', 'tools'=>'Инструменты', 'restaurant'=>'Ресторан', 'other'=>'Другое'];
											echo htmlspecialchars($catNames[$r['category'] ?? ''] ?? $r['category'] ?: '—');
										?>
									</td>
									<td>
										<?= htmlspecialchars($r['date'] ?: '') ?> 
										<span class="text-muted" style="font-size:0.8125rem;"><?= htmlspecialchars($r['time'] ?: '') ?></span>
									</td>
									<td class="cell-amount text-success">$<?= number_format((float)($r['amount'] ?? 0), 2) ?></td>
									<td style="text-align: right; padding-right: 30px;">
										<?php if (!empty($r['gdrive_url'])): ?>
											<button class="btn btn-sm" style="background: transparent; border: none; padding: 4px;" onclick="event.stopPropagation(); viewReceiptImage('<?= htmlspecialchars($r['gdrive_url']) ?>')" title="Посмотреть чек">
												<svg width="24" height="24" fill="var(--primary-color)" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
											</button>
										<?php else: ?>
											<span class="text-muted">—</span>
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
				</div> <!-- end pagination-wrapper -->
				<?php endif; ?>
				</div> <!-- end tab-reports -->

				<div id="tab-add" class="tab-content">
					<div class="card">
						<div class="global-selectors">
						<div class="form-floating">
							<select id="global-company" class="form-select"><option value="">— Не выбрано —</option></select>
							<label>Компания</label>
						</div>
						<div class="form-floating">
							<select id="global-employee" class="form-select"><option value="">— Не выбрано —</option></select>
							<label>Сотрудник</label>
						</div>
						<div class="form-floating">
							<select id="global-object" class="form-select"><option value="">— Не выбрано —</option></select>
							<label>Объект</label>
						</div>
					</div>

					<div class="ocr-toolbar">
						<div style="flex: 0 0 250px;">
							<label class="btn btn-primary" style="display:block; text-align:center; cursor:pointer; margin:0;">
								Выбрать файлы
								<input id="picker" type="file" accept="image/*" multiple style="display:none;">
							</label>
						</div>
						<div class="ocr-stats">
							<div>
								<div class="text-muted" style="font-size:0.875rem;">Загрузка: <span id="upCount">0</span>/<span id="upTotal">0</span></div>
								<div class="ocr-bar"><i id="upBar"></i></div>
							</div>
							<div>
								<div class="text-muted" style="font-size:0.875rem;">Распознавание: <span id="recCount">0</span>/<span id="recTotal">0</span></div>
								<div class="ocr-bar"><i id="recBar"></i></div>
							</div>
						</div>
					</div>
						<div id="refInfo" class="text-muted" style="font-size:0.875rem; margin-bottom: 1rem;">Загрузка справочников…</div>
						<div id="tasks"></div>
					</div>
				</div> <!-- end tab-add -->

			</div>
		</main>
	</div>

	<!-- Modal for Edit Form -->
	<div id="editModal" class="ocr-modal" aria-hidden="true" style="align-items: flex-start; overflow-y: auto; padding: 2rem;">
	  <div class="ocr-modal-content" style="width: 100%; max-width: 1200px; max-height: none;">
		<div class="ocr-modal-toolbar" style="justify-content: space-between;">
		  <h3 style="margin:0;">Редактирование чека</h3>
		  <div style="display: flex; gap: 8px; align-items: center;">
			  <button id="emDelete" class="btn btn-danger btn-sm" style="display: none; background: #dc3545; border-color: #dc3545; color: #fff;">🗑️ Удалить</button>
			  <button id="emSave" class="btn btn-primary btn-sm">💾 Сохранить</button>
			  <button id="emClose" class="btn btn-secondary btn-sm">Закрыть</button>
		  </div>
		</div>
		<div class="ocr-grid">
			<div class="ocr-media">
				<div class="preview-box">
				  <img id="emImg" class="thumb" src="" alt="">
				  <div id="emLens" class="magnifier-lens"></div>
				</div>
				<div class="toolbar" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
				  <button id="emRotate" class="btn btn-sm btn-secondary rotate">Повернуть ↻</button>
				  <button id="emCrop" class="btn btn-sm btn-secondary crop">Обрезать</button>
				  <button id="emApply" class="btn btn-sm btn-success apply" style="display: none; background-color: #28a745; color: #fff; border-color: #28a745;">Применить</button>
				  <button id="emReset" class="btn btn-sm btn-secondary reset">Сбросить</button>
				  <button id="emRerun" class="btn btn-sm btn-secondary rerun">Распознать снова</button>
				</div>
			</div>
			<div class="ocr-form" id="emForm">
				<fieldset>
				  <div class="ocr-form-row">
					<div class="form-floating"><select class="form-select" name="receipt_org"><option value="">—</option></select><label>Компания</label></div>
					<div class="form-floating"><select class="form-select" name="id_telegram"><option value="">—</option></select><label>Сотрудник</label></div>
					<div class="form-floating"><select class="form-select" name="place_id"><option value="">—</option></select><label>Объект</label></div>
				  </div>
				  <div class="ocr-form-row">
					<div class="form-floating"><input class="form-control" name="merchant_name" placeholder=" "><label>Магазин (название)</label></div>
					<div class="form-floating"><input class="form-control" name="merchant_address" placeholder=" "><label>Адрес магазина</label></div>
				  </div>
				  <div class="ocr-form-row">
					<div class="form-floating">
					  <select class="form-select" name="payment_method"><option value="">—</option><option value="cash">Наличные</option><option value="card">Карта</option></select>
					  <label>Способ оплаты</label>
					</div>
					<div class="form-floating"><input class="form-control" name="card_last4" maxlength="4" placeholder=" "><label>Карта (последние 4 цифры)</label></div>
				  </div>
				  <div class="ocr-form-row">
					<div class="form-floating"><input class="form-control" name="receipt_date" placeholder="YYYY-MM-DD"><label>Дата чека</label></div>
					<div class="form-floating"><input class="form-control" name="receipt_time" placeholder="HH:MM"><label>Время чека</label></div>
				  </div>
				  <div class="ocr-form-row">
					<div class="form-floating"><input class="form-control" name="subtotal" type="number" step="0.01" placeholder=" "><label>Сумма до налогов (Subtotal)</label></div>
					<div class="form-floating"><input class="form-control" name="tax" type="number" step="0.01" placeholder=" "><label>Налоги (Tax)</label></div>
					<div class="form-floating"><input class="form-control" name="receipt_amount" type="number" step="0.01" placeholder=" "><label>Полная сумма (Total)</label></div>
				  </div>
				  <div class="ocr-form-row">
					<div class="form-floating">
					  <select class="form-select" name="receipt_type"><option value="">—</option><option value="fuel">Топливо</option><option value="materials">Материалы</option><option value="groceries">Продукты</option><option value="tools">Инструменты</option><option value="restaurant">Ресторан</option><option value="other">Другое</option></select>
					  <label>Категория</label>
					</div>
					<div class="form-floating"><input class="form-control" name="currency" value="CAD" placeholder=" "><label>Валюта</label></div>
				  </div>
				  <div class="form-floating" style="margin-bottom:1rem;"><input class="form-control" name="comment" placeholder=" "><label>Комментарий</label></div>
				  <input type="hidden" name="items_json">
				  <input type="hidden" name="ocr_text">
				</fieldset>
			</div>
		</div>
	  </div>
	</div>

	<!-- Modal for Viewing Receipt -->
	<div id="viewModal" class="ocr-modal" aria-hidden="true" style="align-items: center; justify-content: center; z-index: 10000;" onclick="if(event.target===this) closeViewModal()">
	  <div class="ocr-modal-content" style="width: 90vw; max-width: 800px; height: 90vh; background: transparent; padding: 0; position: relative; border: none; box-shadow: none;">
		<button onclick="closeViewModal()" style="position: absolute; top: -40px; right: 0; background: transparent; border: none; color: #fff; font-size: 2.5rem; cursor: pointer; line-height: 1;">&times;</button>
		<iframe id="viewIframe" src="" style="width: 100%; height: 100%; border: none; border-radius: 8px; background: #fff;"></iframe>
	  </div>
	</div>

	<script>
		function viewReceiptImage(url) {
			const previewUrl = url.replace(/\/view$/, '/preview');
			document.getElementById('viewIframe').src = previewUrl;
			document.getElementById('viewModal').classList.add('open');
		}
		function closeViewModal() {
			document.getElementById('viewModal').classList.remove('open');
			document.getElementById('viewIframe').src = '';
		}
	</script>

	<script>
		localStorage.setItem('access_token', '<?= $_SESSION['api_token'] ?? '' ?>');
		window.CURRENT_LANG = '<?= $_SESSION['user_info']['lcode'] ?? "ru" ?>';
		function switchTab(tabId, btn) {
			document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
			document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
			document.getElementById(tabId).classList.add('active');
			btn.classList.add('active');
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
	</script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="/public/js/receipts-ocr.js?v=<?= time() ?>"></script>
</body>
</html>
