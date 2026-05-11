<?php
if (empty($_SESSION['user_info'])) {
	header('Location: /?route=login');
	exit;
}
$isAdmin = ($_SESSION['user_info']['role'] ?? 'user') === 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Отчёты — WorkBangers CRM</title>
	<script>
		const savedTheme = localStorage.getItem('theme') || 'dark';
		document.documentElement.setAttribute('data-theme', savedTheme);
		if (localStorage.getItem('sidebarCollapsed') === 'true') document.documentElement.setAttribute('data-sidebar', 'collapsed');
	</script>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">
	<!-- Favicons -->
	<link rel="apple-touch-icon" sizes="180x180" href="https://workbangers.com/wp-content/themes/workbangers/img/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon-16x16.png">
	<link rel="shortcut icon" href="https://workbangers.com/wp-content/themes/workbangers/img/favicon.ico">
	<style>
		.tabs-container { margin-bottom: 20px; border-bottom: 1px solid var(--border-color); display: flex; gap: 10px; }
		.tab-btn { background: none; border: none; padding: 10px 20px; font-size: 1rem; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
		.tab-btn:hover { color: var(--text-main); }
		.tab-btn.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 600; }
		.tab-pane { display: none; }
		.tab-pane.active { display: block; }
		.editable-row { cursor: pointer; transition: background 0.2s; }
		.editable-row:hover { background: var(--bg-hover) !important; }
		.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
		.modal.active { display: flex; }
		.modal-content { background: var(--bg-card); border-radius: var(--radius-md); width: 90%; max-width: 500px; padding: 20px; border: 1px solid var(--border-color); }
		.form-group { margin-bottom: 15px; }
		.form-group label { display: block; margin-bottom: 5px; color: var(--text-muted); }
		.multi-select-container { max-height: 150px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 5px; background: var(--bg-input); }
		.multi-select-container label { display: flex; align-items: center; gap: 10px; padding: 5px; cursor: pointer; color: var(--text-main); margin-bottom: 0; }
		.multi-select-container label:hover { background: var(--bg-hover); }
		.pagination { display: flex; gap: 5px; align-items: center; margin-top: 15px; }
		.page-btn { padding: 5px 10px; background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); border-radius: var(--radius-sm); cursor: pointer; }
		.page-btn.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
		.page-btn:disabled { opacity: 0.5; cursor: not-allowed; }
		.chip { display: inline-flex; align-items: center; gap: 8px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 6px 12px; cursor: pointer; user-select: none; transition: background 0.2s; }
		.chip:hover { background: var(--bg-hover); }
		.chip .label { font-size: 0.8rem; color: var(--text-muted); }
		.chip .value { font-weight: 600; color: var(--text-main); font-size: 0.95rem; }
		.chip-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; width: 100%; }
		.list-group-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid var(--border-color); cursor: pointer; }
		.list-group-item:hover { background: var(--bg-hover); }
		.list-group-item input[type="radio"] { margin-right: 10px; }
	</style>
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>
		<?php require __DIR__ . '/components/sidebar.php'; ?>
		<main class="main-content">
			<?php $pageTitle = 'Отчеты'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="tabs-container">
					<button class="tab-btn active" onclick="switchTab('emp')">По периодам (Сотрудники)</button>
					<button class="tab-btn" onclick="switchTab('obj')">По объектам</button>
					<button class="tab-btn" onclick="switchTab('full')">Полный отчет</button>
				</div>

				<!-- Вкладка: По сотрудникам -->
				<div id="tab-emp" class="tab-pane active">
					<div class="card mb-3">
						<div class="card-body">
							<form id="formEmpReport" class="filter-bar">
								<div class="filter-group">
									<label>Год</label>
									<select id="empYear" class="form-control" required>
										<?php $cy = (int)date('Y'); for($y=$cy-2; $y<=$cy+1; $y++) { echo "<option value=\"$y\" ".($y==$cy?'selected':'').">$y</option>"; } ?>
									</select>
								</div>
								<div class="filter-group">
									<label>Месяц</label>
									<select id="empMonth" class="form-control" required>
										<?php for($m=1; $m<=12; $m++) { $sm=str_pad($m, 2, '0', STR_PAD_LEFT); echo "<option value=\"$m\" ".($m==(int)date('n')?'selected':'').">$sm</option>"; } ?>
									</select>
								</div>
								<div class="filter-group">
									<label>Период</label>
									<select id="empHalf" class="form-control" required>
										<option value="1" <?= (int)date('j') <= 15 ? 'selected' : '' ?>>1 - 15</option>
										<option value="2" <?= (int)date('j') > 15 ? 'selected' : '' ?>>16 - Конец месяца</option>
									</select>
								</div>
								<div style="width: 100%; display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; align-items: center;">
									<button type="button" class="btn btn-primary" onclick="loadEmpReport()">Показать на сайте</button>
									<button type="button" class="btn btn-success" onclick="exportEmpReport()">Экспорт в XLSX</button>
									<?php if ($isAdmin): ?>
										<button type="button" class="btn btn-outline" style="margin-left:auto;" onclick="openAddShiftModal()">+ Добавить смену</button>
									<?php endif; ?>
								</div>
							</form>
						</div>
					</div>
					<div id="reportContainerEmp">
						<div class="card"><div class="card-body text-center text-muted p-3">Выберите параметры отчета и нажмите «Показать на сайте»</div></div>
					</div>
				</div>

				<!-- Вкладка: По объектам -->
				<div id="tab-obj" class="tab-pane">
					<div class="card mb-3">
						<div class="card-body">
							<form id="formObjReport" onsubmit="event.preventDefault(); loadObjReport();">
								<div class="chip-row">
									<div class="chip" onclick="openModal('modalObjSingle')">
										<span class="label">Объект:</span>
										<span class="value" id="lblObjSingle">Выбрать...</span>
									</div>
									<div class="chip" onclick="openModal('modalEmpsObj')">
										<span class="label">Сотрудники</span>
										<span class="value" id="lblEmpsObj">(0)</span>
									</div>
									<div class="chip" onclick="openModalDates('obj')">
										<span class="label">Даты</span>
										<span class="value" id="lblDatesObj">— — —</span>
									</div>
									
									<input type="hidden" id="objSelect">
									<input type="hidden" id="objDateFrom">
									<input type="hidden" id="objDateTo">
									
									<div style="display: flex; gap: 10px; margin-left: auto;">
										<button type="button" class="btn btn-primary" onclick="loadObjReport()">Показать на сайте</button>
										<button type="button" class="btn btn-success" onclick="exportObjReport()">Экспорт в XLSX</button>
									</div>
								</div>
							</form>
						</div>
					</div>
					<div id="reportContainerObj">
						<div class="card"><div class="card-body text-center text-muted p-3">Выберите параметры отчета и нажмите «Показать на сайте»</div></div>
					</div>
				</div>

				<!-- Вкладка: Полный отчет -->
				<div id="tab-full" class="tab-pane">
					<div class="card mb-3">
						<div class="card-body">
							<form id="formFullReport" onsubmit="event.preventDefault(); loadFullReport(1);">
								<div class="chip-row">
									<div class="chip" onclick="openModal('modalObjMulti')">
										<span class="label">Объекты</span>
										<span class="value" id="lblObjMulti">(0)</span>
									</div>
									<div class="chip" onclick="openModal('modalEmpsFull')">
										<span class="label">Сотрудники</span>
										<span class="value" id="lblEmpsFull">(0)</span>
									</div>
									<div class="chip" onclick="openModalDates('full')">
										<span class="label">Даты</span>
										<span class="value" id="lblDatesFull">— — —</span>
									</div>
									
									<input type="hidden" id="fullDateFrom">
									<input type="hidden" id="fullDateTo">

									<div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
										<button type="button" class="btn btn-primary" onclick="loadFullReport(1)">Показать</button>
									</div>
								</div>
							</form>
						</div>
					</div>
					<div class="card">
						<div class="table-container" id="reportContainerFull">
							<table class="table">
								<thead>
									<tr>
										<th>ID</th>
										<th>Сотрудник</th>
										<th>Объект</th>
										<th>Дата</th>
										<th>Check-in</th>
										<th>Check-out</th>
										<th>Lunch-in</th>
										<th>Lunch-out</th>
										<th>Часы</th>
									</tr>
								</thead>
								<tbody>
									<tr><td colspan="9" class="text-center text-muted">Задайте фильтры для поиска</td></tr>
								</tbody>
							</table>
						</div>
					</div>
					<div id="fullPagination" class="pagination-wrapper" style="display:none;">
						<!-- Пагинация -->
					</div>
				</div>

			</div>
		</main>
	</div>

	<!-- Модалка: Редактировать смену -->
	<div id="modalEditShift" class="modal">
		<div class="modal-content">
			<h2 class="mb-3">Редактировать смену</h2>
			<form id="formEditShift" onsubmit="saveShift(event)">
				<input type="hidden" id="editShiftId">
				<div class="form-group">
					<label>Сотрудник</label>
					<input type="text" id="editEmpName" class="form-control" disabled>
				</div>
				<div class="form-group">
					<label>Объект</label>
					<select id="editPlaceId" class="form-control" required></select>
				</div>
				<div class="form-group">
					<label>Дата</label>
					<input type="text" id="editWorkday" class="form-control" disabled>
				</div>
				<div class="form-group" style="display:flex; gap:10px;">
					<div style="flex:1;">
						<label>Check-in</label>
						<input type="datetime-local" id="editCheckin" class="form-control" step="1">
					</div>
					<div style="flex:1;">
						<label>Check-out</label>
						<input type="datetime-local" id="editCheckout" class="form-control" step="1">
					</div>
				</div>
				<div class="form-group" style="display:flex; gap:10px;">
					<div style="flex:1;">
						<label>Lunch-in</label>
						<input type="datetime-local" id="editLunchin" class="form-control" step="1">
					</div>
					<div style="flex:1;">
						<label>Lunch-out</label>
						<input type="datetime-local" id="editLunchout" class="form-control" step="1">
					</div>
				</div>
				<div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
					<button type="button" class="btn btn-outline" onclick="document.getElementById('modalEditShift').classList.remove('active')">Отмена</button>
					<button type="submit" class="btn btn-primary" id="btnSaveShift">Сохранить</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Модалка: Добавить смену -->
	<div id="modalAddShift" class="modal">
		<div class="modal-content">
			<h2 class="mb-3">Добавить смену</h2>
			<form id="formAddShift" onsubmit="addShift(event)">
				<div class="form-group">
					<label>Сотрудник</label>
					<select id="addEmpId" class="form-control" required></select>
				</div>
				<div class="form-group">
					<label>Объект</label>
					<select id="addPlaceId" class="form-control" required></select>
				</div>
				<div class="form-group">
					<label>Дата</label>
					<input type="date" id="addWorkday" class="form-control" required>
				</div>
				<div class="form-group" style="display:flex; gap:10px;">
					<div style="flex:1;">
						<label>Check-in</label>
						<input type="datetime-local" id="addCheckin" class="form-control" step="1">
					</div>
					<div style="flex:1;">
						<label>Check-out</label>
						<input type="datetime-local" id="addCheckout" class="form-control" step="1">
					</div>
				</div>
				<div class="form-group" style="display:flex; gap:10px;">
					<div style="flex:1;">
						<label>Lunch-in</label>
						<input type="datetime-local" id="addLunchin" class="form-control" step="1">
					</div>
					<div style="flex:1;">
						<label>Lunch-out</label>
						<input type="datetime-local" id="addLunchout" class="form-control" step="1">
					</div>
				</div>
				<div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
					<button type="button" class="btn btn-outline" onclick="document.getElementById('modalAddShift').classList.remove('active')">Отмена</button>
					<button type="submit" class="btn btn-primary" id="btnAddShift">Добавить</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Модалка: Одиночный выбор объекта (для вкладки По объектам) -->
	<div id="modalObjSingle" class="modal" onclick="if(event.target===this) closeModal('modalObjSingle')">
		<div class="modal-content">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<h3 style="margin:0;">Выбор объекта</h3>
				<button type="button" onclick="closeModal('modalObjSingle')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);">&times;</button>
			</div>
			<input type="text" class="form-control mb-3" placeholder="Поиск..." oninput="filterList(this.value, 'listObjSingle')">
			<div id="listObjSingle" style="max-height:300px; overflow-y:auto; border:1px solid var(--border-color); border-radius:var(--radius-sm); background: var(--bg-input);"></div>
		</div>
	</div>

	<!-- Модалка: Множественный выбор объектов (для вкладки Полный отчет) -->
	<div id="modalObjMulti" class="modal" onclick="if(event.target===this) closeModal('modalObjMulti')">
		<div class="modal-content">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<h3 style="margin:0;">Объекты</h3>
				<button type="button" onclick="closeModal('modalObjMulti')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);">&times;</button>
			</div>
			<div style="display:flex; gap:10px; margin-bottom:10px;">
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('fullObjects', true)">Выделить всех</button>
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('fullObjects', false)">Снять</button>
			</div>
			<input type="text" class="form-control mb-3" placeholder="Фильтр..." oninput="filterList(this.value, 'fullObjects')">
			<div id="fullObjects" class="multi-select-container" style="max-height:300px;"></div>
			<div style="text-align:right; margin-top:15px;">
				<button type="button" class="btn btn-primary" onclick="applyObjMulti()">Применить</button>
			</div>
		</div>
	</div>

	<!-- Модалка: Сотрудники объекта (для вкладки По объектам) -->
	<div id="modalEmpsObj" class="modal" onclick="if(event.target===this) closeModal('modalEmpsObj')">
		<div class="modal-content">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<h3 style="margin:0;">Сотрудники объекта</h3>
				<button type="button" onclick="closeModal('modalEmpsObj')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);">&times;</button>
			</div>
			<div style="display:flex; gap:10px; margin-bottom:10px;">
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('objEmployees', true)">Выделить всех</button>
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('objEmployees', false)">Снять</button>
			</div>
			<input type="text" class="form-control mb-3" placeholder="Фильтр..." oninput="filterList(this.value, 'objEmployees')">
			<div id="objEmployees" class="multi-select-container" style="max-height:300px;"></div>
			<div style="text-align:right; margin-top:15px;">
				<button type="button" class="btn btn-primary" onclick="applyEmpsObj()">Применить</button>
			</div>
		</div>
	</div>

	<!-- Модалка: Сотрудники (для вкладки Полный отчет) -->
	<div id="modalEmpsFull" class="modal" onclick="if(event.target===this) closeModal('modalEmpsFull')">
		<div class="modal-content">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<h3 style="margin:0;">Сотрудники</h3>
				<button type="button" onclick="closeModal('modalEmpsFull')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);">&times;</button>
			</div>
			<div style="display:flex; gap:10px; margin-bottom:10px;">
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('fullEmployees', true)">Выделить всех</button>
				<button type="button" class="btn btn-sm btn-outline" onclick="selectAll('fullEmployees', false)">Снять</button>
			</div>
			<input type="text" class="form-control mb-3" placeholder="Фильтр..." oninput="filterList(this.value, 'fullEmployees')">
			<div id="fullEmployees" class="multi-select-container" style="max-height:300px;"></div>
			<div style="text-align:right; margin-top:15px;">
				<button type="button" class="btn btn-primary" onclick="applyEmpsFull()">Применить</button>
			</div>
		</div>
	</div>

	<!-- Модалка: Выбор дат -->
	<div id="modalDates" class="modal" onclick="if(event.target===this) closeModal('modalDates')">
		<div class="modal-content">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
				<h3 style="margin:0;">Выбрать даты</h3>
				<button type="button" onclick="closeModal('modalDates')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);">&times;</button>
			</div>
			<div style="display:flex; gap:10px; margin-bottom:15px;">
				<div style="flex:1;">
					<label class="text-muted small">С</label>
					<input type="date" id="modalDateFrom" class="form-control">
				</div>
				<div style="flex:1;">
					<label class="text-muted small">По</label>
					<input type="date" id="modalDateTo" class="form-control">
				</div>
			</div>
			<div style="text-align:right;">
				<button type="button" class="btn btn-primary" onclick="applyDates()">Применить</button>
			</div>
		</div>
	</div>

	<script>
		const API_TOKEN = '<?= $_SESSION["api_token"] ?? "" ?>';
		let allEmployees = [];
		let allObjects = [];
		const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

		// Tabs
		function switchTab(tabId) {
			document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
			document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
			event.target.classList.add('active');
			document.getElementById('tab-' + tabId).classList.add('active');
		}

		document.addEventListener('DOMContentLoaded', async () => {
			const today = new Date().toISOString().split('T')[0];
			document.getElementById('objDateTo').value = today;
			document.getElementById('fullDateTo').value = today;
			const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
			document.getElementById('objDateFrom').value = firstDay;
			document.getElementById('fullDateFrom').value = firstDay;

			document.getElementById('lblDatesObj').innerText = `${firstDay} — ${today}`;
			document.getElementById('lblDatesFull').innerText = `${firstDay} — ${today}`;

			// Загрузка объектов
			try {
				const res = await fetch('/api/v1/references/objects', { headers: { 'Authorization': 'Bearer ' + API_TOKEN }});
				const data = await res.json();
				allObjects = data.data ? data.data : (Array.isArray(data) ? data : []);
				
				let objOpts = '<option value="">Выберите объект...</option>';
				let objDivs = '';
				let objChecks = '';
				allObjects.forEach(o => {
					let id = o.id || o.id_place;
					let name = o.name || o.place_name;
					objOpts += `<option value="${id}">${name}</option>`;
					objDivs += `<div class="list-group-item" onclick="applyObjSingle('${id}', '${name.replace(/'/g, "\\'")}')">${name}</div>`;
					objChecks += `<label class="list-group-item"><input type="checkbox" value="${id}"> ${name}</label>`;
				});
				document.getElementById('listObjSingle').innerHTML = objDivs;
				document.getElementById('editPlaceId').innerHTML = objOpts;
				document.getElementById('addPlaceId').innerHTML = objOpts;
				document.getElementById('fullObjects').innerHTML = objChecks;
			} catch(e) { console.error('Ошибка загрузки объектов'); }

			// Загрузка сотрудников
			try {
				const res = await fetch('/api/v1/references/employees', { headers: { 'Authorization': 'Bearer ' + API_TOKEN }});
				const data = await res.json();
				allEmployees = data.data ? data.data : (Array.isArray(data) ? data : []);
				
				let empChecks = '';
				let empOpts = '<option value="">Выберите сотрудника...</option>';
				allEmployees.forEach(e => {
					let id = e.id_telegram || e.id;
					let name = e.name || e.first_name + ' ' + (e.last_name || '');
					empChecks += `<label class="list-group-item"><input type="checkbox" value="${id}"> ${name}</label>`;
					empOpts += `<option value="${id}">${name}</option>`;
				});
				document.getElementById('objEmployees').innerHTML = empChecks;
				document.getElementById('fullEmployees').innerHTML = empChecks;
				document.getElementById('addEmpId').innerHTML = empOpts;
			} catch(e) { console.error('Ошибка загрузки сотрудников'); }

			// Устанавливаем все фильтры выбранными по умолчанию (Объекты, Сотрудники, Даты уже установлены)
			selectAll('fullObjects', true);
			selectAll('objEmployees', true);
			selectAll('fullEmployees', true);
			applyObjMulti();
			applyEmpsObj();
			applyEmpsFull();

			if (allObjects.length > 0) {
				let firstObj = allObjects[0];
				let id = firstObj.id || firstObj.id_place;
				let name = firstObj.name || firstObj.place_name;
				applyObjSingle(id, name.replace(/'/g, "\\'"));
			}

			loadObjReport();
			loadFullReport(1);
		});

		// Вспомогательные функции
		function getCheckedValues(containerId) {
			const checkboxes = document.querySelectorAll(`#${containerId} input[type="checkbox"]:checked`);
			return Array.from(checkboxes).map(c => c.value);
		}

		function toLocalDt(sqlStr) {
			if (!sqlStr || sqlStr.startsWith('0000-')) return '';
			return sqlStr.replace(' ', 'T');
		}

		function fromLocalDt(htmlStr) {
			if (!htmlStr) return '';
			if (htmlStr.length === 16) return htmlStr.replace('T', ' ') + ':00';
			return htmlStr.replace('T', ' ');
		}

		// Modal Helpers
		let currentFilterTab = '';

		function openModal(id) {
			document.getElementById(id).classList.add('active');
		}
		function closeModal(id) {
			document.getElementById(id).classList.remove('active');
		}
		function selectAll(containerId, state) {
			document.querySelectorAll(`#${containerId} input[type="checkbox"]`).forEach(c => c.checked = state);
		}
		function filterList(query, containerId) {
			const q = query.toLowerCase();
			document.querySelectorAll(`#${containerId} .list-group-item`).forEach(el => {
				const text = el.innerText.toLowerCase();
				el.style.display = text.includes(q) ? 'flex' : 'none';
			});
		}

		function applyObjSingle(id, name) {
			document.getElementById('objSelect').value = id;
			document.getElementById('lblObjSingle').innerText = name;
			closeModal('modalObjSingle');
		}

		function applyObjMulti() {
			const selected = getCheckedValues('fullObjects');
			document.getElementById('lblObjMulti').innerText = selected.length > 0 ? `(${selected.length})` : '(0)';
			closeModal('modalObjMulti');
		}

		function applyEmpsObj() {
			const selected = getCheckedValues('objEmployees');
			document.getElementById('lblEmpsObj').innerText = selected.length > 0 ? `(${selected.length})` : '(0)';
			closeModal('modalEmpsObj');
		}

		function applyEmpsFull() {
			const selected = getCheckedValues('fullEmployees');
			document.getElementById('lblEmpsFull').innerText = selected.length > 0 ? `(${selected.length})` : '(0)';
			closeModal('modalEmpsFull');
		}

		function openModalDates(tab) {
			currentFilterTab = tab;
			let from = tab === 'obj' ? document.getElementById('objDateFrom').value : document.getElementById('fullDateFrom').value;
			let to = tab === 'obj' ? document.getElementById('objDateTo').value : document.getElementById('fullDateTo').value;
			document.getElementById('modalDateFrom').value = from;
			document.getElementById('modalDateTo').value = to;
			openModal('modalDates');
		}

		function applyDates() {
			const from = document.getElementById('modalDateFrom').value;
			const to = document.getElementById('modalDateTo').value;
			
			if (currentFilterTab === 'obj') {
				document.getElementById('objDateFrom').value = from;
				document.getElementById('objDateTo').value = to;
				document.getElementById('lblDatesObj').innerText = `${from || '—'} — ${to || '—'}`;
			} else {
				document.getElementById('fullDateFrom').value = from;
				document.getElementById('fullDateTo').value = to;
				document.getElementById('lblDatesFull').innerText = `${from || '—'} — ${to || '—'}`;
			}
			closeModal('modalDates');
		}

		// Отчет по сотрудникам (Периоды)
		async function loadEmpReport() {
			const y = document.getElementById('empYear').value;
			const m = document.getElementById('empMonth').value;
			const h = document.getElementById('empHalf').value;
			if (!y || !m || !h) return alert("Заполните все поля");

			const btn = document.querySelector('#formEmpReport .btn-primary');
			const oldText = btn.innerText;
			btn.innerText = 'Загрузка...';
			btn.disabled = true;

			document.getElementById('reportContainerEmp').innerHTML = '<div class="alert alert-info">Загрузка данных...</div>';
			try {
				const res = await fetch(`/api/v1/reports/employees?y=${y}&m=${m}&h=${h}`, { headers: { 'Authorization': 'Bearer ' + API_TOKEN }});
				const json = await res.json();
				btn.innerText = oldText; btn.disabled = false;
				if (!json.success) return document.getElementById('reportContainerEmp').innerHTML = `<div class="alert error">${json.message}</div>`;
				
				const data = json.data;
				let html = '';
				if (!data.users || data.users.length === 0) {
					html = '<div class="card mt-3"><div class="card-body text-center text-muted">Нет данных за этот период</div></div>';
				}

				data.users.forEach(u => {
					html += `<div class="card mt-3 mb-4"><div class="card-header" style="background:var(--bg-body); padding:1rem 1.5rem; border-bottom:1px solid var(--border-color);"><h2 style="margin:0; font-size:1.1rem;">${u.name} <span class="text-muted" style="font-size:0.8rem; font-weight:normal;">(ID: ${u.id_telegram})</span></h2><div class="text-muted" style="font-size:0.9rem;">Итого часов (округл.): <span class="text-primary" style="font-weight:600; font-family:monospace; font-size:1.1rem;">${u.work_total_h}ч ${u.work_total_m}м</span></div></div>`;
					
					if (u.worktimes && u.worktimes.length > 0) {
						html += `<div class="card-body pt-3 pb-0"><h3 style="font-size:1rem; margin-top:0.5rem; margin-bottom:1rem;">Рабочее время</h3><div class="table-container mb-3" style="border:1px solid var(--border-color); border-radius:var(--radius-md);"><table><thead><tr><th>Дата</th><th>Объект</th><th>Check-in</th><th>Check-out</th><th>Lunch-in</th><th>Lunch-out</th><th>Часы</th><th>Мин</th></tr></thead><tbody>`;
						u.worktimes.forEach(w => {
							const encodedW = encodeURIComponent(JSON.stringify(w));
							const rowClass = isAdmin ? 'editable-row' : '';
							const onClick = isAdmin ? `onclick="openEditShiftModal('${encodedW}', '${u.name.replace(/'/g, "\\'")}')"` : '';
							html += `<tr class="${rowClass}" ${onClick}><td class="mono">${w.date}</td><td>${w.place}</td><td class="mono">${(w.checkin || '').substring(0,19)}</td><td class="mono">${(w.checkout || '').substring(0,19)}</td><td class="mono">${(w.lunchin || '').substring(0,19)}</td><td class="mono">${(w.lunchout || '').substring(0,19)}</td><td class="mono text-right" style="font-weight:600;">${w.hours}</td><td class="mono text-right">${w.minutes}</td></tr>`;
						});
						html += `</tbody></table></div></div>`;
					}

					if (u.receipts && u.receipts.length > 0) {
						html += `<div class="card-body pt-2 pb-3 ${u.worktimes && u.worktimes.length > 0 ? 'border-top' : ''}" style="border-color: var(--border-color) !important;"><h3 style="font-size:1rem; margin-top:0.5rem; margin-bottom:1rem;">Чеки</h3><div class="table-container" style="border:1px solid var(--border-color); border-radius:var(--radius-md);"><table><thead><tr><th>Дата</th><th>Время</th><th>Магазин</th><th class="text-right">Сумма</th><th>Категория</th><th>Метод оплаты</th><th>Карта</th></tr></thead><tbody>`;
						u.receipts.forEach(r => {
							html += `<tr><td class="mono">${r.date}</td><td class="mono">${r.time ? r.time.substring(0,5) : ''}</td><td>${r.merchant || ''}</td><td class="mono text-success text-right" style="font-weight:600;">$${parseFloat(r.amount).toFixed(2)}</td><td>${r.category || ''}</td><td>${r.payment_method || ''}</td><td class="mono">${r.card_last4 || ''}</td></tr>`;
						});
						html += `</tbody><tfoot><tr><th colspan="3" class="text-right">Итого чеков:</th><th class="mono text-success text-right" style="font-size:1.1rem;">$${parseFloat(u.receipts_total).toFixed(2)}</th><th colspan="3"></th></tr></tfoot></table></div></div>`;
					}
					html += `</div>`;
				});

				document.getElementById('reportContainerEmp').innerHTML = html;
			} catch(e) {
				btn.innerText = oldText; btn.disabled = false;
				document.getElementById('reportContainerEmp').innerHTML = '<div class="alert error">Ошибка сети</div>';
			}
		}

		// Отчет по объектам
		async function loadObjReport() {
			const id = document.getElementById('objSelect').value;
			const from = document.getElementById('objDateFrom').value;
			const to = document.getElementById('objDateTo').value;
			const emps = getCheckedValues('objEmployees').join(',');
			if (!id || !from || !to) return alert("Выберите объект и даты");

			const btn = document.querySelector('#formObjReport .btn-primary');
			const oldText = btn.innerText;
			btn.innerText = 'Загрузка...'; btn.disabled = true;

			document.getElementById('reportContainerObj').innerHTML = '<div class="alert alert-info">Загрузка данных...</div>';
			try {
				let url = `/api/v1/reports/objects?place_id=${id}&date_from=${from}&date_to=${to}`;
				if (emps) url += `&employees=${emps}`;
				
				const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + API_TOKEN }});
				const json = await res.json();
				btn.innerText = oldText; btn.disabled = false;

				if (!json.success) return document.getElementById('reportContainerObj').innerHTML = `<div class="alert error">${json.message}</div>`;
				const data = json.data;
				let html = `<div class="card mt-3"><div class="card-header"><h2 style="margin: 0;">Отчет: ${data.place ? data.place.name : id}</h2><span class="badge badge--primary">Рабочее время</span></div><div class="table-container"><table><thead><tr><th>Дата</th><th>Часы</th><th>Мин</th><th>Сотрудников</th><th>Имена</th></tr></thead><tbody>`;
				
				if (data.work_by_day.length === 0) {
					html += `<tr><td colspan="5" class="text-center text-muted">Нет данных</td></tr>`;
				} else {
					data.work_by_day.forEach(d => {
						html += `<tr><td class="mono">${d.date}</td><td class="mono">${d.hours}</td><td class="mono">${d.minutes}</td><td class="mono">${d.count}</td><td>${d.employees.join(', ')}</td></tr>`;
					});
				}
				html += `</tbody><tfoot><tr><th class="text-right">ИТОГО:</th><th class="mono">${data.work_total_h}</th><th class="mono">${data.work_total_m}</th><th></th><th></th></tr></tfoot></table></div></div>`;
				
				html += `<div class="card"><div class="card-header"><h2 style="margin: 0;">Чеки по объекту</h2></div><div class="table-container"><table><thead><tr><th>Дата</th><th>Магазин</th><th>Сотрудник</th><th>Сумма</th></tr></thead><tbody>`;
				if (data.receipts.length === 0) {
					html += `<tr><td colspan="4" class="text-center text-muted">Нет чеков</td></tr>`;
				} else {
					data.receipts.forEach(r => {
						html += `<tr><td class="mono">${r.date} ${r.time ? r.time.substring(0,5) : ''}</td><td>${r.merchant || ''}</td><td>${r.employee || ''}</td><td class="mono text-success">$${parseFloat(r.amount).toFixed(2)}</td></tr>`;
					});
				}
				html += `</tbody><tfoot><tr><th colspan="3" class="text-right">ИТОГО:</th><th class="mono text-success">$${parseFloat(data.receipts_total).toFixed(2)}</th></tr></tfoot></table></div></div>`;
				document.getElementById('reportContainerObj').innerHTML = html;
			} catch(e) {
				btn.innerText = oldText; btn.disabled = false;
				document.getElementById('reportContainerObj').innerHTML = '<div class="alert error">Ошибка сети</div>';
			}
		}

		// Полный отчет
		async function loadFullReport(page = 1) {
			console.log('loadFullReport starts for page:', page);
			try {
				const objs = getCheckedValues('fullObjects').join(',');
				const emps = getCheckedValues('fullEmployees').join(',');
				const from = document.getElementById('fullDateFrom').value;
				const to = document.getElementById('fullDateTo').value;
				const limitEl = document.getElementById('fullLimit');
				let limit = limitEl ? limitEl.value : (window.currentFullLimit || 50);
				window.currentFullLimit = limit;
				console.log('Params:', {objs, emps, from, to, limit});

				const btn = document.querySelector('#formFullReport .btn-primary');
				if (!btn) console.error('Button not found!');
				const oldText = btn ? btn.innerText : 'Показать';
				if (btn) { btn.innerText = 'Загрузка...'; btn.disabled = true; }

				let url = `/api/v1/time-entries?page=${page}&limit=${limit}`;
				if (objs) url += `&place_id=${objs}`;
				if (emps) url += `&telegram_id=${emps}`;
				if (from) url += `&date_from=${from}`;
				if (to) url += `&date_to=${to}`;
				console.log('Fetching URL:', url);

				const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + API_TOKEN }});
				console.log('Fetch status:', res.status);
				const json = await res.json();
				console.log('Fetch JSON:', json);
				
				if (btn) { btn.innerText = oldText; btn.disabled = false; }

				const tbody = document.querySelector('#reportContainerFull tbody');
				if (!tbody) console.error('tbody not found!');
				
				const items = Array.isArray(json.data) ? json.data : (json?.data?.items || json?.data?.data || []);
				
				if (!json.success || items.length === 0) {
					tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Смены не найдены</td></tr>';
					document.getElementById('fullPagination').style.display = 'none';
					return;
				}

				let html = '';
				items.forEach(w => {
					let h = Math.floor(w.work_minutes_rounded / 60);
					let m = w.work_minutes_rounded % 60;
					let empName = 'ID ' + w.telegram_id;
					const emp = allEmployees.find(e => (e.id_telegram || e.id) == w.telegram_id);
					if(emp) empName = emp.name || emp.first_name;

					html += `<tr>
						<td class="mono text-muted">${w.id}</td>
						<td>${empName}</td>
						<td>${w.place_name || '-'}</td>
						<td class="mono">${w.workday}</td>
						<td class="mono text-muted">${(w.checkin || '').replace('T', ' ')}</td>
						<td class="mono text-muted">${(w.checkout || '').replace('T', ' ')}</td>
						<td class="mono text-muted">${(w.lunchin || '').replace('T', ' ')}</td>
						<td class="mono text-muted">${(w.lunchout || '').replace('T', ' ')}</td>
						<td class="mono" style="font-weight:600;">${h}ч ${m}м</td>
					</tr>`;
				});
				tbody.innerHTML = html;

				// Пагинация
				// Если метаданные лежат прямо в json.data
				const meta = json?.data?.meta || json?.meta || (json?.data?.current_page ? json.data : null);
				const pag = document.getElementById('fullPagination');
				
				if (meta && meta.last_page > 1) {
					pag.style.display = 'flex';

					let pagHtml = `
					<div class="pagination-controls">
						<label>Показывать по:</label>
						<select id="fullLimit" class="form-control form-control-sm" style="width: auto;" onchange="loadFullReport(1)">
							<option value="25" ${limit == 25 ? 'selected' : ''}>25</option>
							<option value="50" ${limit == 50 ? 'selected' : ''}>50</option>
							<option value="100" ${limit == 100 ? 'selected' : ''}>100</option>
							<option value="250" ${limit == 250 ? 'selected' : ''}>250</option>
						</select>
					</div>
				`;

					pagHtml += `<div class="pagination">`;
					const last = meta.last_page;
					const current = meta.current_page;
					
					let pages = [];
					if (last <= 7) {
						for (let i = 1; i <= last; i++) pages.push(i);
					} else {
						if (current <= 4) {
							pages = [1, 2, 3, 4, 5, '...', last];
						} else if (current >= last - 3) {
							pages = [1, '...', last - 4, last - 3, last - 2, last - 1, last];
						} else {
							pages = [1, '...', current - 1, current, current + 1, '...', last];
						}
					}

					pages.forEach(p => {
						if (p === '...') {
							pagHtml += `<span class="ellipsis" style="color: var(--text-muted); padding: 5px;">...</span>`;
						} else {
							const activeClass = p === current ? 'active' : '';
							pagHtml += `<button class="page-btn ${activeClass}" onclick="loadFullReport(${p})">${p}</button>`;
						}
					});
					pagHtml += `</div>`;

					pagHtml += `
					<div class="pagination-controls">
						<label>Перейти на:</label>
						<select class="form-control form-control-sm" style="width: auto;" onchange="loadFullReport(this.value)">
				`;
					for (let i = 1; i <= last; i++) {
						pagHtml += `<option value="${i}" ${i === current ? 'selected' : ''}>${i}</option>`;
					}
					pagHtml += `
						</select>
					</div>
				`;

					pag.innerHTML = pagHtml;
				} else {
					pag.style.display = 'none';
				}

			} catch(e) {
				console.error('CRITICAL ERROR inside loadFullReport:', e);
				alert('Ошибка выполнения:\n' + e.message + '\n' + e.stack);
			}
		}

		// Редактирование смен
		function openEditShiftModal(encodedW, empName) {
			const w = JSON.parse(decodeURIComponent(encodedW));
			document.getElementById('editShiftId').value = w.id;
			document.getElementById('editEmpName').value = empName;
			document.getElementById('editPlaceId').value = w.id_place || '';
			document.getElementById('editWorkday').value = w.date;
			
			document.getElementById('editCheckin').value = toLocalDt(w.checkin);
			document.getElementById('editCheckout').value = toLocalDt(w.checkout);
			document.getElementById('editLunchin').value = toLocalDt(w.lunchin);
			document.getElementById('editLunchout').value = toLocalDt(w.lunchout);

			document.getElementById('modalEditShift').classList.add('active');
		}

		async function saveShift(e) {
			e.preventDefault();
			const id = document.getElementById('editShiftId').value;
			const data = {
				place_id: document.getElementById('editPlaceId').value || null,
				checkin: fromLocalDt(document.getElementById('editCheckin').value) || null,
				checkout: fromLocalDt(document.getElementById('editCheckout').value) || null,
				lunchin: fromLocalDt(document.getElementById('editLunchin').value) || null,
				lunchout: fromLocalDt(document.getElementById('editLunchout').value) || null,
			};
			const btn = document.getElementById('btnSaveShift');
			btn.disabled = true; btn.innerText = 'Сохранение...';

			try {
				const res = await fetch(`/api/v1/time-entries/${id}`, {
					method: 'PUT',
					headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Content-Type': 'application/json' },
					body: JSON.stringify(data)
				});
				const json = await res.json();
				if(json.success) {
					document.getElementById('modalEditShift').classList.remove('active');
					loadEmpReport(); // Перезагружаем текущий отчет
				} else {
					alert(json.message);
				}
			} catch(err) { alert('Ошибка сохранения'); }
			btn.disabled = false; btn.innerText = 'Сохранить';
		}

		// Добавление смен
		function openAddShiftModal() {
			document.getElementById('formAddShift').reset();
			const today = new Date().toISOString().split('T')[0];
			document.getElementById('addWorkday').value = today;
			document.getElementById('modalAddShift').classList.add('active');
		}

		async function addShift(e) {
			e.preventDefault();
			const data = {
				telegram_id: document.getElementById('addEmpId').value,
				place_id: document.getElementById('addPlaceId').value || null,
				workday: document.getElementById('addWorkday').value,
				checkin: fromLocalDt(document.getElementById('addCheckin').value) || null,
				checkout: fromLocalDt(document.getElementById('addCheckout').value) || null,
				lunchin: fromLocalDt(document.getElementById('addLunchin').value) || null,
				lunchout: fromLocalDt(document.getElementById('addLunchout').value) || null,
			};
			if (!data.telegram_id || !data.workday) return alert("Заполните обязательные поля");

			const btn = document.getElementById('btnAddShift');
			btn.disabled = true; btn.innerText = 'Добавление...';

			try {
				const res = await fetch(`/api/v1/time-entries`, {
					method: 'POST',
					headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Content-Type': 'application/json' },
					body: JSON.stringify(data)
				});
				const json = await res.json();
				if(json.success) {
					document.getElementById('modalAddShift').classList.remove('active');
					loadEmpReport(); // Перезагружаем текущий отчет
				} else {
					alert(json.message);
				}
			} catch(err) { alert('Ошибка сохранения'); }
			btn.disabled = false; btn.innerText = 'Добавить';
		}

		// Скачивание
		async function authDownload(url, filename) {
			try {
				const btn = event.target;
				const oldText = btn.innerText;
				btn.innerText = 'Генерация...'; btn.disabled = true;

				const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + API_TOKEN } });
				if (!res.ok) { alert('Ошибка скачивания отчета'); btn.innerText = oldText; btn.disabled = false; return; }
				const blob = await res.blob();
				const dl = document.createElement('a');
				dl.href = window.URL.createObjectURL(blob);
				dl.download = filename;
				document.body.appendChild(dl);
				dl.click(); dl.remove();
				btn.innerText = oldText; btn.disabled = false;
			} catch(e) { alert('Ошибка сети при скачивании'); }
		}

		function exportObjReport() {
			const id = document.getElementById('objSelect').value;
			const from = document.getElementById('objDateFrom').value;
			const to = document.getElementById('objDateTo').value;
			const emps = getCheckedValues('objEmployees').join(',');
			if (!id || !from || !to) return alert("Заполните все поля");
			let url = `/api/v1/reports/objects/xlsx?place_id=${id}&date_from=${from}&date_to=${to}`;
			if (emps) url += `&employees=${emps}`;
			authDownload(url, `object_report_${id}.xlsx`);
		}

		function exportEmpReport() {
			const y = document.getElementById('empYear').value;
			const m = document.getElementById('empMonth').value;
			const h = document.getElementById('empHalf').value;
			if (!y || !m || !h) return alert("Заполните все поля");
			authDownload(`/api/v1/reports/employees/xlsx?y=${y}&m=${m}&h=${h}`, `employee_report_${y}_${m}_half${h}.xlsx`);
		}
	</script>
	<script>
		function toggleSidebarMobile() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('active'); }
		function toggleSidebarCollapse() { const root = document.documentElement; const isCollapsed = root.getAttribute('data-sidebar') === 'collapsed'; if (isCollapsed) root.removeAttribute('data-sidebar'); else root.setAttribute('data-sidebar', 'collapsed'); localStorage.setItem('sidebarCollapsed', !isCollapsed ? 'true' : 'false'); }
		function toggleTheme() { const root = document.documentElement; const newTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'; root.setAttribute('data-theme', newTheme); localStorage.setItem('theme', newTheme); updateThemeIcon(newTheme); }
		function updateThemeIcon(theme) { const icon = document.getElementById('themeIcon'); if (theme === 'dark') { icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'; } else { icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'; } }
		updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'dark');
	</script>
</body>
</html>
