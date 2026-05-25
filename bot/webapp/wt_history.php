<?php
date_default_timezone_set('America/Halifax');

$APP_VERSION = '1.0.31';
$showlogdata = false;

// Подключаем i18n
require_once('../lib/i18n.php');
$userLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
I18n::load($userLanguage);

function translate($key) {
	return __('webapp.wt.'.$key);
}

// Translations missing in wt? We can just use hardcoded ru/uk for now or rely on existing keys if they fit.
// But the user said: "таблица должна быть компактной чтоб нормально отображалась на мобильном, также для этой страницы сделай версию v1.0.0"
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($userLanguage) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title>История смен</title>
	<style>
		:root{
			--radius:.2rem;
			--btn-pad-y:.95rem;
			--btn-pad-x:1.2rem;
			--font-lg:1.125rem;
		}
		body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:1rem;padding-bottom:100px;background:#ddd;color:#000;margin:0;}
		h2{font-size:1.75rem;margin:0 0 1rem 0}

		.period-title { font-size: 1.0rem; font-weight: bold; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #444; }
		.history-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: var(--radius); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
		.history-table th, .history-table td { padding: 0.5rem 0.3rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.75rem; }
		.history-table th { background: #f0f0f0; color: #555; font-weight: 600; }
		.history-table tr:last-child td { border-bottom: none; }
		.history-table td.col-date { white-space: nowrap; width: 1%; color: #666; }
		.history-table td.col-place { word-wrap: break-word; white-space: normal; }
		.history-table td.col-start, .history-table td.col-end { white-space: nowrap; width: 1%; color: #444; }
		.history-table td.col-hours { white-space: nowrap; width: 1%; text-align: right; font-weight: 500; }
		
		.empty-message { text-align: center; padding: 2rem 1rem; color: #888; background: #f9f9f9; border-radius: var(--radius); border: 1px dashed #ccc; }

		/* Навигация внизу */
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
			overflow-x: auto;
		}
		.bottom-nav::-webkit-scrollbar { display: none; }
		
		.nav-btn {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			background: none;
			border: none;
			padding: 0.5rem 0;
			color: #666;
			text-decoration: none;
			font-size: 0.75rem;
			border-radius: var(--radius);
			min-width: 60px;
		}
		.nav-btn.active {
			color: #0b66ff;
			background: #f0f6ff;
			font-weight: 600;
		}
		.nav-btn svg { width: 24px; height: 24px; margin-bottom: 4px; }

		.app-version{
			position:fixed;
			top:2px;
			right:3px;
			font-size:6px;
			line-height:1;
			color:#000;
			opacity:.45;
			z-index:1000;
			pointer-events:none;
			font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
		}
		
		.spinner-container { text-align: center; padding: 3rem; }
		.spinner{display:inline-block;width:2em;height:2em;border:.25em solid #d0d0d0;border-top-color:#0b66ff;border-radius:50%;animation:spin 1s linear infinite;}
		@keyframes spin{to{transform:rotate(360deg)}}
	</style>
	<script src="https://telegram.org/js/telegram-web-app.js"></script>
	<script src="auth.js?v=<?= $APP_VERSION ?>"></script>
</head>
<body>
	<div class="app-version">v<?= $APP_VERSION ?></div>

	<h2 style="text-transform: uppercase; font-size: 1.25rem; font-weight: bold; margin-bottom: 0.2rem; color: #1e3a8a;"><?= translate('history_title') ?></h2>

	<div id="content">
		<div class="spinner-container"><div class="spinner"></div></div>
	</div>

	<!-- Нижнее меню -->
	<?php include 'nav.php'; ?>



<script>
		const userLanguage = "<?= $userLanguage ?>";
		
		function escapeHtml(s) {
			if (!s) return '';
			return String(s).replace(/[&<>'"]/g, c => ({
				'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'
			}[c]));
		}

		function formatDateString(dateObj) {
			return dateObj.getFullYear() + '-' + 
				String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + 
				String(dateObj.getDate()).padStart(2, '0');
		}

		function getHalfMonthPeriods() {
			const now = new Date();
			const year = now.getFullYear();
			const month = now.getMonth(); // 0-11
			const day = now.getDate();

			let currentFrom, currentTo;
			let prevFrom, prevTo;

			if (day <= 15) {
				// Текущий: с 1 по 15 число
				currentFrom = new Date(year, month, 1);
				currentTo = new Date(year, month, 15);
				
				// Прошлый: с 16 по конец прошлого месяца
				let prevMonth = month - 1;
				let prevYear = year;
				if (prevMonth < 0) { prevMonth = 11; prevYear--; }
				
				prevFrom = new Date(prevYear, prevMonth, 16);
				prevTo = new Date(prevYear, prevMonth + 1, 0); // Последний день прошлого месяца
			} else {
				// Текущий: с 16 по конец месяца
				currentFrom = new Date(year, month, 16);
				currentTo = new Date(year, month + 1, 0);

				// Прошлый: с 1 по 15 этого месяца
				prevFrom = new Date(year, month, 1);
				prevTo = new Date(year, month, 15);
			}

			return {
				current: { from: currentFrom, to: currentTo },
				prev: { from: prevFrom, to: prevTo }
			};
		}

		function formatPeriodName(periodObj) {
			const months = userLanguage === 'ru' ? 
				['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'] :
				userLanguage === 'uk' ?
				['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'] :
				['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
			
			const d1 = periodObj.from.getDate();
			const m1 = months[periodObj.from.getMonth()];
			const d2 = periodObj.to.getDate();
			const m2 = months[periodObj.to.getMonth()];
			
			if (periodObj.from.getMonth() === periodObj.to.getMonth()) {
				return `${d1}-${d2} ${m1}`;
			} else {
				return `${d1} ${m1} - ${d2} ${m2}`;
			}
		}

		function renderTable(entries, periodName) {
			if (!entries || entries.length === 0) {
				return `<div class="empty-message"><?= translate('no_data') ?><br><b>${periodName}</b></div>`;
			}

			let rowsHtml = '';
			let totalMinutes = 0;

			// Сортировка по возрастанию даты
			entries.sort((a, b) => new Date(a.workday) - new Date(b.workday));

			entries.forEach(w => {
				const d = new Date(w.workday + 'T00:00:00');
				const dateOpts = { month: '2-digit', day: '2-digit' };
				const dateStr = d.toLocaleDateString(userLanguage === 'ru' ? 'ru-RU' : 'en-US', dateOpts);

				const formatTime = (ts) => {
					if (!ts || ts.startsWith('0000')) return '...';
					const parts = ts.split(' ')[1].split(':');
					return parts[0] + ':' + parts[1];
				};

				let badgeStr = '...';
				let closed = w.checkout && w.checkout !== '0000-00-00 00:00:00';
				
				if (closed && w.work_minutes_rounded !== undefined && w.work_minutes_rounded !== null) {
					totalMinutes += parseFloat(w.work_minutes_rounded) || 0;
					const h = Math.floor(w.work_minutes_rounded / 60);
					const m = w.work_minutes_rounded % 60;
					badgeStr = '';
					if (h > 0) badgeStr += h + 'h ';
					if (m > 0 || h === 0) badgeStr += m + 'm';
					badgeStr = badgeStr.trim();
				} else if (closed) {
					badgeStr = '0m';
				} else {
					badgeStr = '<?= translate("status_active") ?: "Active" ?>';
				}

				rowsHtml += `
					<tr>
						<td class="col-date">${dateStr}</td>
						<td class="col-place">${escapeHtml(w.place_name || 'Неизвестный объект')}</td>
						<td class="col-start">${formatTime(w.checkin)}</td>
						<td class="col-end">${formatTime(w.checkout)}</td>
						<td class="col-hours">${badgeStr}</td>
					</tr>
				`;
			});

			let totalHtml = '';
			if (totalMinutes > 0) {
				const th = Math.floor(totalMinutes / 60);
				const tm = totalMinutes % 60;
				totalHtml = `
					<tfoot>
						<tr>
							<th colspan="4" style="text-align: right;"><?= translate('total') ?></th>
							<th class="col-hours">${th > 0 ? th + 'h ' : ''}${tm}m</th>
						</tr>
					</tfoot>
				`;
			}

			return `
				<table class="history-table">
					<thead>
						<tr>
							<th class="col-date"><?= translate('col_date') ?></th>
							<th class="col-place"><?= translate('col_place') ?></th>
							<th class="col-start"><?= translate('col_start') ?></th>
							<th class="col-end"><?= translate('col_end') ?></th>
							<th class="col-hours"><?= translate('col_hours') ?></th>
						</tr>
					</thead>
					<tbody>
						${rowsHtml}
					</tbody>
					${totalHtml}
				</table>
			`;
		}

		async function initApp() {
			try {

				const periods = getHalfMonthPeriods();
				const pFrom = formatDateString(periods.prev.from);
				const pTo = formatDateString(periods.current.to);

				// Сначала получаем профиль, чтобы знать наш telegram_id
				const profileReq = await WebAppAPI.request('/auth/me');
				const profileData = profileReq.data || profileReq;
				const tid = profileData.telegram_id;

				if (profileData && (profileData.role === 'admin' || (profileData.permissions && profileData.permissions.includes('objects.manage')))) {
					document.getElementById('navAddObject').style.display = 'flex';
				}

				// Теперь запрашиваем смены только для текущего пользователя
				const timeData = await WebAppAPI.request(`/time-entries?date_from=${pFrom}&date_to=${pTo}&limit=100&telegram_id=${tid}`);

				const extractArray = (res) => Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : (res?.data?.items || res?.data?.data || res?.items || []));
				const entries = extractArray(timeData);

				// Разбиваем на два периода
				const currentEntries = [];
				const prevEntries = [];

				const cFromStr = formatDateString(periods.current.from);
				const cToStr = formatDateString(periods.current.to);
				const pFromStr = formatDateString(periods.prev.from);
				const pToStr = formatDateString(periods.prev.to);

				entries.forEach(e => {
					if (e.workday >= cFromStr && e.workday <= cToStr) {
						currentEntries.push(e);
					} else if (e.workday >= pFromStr && e.workday <= pToStr) {
						prevEntries.push(e);
					}
				});

				const content = document.getElementById('content');
				content.innerHTML = `
					<div class="period-title"><?= translate('period_curr') ?>: ${formatPeriodName(periods.current)}</div>
					${renderTable(currentEntries, formatPeriodName(periods.current))}
					
					<div class="period-title" style="margin-top: 2rem;"><?= translate('period_prev') ?>: ${formatPeriodName(periods.prev)}</div>
					${renderTable(prevEntries, formatPeriodName(periods.prev))}
				`;

			} catch (err) {
				console.error('Error loading history:', err);
				document.getElementById('content').innerHTML = `<div class="empty-message" style="color:red;"><?= translate('error_loading') ?: 'Error loading history' ?>: ${err.message}</div>`;
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
				window.Telegram.WebApp.showAlert('Введите или выберите название объекта');
				return;
			}
			if (!address) {
				window.Telegram.WebApp.showAlert('Выберите полный адрес из подсказок');
				return;
			}
			if (!worksType) {
				window.Telegram.WebApp.showAlert('Выберите тип работ');
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
					window.Telegram.WebApp.showAlert('Объект успешно добавлен!');
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
