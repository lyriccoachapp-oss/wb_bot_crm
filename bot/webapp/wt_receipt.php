<?php
date_default_timezone_set('America/Halifax');

$APP_VERSION = '1.0.26';

// Подключаем i18n
require_once('../lib/i18n.php');
$userLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'ru';
I18n::load($userLanguage);

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($userLanguage) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= __('webapp.receipts.title') ?></title>
	<style>
		:root{
			--radius:.4rem;
			--btn-pad-y:.85rem;
			--btn-pad-x:1.2rem;
			--font-lg:1.125rem;
		}
		body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:0;background:#ddd;color:#000;margin:0;padding-bottom:100px;}
		
		.header { background: #fff; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 10; }
		h2 { margin: 0; font-size: 1.5rem; }

		.tabs { display: flex; border-bottom: 2px solid #eee; background: #fff; }
		.tab { flex: 1; text-align: center; padding: 0.8rem; font-weight: 600; color: #666; cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; }
		.tab.active { color: #0b66ff; border-bottom: 2px solid #0b66ff; }

		.tab-content { display: none; padding: 1rem; }
		.tab-content.active { display: block; }

		.warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 0.8rem; margin-bottom: 1rem; border-radius: 4px; font-size: 0.9rem; color: #856404; }
		.instruction-box { background: #e2e3e5; padding: 0.8rem; margin-bottom: 1.5rem; border-radius: 4px; font-size: 0.85rem; color: #383d41; }

		.btn { display: block; width: 100%; text-align: center; padding: var(--btn-pad-y) var(--btn-pad-x); border: none; border-radius: var(--radius); cursor: pointer; background: #0b66ff; color: #fff; font-size: 1rem; font-weight: 600; box-sizing: border-box; }
		.btn:disabled { opacity: 0.6; pointer-events: none; }
		.btn-outline { background: transparent; border: 1px solid #0b66ff; color: #0b66ff; }
		
		/* Очередь чеков */
		.queue-list { display: flex; flex-direction: column; gap: 0.8rem; margin-top: 1.5rem; }
		.q-card { background: #fff; border-radius: var(--radius); padding: 0.8rem; display: flex; gap: 1rem; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); cursor: pointer; border: 1px solid #eee; transition: background 0.2s; }
		.q-card:active { background: #f0f0f0; }
		.q-thumb { width: 60px; height: 60px; border-radius: 4px; object-fit: cover; background: #eee; }
		.q-card.blurred .q-thumb { filter: blur(4px); opacity: 0.6; }
		.q-info { flex: 1; }
		.q-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
		.q-status { font-size: 0.85rem; color: #666; display: flex; align-items: center; gap: 0.4rem; }
		.q-status.ready { color: #d97706; font-weight: 600; }
		
		.spinner-small { width: 12px; height: 12px; border: 2px solid #ccc; border-top-color: #666; border-radius: 50%; animation: spin 1s linear infinite; }

		/* Список истории */
		.history-list { display: flex; flex-direction: column; gap: 0.5rem; }
		.h-card { background: #fff; border-radius: var(--radius); padding: 0.8rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
		.h-info { display: flex; flex-direction: column; gap: 0.2rem; }
		.h-date { font-size: 0.8rem; color: #666; }
		.h-place { font-weight: 600; font-size: 0.95rem; }
		.h-amount { font-weight: bold; font-size: 1.1rem; color: #16a34a; text-align: right; }
		.h-status { font-size: 0.75rem; text-transform: uppercase; background: #eee; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; text-align: center; }
		.load-more { margin-top: 1rem; text-align: center; padding: 0.8rem; color: #0b66ff; font-weight: 600; cursor: pointer; }

		/* Модалка */
		#modalOverlay{position:fixed;top:0;left:0;right:0;bottom:5rem;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.6);z-index:999;padding:1rem;box-sizing:border-box}
		#modalContent{background:#fff;width:100%;max-width:400px;padding:1.5rem;border-radius:var(--radius);box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:100%;overflow:auto}
		.form-group { margin-bottom: 0.8rem; }
		.form-group label { display: block; font-size: 0.85rem; color: #555; margin-bottom: 0.3rem; font-weight: 600; }
		.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.65rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; box-sizing: border-box; background: #fff;}
		.form-group textarea { resize: vertical; min-height: 60px; }
		.modal-buttons { display: flex; gap: 0.8rem; margin-top: 1.5rem; }
		.modal-buttons .btn { flex: 1; }

		/* Навигация внизу */
		.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; display: flex; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); padding: 0.5rem; gap: 0.5rem; z-index: 100; overflow-x: auto; }
		.bottom-nav::-webkit-scrollbar { display: none; }
		.nav-btn { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; background: none; border: none; padding: 0.5rem 0; color: #666; text-decoration: none; font-size: 0.75rem; border-radius: var(--radius); min-width: 60px; }
		.nav-btn.active { color: #0b66ff; background: #f0f6ff; font-weight: 600; }
		.nav-btn svg { width: 24px; height: 24px; margin-bottom: 4px; }

		.app-version{ position:fixed; top:2px; right:3px; font-size:6px; line-height:1; color:#000; opacity:.45; z-index:1000; pointer-events:none;}
		@keyframes spin{to{transform:rotate(360deg)}}
	</style>
	<script src="https://telegram.org/js/telegram-web-app.js"></script>
	<script src="auth.js?v=<?= $APP_VERSION ?>"></script>
</head>
<body>
	<div class="app-version">v<?= $APP_VERSION ?></div>

	<div class="header">
		<h2 style="text-transform: uppercase; font-size: 1.25rem; font-weight: bold; margin-bottom: 0.2rem; color: #1e3a8a;"><?= __('webapp.receipts.title') ?></h2>
	</div>

		<div class="tabs">
		<div class="tab active" onclick="switchTab('upload')"><?= ($userLanguage === 'ru' ? 'Добавить чеки' : ($userLanguage === 'uk' ? 'Додати чеки' : 'Add Receipts')) ?></div>
		<div class="tab" onclick="switchTab('list')"><?= ($userLanguage === 'ru' ? 'Список чеков' : ($userLanguage === 'uk' ? 'Список чеків' : 'Receipts List')) ?></div>
	</div>

	<!-- Вкладка Загрузка -->
	<div id="tab-upload" class="tab-content active">
		<div class="warning-box">
			<?= ($userLanguage === 'ru' ? '<strong>Внимание!</strong> Загружайте чеки при хорошем интернете.' : '<strong>Warning!</strong> Upload receipts with good internet connection.') ?>
		</div>
		<div class="instruction-box">
			<b><?= ($userLanguage === 'ru' ? 'Как это работает:' : ($userLanguage === 'uk' ? 'Як це працює:' : 'How it works:')) ?></b> <?= ($userLanguage === 'ru' ? 'Выберите фото чека. Он станет в очередь на распознавание. Дождитесь окончания (статус изменится), нажмите на него, проверьте данные и нажмите "Сохранить".' : ($userLanguage === 'uk' ? 'Оберіть фото чека. Він стане в чергу на розпізнавання. Дочекайтеся закінчення (статус зміниться), натисніть на нього, перевірте дані та натисніть "Зберегти".' : 'Select a photo of the receipt. It will be placed in the recognition queue. Wait for completion (status will change), click on it, check the data and click "Save".')) ?>
		</div>

		<input type="file" id="fileInput" accept="image/*" style="display:none" onchange="handleFileSelect(event)" multiple>
		<button class="btn" onclick="document.getElementById('fileInput').click()">
			<svg style="vertical-align:middle;margin-right:8px;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
			<?= ($userLanguage === 'ru' ? 'Сфотографировать / Загрузить' : ($userLanguage === 'uk' ? 'Сфотографувати / Завантажити' : 'Take Photo / Upload')) ?>
		</button>

		<div class="queue-list" id="queueList">
			<!-- Карточки очереди -->
		</div>
	</div>

	<!-- Вкладка Список -->
	<div id="tab-list" class="tab-content">
		<div class="history-list" id="historyList">
			<!-- История чеков -->
		</div>
		<div class="load-more" id="btnLoadMore" style="display:none;" onclick="loadHistory(historyPage + 1)">Загрузить еще</div>
	</div>

	<!-- Модалка проверки чека -->
	<div id="modalOverlay">
		<div id="modalContent">
			<h3 style="margin-top:0;margin-bottom:1.5rem;font-size:1.2rem;"><?= ($userLanguage === 'ru' ? 'Проверка данных чека' : 'Review Receipt Data') ?></h3>
			<input type="hidden" id="f_queue_id">
			
			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Объект' : ($userLanguage === 'uk' ? 'Об\'єкт' : 'Object')) ?></label>
				<select id="f_place">
					<option value=""><?= ($userLanguage === 'ru' ? '-- Выберите объект --' : ($userLanguage === 'uk' ? '-- Оберіть об\'єкт --' : '-- Select object --')) ?></option>
				</select>
			</div>
			
			<div class="form-group" style="display:flex;gap:1rem;">
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Дата' : 'Date') ?></label>
					<input type="date" id="f_date">
				</div>
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Время' : ($userLanguage === 'uk' ? 'Час' : 'Time')) ?></label>
					<input type="time" id="f_time">
				</div>
			</div>
			
			<div class="form-group" style="display:flex;gap:0.5rem;">
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Без налогов' : ($userLanguage === 'uk' ? 'Без податків' : 'Subtotal')) ?></label>
					<input type="number" step="0.01" id="f_subtotal">
				</div>
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Налоги' : ($userLanguage === 'uk' ? 'Податки' : 'Tax')) ?></label>
					<input type="number" step="0.01" id="f_tax">
				</div>
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Итого' : ($userLanguage === 'uk' ? 'Всього' : 'Total')) ?></label>
					<input type="number" step="0.01" id="f_amount">
				</div>
			</div>
			
			<div class="form-group" style="display:flex;gap:1rem;">
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Тип оплаты' : ($userLanguage === 'uk' ? 'Тип оплати' : 'Payment Type')) ?></label>
					<select id="f_payment_method">
						<option value="card"><?= ($userLanguage === 'ru' ? 'Карта' : 'Card') ?></option>
						<option value="cash"><?= ($userLanguage === 'ru' ? 'Наличные' : ($userLanguage === 'uk' ? 'Готівка' : 'Cash')) ?></option>
					</select>
				</div>
				<div style="flex:1;">
					<label><?= ($userLanguage === 'ru' ? 'Категория' : ($userLanguage === 'uk' ? 'Категорія' : 'Category')) ?></label>
					<select id="f_category">
						<option value="materials"><?= ($userLanguage === 'ru' ? 'Материалы (Materials)' : 'Materials') ?></option>
						<option value="fuel"><?= ($userLanguage === 'ru' ? 'Топливо (Fuel)' : 'Fuel') ?></option>
						<option value="tools"><?= ($userLanguage === 'ru' ? 'Инструменты (Tools)' : 'Tools') ?></option>
						<option value="restaurant"><?= ($userLanguage === 'ru' ? 'Обед (Restaurant)' : 'Restaurant') ?></option>
						<option value="groceries"><?= ($userLanguage === 'ru' ? 'Продукты (Groceries)' : 'Groceries') ?></option>
						<option value="other"><?= ($userLanguage === 'ru' ? 'Другое (Other)' : 'Other') ?></option>
					</select>
				</div>
			</div>

			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Комментарий' : ($userLanguage === 'uk' ? 'Коментар' : 'Comment')) ?></label>
				<textarea id="f_comment" rows="2"></textarea>
			</div>

			<div class="modal-buttons">
				<button class="btn btn-outline" onclick="closeModal()"><?= ($userLanguage === 'ru' ? 'Отмена' : 'Cancel') ?></button>
				<button class="btn" id="btnSaveReceipt" onclick="saveReceipt()"><?= ($userLanguage === 'ru' ? 'Сохранить' : 'Save') ?></button>
			</div>
		</div>
	</div>

	<!-- Нижнее меню -->
	<?php include 'nav.php'; ?>

	<div class="modal-overlay" id="modalObjectAdd" style="display:none;position:fixed;top:0;left:0;right:0;bottom:5rem;align-items:center;justify-content:center;background:rgba(255,255,255,.9);z-index:999;padding:1rem;box-sizing:border-box">
    <div class="modal" style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.15);width:100%;max-width:400px;max-height:100%;overflow-y:auto;">
        <h3 style="margin-top:0;margin-bottom:1.5rem;font-size:1.2rem;text-align:center;"><?= ($userLanguage === 'ru' ? 'Добавление объекта' : ($userLanguage === 'uk' ? 'Додавання об\'єкта' : 'Add Object')) ?></h3>
        
        <div style="margin-bottom:1rem;position:relative;">
            <label style="display:block;margin-bottom:0.5rem;font-size:0.9rem;color:#4b5563;"><?= ($userLanguage === 'ru' ? 'Поиск адреса' : ($userLanguage === 'uk' ? 'Пошук адреси' : 'Search address')) ?></label>
            <input type="text" id="objSearchAddress" placeholder="<?= ($userLanguage === 'ru' ? 'Начните вводить адрес...' : 'Start typing address...') ?>" autocomplete="off" style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;box-sizing:border-box;">
            <div id="objAutocompleteResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:0 0 8px 8px;max-height:200px;overflow-y:auto;z-index:1001;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block;margin-bottom:0.5rem;font-size:0.9rem;color:#4b5563;"><?= ($userLanguage === 'ru' ? 'Название объекта' : ($userLanguage === 'uk' ? 'Назва об\'єкта' : 'Object Name')) ?></label>
            <input type="text" id="objAddName" style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;box-sizing:border-box;">
        </div>

        <div style="margin-bottom:1rem;">
            <label style="display:block;margin-bottom:0.5rem;font-size:0.9rem;color:#4b5563;"><?= ($userLanguage === 'ru' ? 'Адрес (Полный)' : ($userLanguage === 'uk' ? 'Адреса (Повна)' : 'Address (Full)')) ?></label>
            <input type="text" id="objAddAddress" readonly style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;box-sizing:border-box;background:#f3f4f6;">
        </div>

        <div style="margin-bottom:1.5rem;">
            <label style="display:block;margin-bottom:0.5rem;font-size:0.9rem;color:#4b5563;"><?= ($userLanguage === 'ru' ? 'Тип работ' : ($userLanguage === 'uk' ? 'Тип робіт' : 'Work Type')) ?></label>
            <select id="objAddWorksType" style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;box-sizing:border-box;background:#fff;">
                <option value="" disabled selected><?= ($userLanguage === 'ru' ? 'Выберите тип...' : 'Select type...') ?></option>
                <option value="Roof">Roof</option>
                <option value="Siding">Siding</option>
                <option value="External works">External works</option>
                <option value="Internal works">Internal works</option>
                <option value="Electric">Electric</option>
                <option value="Plumbing">Plumbing</option>
                <option value="Other works">Other works</option>
            </select>
        </div>

        <div style="display:flex;gap:1rem;">
            <button onclick="closeObjectModal()" style="flex:1;padding:0.75rem;border:none;border-radius:8px;background:#f3f4f6;color:#4b5563;font-size:1rem;font-weight:600;cursor:pointer;"><?= ($userLanguage === 'ru' ? 'Отмена' : 'Cancel') ?></button>
            <button onclick="saveObject()" id="btnSaveObject" style="flex:1;padding:0.75rem;border:none;border-radius:8px;background:#2563eb;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;"><?= ($userLanguage === 'ru' ? 'Добавить' : 'Add') ?></button>
        </div>
    </div>
</div>

<script>
		const userLanguage = <?= json_encode($userLanguage) ?>;
		let places = [];
		let queueItems = [];
		let pollInterval = null;
		let historyPage = 1;
		let currentUserId = null;

		function switchTab(tabId) {
			document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
			document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
			
			if(tabId === 'upload') {
				document.querySelectorAll('.tab')[0].classList.add('active');
				document.getElementById('tab-upload').classList.add('active');
			} else {
				document.querySelectorAll('.tab')[1].classList.add('active');
				document.getElementById('tab-list').classList.add('active');
				if (document.getElementById('historyList').children.length === 0) {
					loadHistory(1);
				}
			}
		}

		function escapeHtml(s) {
			if (!s) return '';
			return String(s).replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
		}

		async function initApp() {
			try {

				const [placesData, profileData] = await Promise.all([
					WebAppAPI.request('/references/objects?status=1'),
					WebAppAPI.request('/auth/me')
				]);

				const pData = profileData.data || profileData;
				currentUserId = pData.telegram_id;

				if (pData && (pData.role === 'admin' || (pData.permissions && pData.permissions.includes('objects.manage')))) {
					document.getElementById('navAddObject').style.display = 'flex';
				}

				const extractArray = (res) => Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : (res?.data?.items || res?.data?.data || res?.items || []));
				places = extractArray(placesData);

				// Заполняем селект в модалке
				const select = document.getElementById('f_place');
				places.forEach(p => {
					const opt = document.createElement('option');
					opt.value = p.id;
					opt.textContent = p.place_name || p.name;
					select.appendChild(opt);
				});

				fetchQueue();
				pollInterval = setInterval(fetchQueue, 3000);

			} catch (err) {
				console.error(err);
				alert('Ошибка инициализации: ' + err.message);
			}
		}

		async function fetchQueue() {
			try {
				const res = await WebAppAPI.request('/receipts/queue');
				if (res && res.success) {
					renderQueue(res.data);
				}
			} catch(e) {
				console.error('Polling error', e);
			}
		}

		function renderQueue(items) {
			queueItems = items;
			const container = document.getElementById('queueList');
			let html = '';
			
			if (items.length === 0) {
				container.innerHTML = '<div style="text-align:center;color:#888;padding:1rem;">' + (userLanguage === 'ru' ? 'Очередь пуста' : 'Queue is empty') + '</div>';
				return;
			}

			items.forEach(item => {
				const isPending = item.status === 'pending' || item.status === 'processing';
				const isError = item.status === 'error';
				
				let statusHtml = '';
				if (isPending) {
					statusHtml = `<div class="spinner-small"></div> ` + (userLanguage === 'ru' ? 'В очереди на распознавание' : 'Processing...');
				} else if (isError) {
					statusHtml = `<span style="color:#ef4444">` + (userLanguage === 'ru' ? 'Ошибка распознавания' : 'OCR Error') + `</span>`;
				} else {
					statusHtml = `<span class="q-status ready">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
						` + (userLanguage === 'ru' ? 'Требует проверки' : 'Needs Review') + `
					</span>`;
				}

				// Используем base64 из parsed_data.image_thumbnail если есть, иначе запрос картинки
				let imgSrc = '';
				if (item.parsed_data && item.parsed_data.image_thumbnail) {
					imgSrc = item.parsed_data.image_thumbnail;
				} else {
					imgSrc = `/api/v1/receipts/queue/${item.id}/image`;
				}

				html += `
					<div class="q-card ${isPending ? 'blurred' : ''}" onclick="openModal(${item.id})">
						<img src="${escapeHtml(imgSrc)}" class="q-thumb" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2VlZSIvPjwvc3ZnPg=='">
						<div class="q-info">
							<div class="q-title">${escapeHtml(item.original_filename)}</div>
							<div class="q-status">${statusHtml}</div>
						</div>
						<div onclick="deleteQueueItem(${item.id}); event.stopPropagation();" style="padding:0.5rem;color:#ef4444;cursor:pointer;">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
						</div>
					</div>
				`;
			});

			container.innerHTML = html;
		}

		async function compressImage(file, maxSize = 1600) {
			return new Promise((resolve) => {
				if (!file.type.startsWith('image/')) {
					return resolve(file);
				}
				const reader = new FileReader();
				reader.onload = (e) => {
					const img = new Image();
					img.onload = () => {
						const canvas = document.createElement('canvas');
						let width = img.width;
						let height = img.height;
						if (width > height) {
							if (width > maxSize) {
								height = Math.round(height * (maxSize / width));
								width = maxSize;
							}
						} else {
							if (height > maxSize) {
								width = Math.round(width * (maxSize / height));
								height = maxSize;
							}
						}
						canvas.width = width;
						canvas.height = height;
						const ctx = canvas.getContext('2d');
						ctx.drawImage(img, 0, 0, width, height);
						canvas.toBlob((blob) => {
							if (!blob) return resolve(file);
							resolve(new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() }));
						}, 'image/jpeg', 0.85);
					};
					img.onerror = () => resolve(file);
					img.src = e.target.result;
				};
				reader.onerror = () => resolve(file);
				reader.readAsDataURL(file);
			});
		}

		async function handleFileSelect(event) {
			const files = event.target.files;
			if (!files || files.length === 0) return;

			const filesArray = Array.from(files);
			const container = document.getElementById('queueList');
			const emptyText = (userLanguage === 'ru' ? 'Очередь пуста' : (userLanguage === 'uk' ? 'Черга порожня' : 'Queue is empty'));
			if (container.innerHTML.includes(emptyText)) container.innerHTML = '';
            
			// 1. Сразу рисуем карточки статуса для всех файлов до начала сжатия
			const fileCards = [];
			for (let i = 0; i < filesArray.length; i++) {
				const file = filesArray[i];
				const tempId = 'temp_prep_' + Date.now() + '_' + i;
				const statusText = userLanguage === 'ru' ? 'Сжатие фото...' : (userLanguage === 'uk' ? 'Стиснення фото...' : 'Compressing...');
				const tempHtml = `
					<div class="q-card blurred" id="${tempId}">
						<img src="${URL.createObjectURL(file)}" class="q-thumb">
						<div class="q-info">
							<div class="q-title">${escapeHtml(file.name)}</div>
							<div class="q-status" id="status_${tempId}"><div class="spinner-small"></div> ${statusText}</div>
						</div>
					</div>
				`;
				container.insertAdjacentHTML('afterbegin', tempHtml);
				fileCards.push({ originalName: file.name, tempId });
			}

			// 2. Запускаем сжатие всех файлов параллельно, чтобы заблокировать их в памяти до очистки input
			const filesData = await Promise.all(filesArray.map(f => compressImage(f, 1600)));
			
			// Теперь можно безопасно очистить input
			event.target.value = '';

			// 3. Загружаем на сервер
			for (let i = 0; i < filesData.length; i++) {
				const file = filesData[i];
				const cardMeta = fileCards[i];
				const tempId = cardMeta.tempId;

				const statusEl = document.getElementById('status_' + tempId);
				if (statusEl) {
					const loadingText = userLanguage === 'ru' ? 'Загрузка...' : (userLanguage === 'uk' ? 'Завантаження...' : 'Loading...');
					statusEl.innerHTML = `<div class="spinner-small"></div> ${loadingText}`;
				}

				const fd = new FormData();
				fd.append('file', file);
				if (file.lastModified) fd.append('file_last_modified', file.lastModified);

				try {
					const res = await WebAppAPI.request('/receipts/queue', 'POST', fd);
					if (!res || !res.success) throw new Error(res.error || 'Upload failed');
					
					// После успешной загрузки удаляем временную карточку
					const cardEl = document.getElementById(tempId);
					if (cardEl) cardEl.remove();

				} catch (e) {
					console.error(e);
					const errAlert = (userLanguage === 'ru' ? 'Ошибка загрузки файла ' : 'Upload error for ') + file.name + ': ' + e.message;
					alert(errAlert);
					const cardEl = document.getElementById(tempId);
					if (cardEl) {
						const errText = userLanguage === 'ru' ? 'Ошибка' : 'Error';
						cardEl.querySelector('.q-status').innerHTML = `<span style="color:#ef4444">${errText}</span>`;
					}
				}
			}
			
			// обновим общую очередь после всех загрузок
			fetchQueue(); 
		}

		async function deleteQueueItem(id) {
			if (!confirm(userLanguage === 'ru' ? 'Удалить чек из очереди?' : 'Delete from queue?')) return;
			try {
				const res = await WebAppAPI.request('/receipts/queue/' + id, 'DELETE');
				if (res && res.success) {
					fetchQueue();
				}
			} catch(e) {
				alert((userLanguage === 'ru' ? 'Ошибка удаления: ' : 'Delete error: ') + e.message);
			}
		}

		function openModal(id) {
			const item = queueItems.find(i => i.id == id);
			if (!item || item.status === 'pending' || item.status === 'processing') {
				// Не даем открыть если еще в обработке
				if(item) window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred('warning');
				return; 
			}

			document.getElementById('f_queue_id').value = item.id;
			
			// Заполняем данными
			let date = '';
			let time = '';
			let subtotal = '';
			let tax = '';
			let amount = '';
			let type = 'other';
			let payment_method = 'card';

			if (item.parsed_data) {
				date = item.parsed_data.receipt_date || '';
				time = item.parsed_data.receipt_time || '';
				subtotal = item.parsed_data.subtotal || '';
				tax = item.parsed_data.tax || '';
				amount = item.parsed_data.receipt_amount || '';
				type = item.parsed_data.receipt_type || 'other';
				payment_method = item.parsed_data.payment_method || 'card';
			}

			if (!date) date = new Date().toISOString().split('T')[0];

			document.getElementById('f_date').value = date;
			document.getElementById('f_time').value = time;
			document.getElementById('f_subtotal').value = subtotal;
			document.getElementById('f_tax').value = tax;
			document.getElementById('f_amount').value = amount;
			document.getElementById('f_category').value = type;
			document.getElementById('f_payment_method').value = payment_method;
			document.getElementById('f_place').value = item.global_object || '';
			document.getElementById('f_comment').value = '';

			document.getElementById('modalOverlay').style.display = 'flex';
		}

		function closeModal() {
			document.getElementById('modalOverlay').style.display = 'none';
		}

		async function saveReceipt() {
			const id = document.getElementById('f_queue_id').value;
			const place_id = document.getElementById('f_place').value;
			const date = document.getElementById('f_date').value;
			const time = document.getElementById('f_time').value;
			const subtotal = document.getElementById('f_subtotal').value;
			const tax = document.getElementById('f_tax').value;
			const amount = document.getElementById('f_amount').value;
			const type = document.getElementById('f_category').value;
			const payment_method = document.getElementById('f_payment_method').value;
			const comment = document.getElementById('f_comment').value;

			if (!place_id) return alert(userLanguage === 'ru' ? 'Выберите объект!' : 'Select Workplace!');
			if (!date) return alert(userLanguage === 'ru' ? 'Укажите дату!' : 'Enter date!');
			if (!amount || isNaN(parseFloat(amount))) return alert(userLanguage === 'ru' ? 'Укажите корректную сумму!' : 'Enter correct amount!');

			const btn = document.getElementById('btnSaveReceipt');
			btn.disabled = true;
			btn.textContent = 'Сохранение...';

			try {
				const item = queueItems.find(i => i.id == id);
				const payload = {
					place_id: place_id,
					receipt_date: date,
					receipt_time: time,
					subtotal: subtotal,
					tax: tax,
					receipt_amount: amount,
					receipt_type: type,
					payment_method: payment_method,
					comment: comment
				};

				// Копируем остальные распарсенные данные если нужно, хотя API сохранит все что в parsed_data
				if (item && item.parsed_data) {
					payload.merchant_name = item.parsed_data.merchant_name;
					payload.merchant_address = item.parsed_data.merchant_address;
					payload.card_last4 = item.parsed_data.card_last4;
					payload.items_json = JSON.stringify(item.parsed_data.items || []);
				}

				const res = await WebAppAPI.request(`/receipts/queue/${id}/save`, 'POST', payload);
				if (res && res.success) {
					window.Telegram?.WebApp?.HapticFeedback?.notificationOccurred('success');
					closeModal();
					fetchQueue(); // обновит список очереди
					
					// Если мы уже загружали вкладку списка, сбросим ее
					if (document.getElementById('historyList').children.length > 0) {
						loadHistory(1);
					}
				} else {
					throw new Error(res.message || 'Error saving');
				}
			} catch (e) {
				alert('Ошибка сохранения: ' + e.message);
			} finally {
				btn.disabled = false;
				btn.textContent = 'Сохранить';
			}
		}

		async function loadHistory(page = 1) {
			const btn = document.getElementById('btnLoadMore');
			if (page === 1) {
				document.getElementById('historyList').innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner-small" style="display:inline-block"></div></div>';
				btn.style.display = 'none';
			}

			try {
				let url = `/receipts?page=${page}&limit=15`;
				if (currentUserId) {
					url += `&telegram_id=${currentUserId}`;
				}
				const res = await WebAppAPI.request(url);
				const extractArray = (res) => Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : (res?.data?.items || res?.data?.data || res?.items || []));
				const items = extractArray(res);
				
				let html = '';
				if (items.length === 0 && page === 1) {
					html = '<div style="text-align:center;color:#888;padding:2rem;">' + (userLanguage === 'ru' ? 'Чеков не найдено' : 'No receipts found') + '</div>';
				} else {
					items.forEach(r => {
						html += `
							<div class="h-card">
								<div class="h-info">
									<div class="h-place">${escapeHtml(r.place_name || 'Неизвестно')}</div>
									<div class="h-date">${escapeHtml(r.date)} &bull; ${escapeHtml(r.merchant_name || 'Без мерчанта')}</div>
								</div>
								<div class="h-amount">$${parseFloat(r.amount).toFixed(2)}</div>
							</div>
						`;
					});
				}

				const container = document.getElementById('historyList');
				if (page === 1) {
					container.innerHTML = html;
				} else {
					container.insertAdjacentHTML('beforeend', html);
				}

				historyPage = page;
				
				// Если вернулось меньше limit (15), то больше загружать нечего
				if (items.length < 15) {
					btn.style.display = 'none';
				} else {
					btn.style.display = 'block';
				}

			} catch (e) {
				console.error(e);
				if(page === 1) document.getElementById('historyList').innerHTML = '<div style="color:red;padding:1rem;">Ошибка загрузки</div>';
			}
		}

		initApp();

		function openObjectModal() {
			document.getElementById('modalObjectAdd').style.display = 'flex';
		}

		function closeObjectModal() {
			document.getElementById('modalObjectAdd').style.display = 'none';
			document.getElementById('objSearchAddress').value = '';
			document.getElementById('objAddName').value = '';
			document.getElementById('objAddAddress').value = '';
			document.getElementById('objAddWorksType').value = '';
			document.getElementById('objAutocompleteResults').style.display = 'none';
		}

		let objDebounceTimer;
		document.getElementById('objSearchAddress').addEventListener('input', function(e) {
			clearTimeout(objDebounceTimer);
			const query = e.target.value.trim();
			const resultsDiv = document.getElementById('objAutocompleteResults');
			
			if (query.length < 3) {
				resultsDiv.style.display = 'none';
				return;
			}
			
			objDebounceTimer = setTimeout(async () => {
				try {
					const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&addressdetails=1&countrycodes=ca&limit=5`, {
						headers: { 'Accept-Language': 'en' }
					});
					const data = await res.json();
					
					resultsDiv.innerHTML = '';
					if (data.length > 0) {
						data.forEach(item => {
							const div = document.createElement('div');
							div.style.padding = '10px';
							div.style.borderBottom = '1px solid #eee';
							div.style.cursor = 'pointer';
							div.textContent = item.display_name;
							div.onclick = () => {
								document.getElementById('objSearchAddress').value = item.display_name;
								document.getElementById('objAddAddress').value = item.display_name;
								resultsDiv.style.display = 'none';
								
								const addr = item.address || {};
								const house = addr.house_number || '';
								const street = addr.road || '';
								const city = addr.city || addr.town || addr.village || '';
								
								let parts = [];
								if (house && street) parts.push(`${house} ${street}`);
								else if (street) parts.push(street);
								else if (house) parts.push(house);
								
								if (city) parts.push(city);
								
								let objName = parts.join(', ');
								if (!objName) objName = item.display_name.split(',')[0];
								
								document.getElementById('objAddName').value = objName;
							};
							resultsDiv.appendChild(div);
						});
						resultsDiv.style.display = 'block';
					} else {
						resultsDiv.style.display = 'none';
					}
				} catch (err) {
					console.error('Autocomplete error', err);
				}
			}, 500);
		});

		async function saveObject() {
			const name = document.getElementById('objAddName').value.trim();
			const address = document.getElementById('objAddAddress').value.trim();
			const worksType = document.getElementById('objAddWorksType').value;
			
			if (!name) {
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Введите название объекта' : 'Enter object name');
				return;
			}
			if (!address) {
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Выберите адрес' : 'Select address');
				return;
			}
			if (!worksType) {
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Выберите тип работ' : 'Select work type');
				return;
			}

			const btn = document.getElementById('btnSaveObject');
			btn.disabled = true;
			btn.textContent = 'Сохранение...';

			try {
				const response = await fetch(API_URL + 'objects', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-Telegram-Id': telegramId,
						'X-Bot-Token': ''
					},
					body: JSON.stringify({
						place_name: name,
						place_address: address,
						works_type: worksType
					})
				});

				const res = await response.json();
				if (res.success) {
					window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Объект успешно добавлен!' : 'Object added!');
					closeObjectModal();
				} else {
					window.Telegram.WebApp.showAlert(res.error || 'Ошибка при сохранении');
				}
			} catch (error) {
				console.error('Error adding object:', error);
				window.Telegram.WebApp.showAlert('Сетевая ошибка при добавлении объекта');
			} finally {
				btn.disabled = false;
				btn.textContent = 'Добавить';
			}
		}
	</script>
</body>
</html>
