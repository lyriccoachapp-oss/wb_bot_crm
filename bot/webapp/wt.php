<?php
date_default_timezone_set('America/Halifax');

$APP_VERSION = '1.0.32';
$showlogdata = false;

// Подключаем i18n
require_once('../lib/i18n.php');
$userLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
I18n::load($userLanguage);

function translate($key) {
return __('webapp.wt.'.$key);
}

$date = date('Y-m-d');
$today = $date;
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($userLanguage) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= translate('checkin_title') ?></title>
	<style>
		:root{
			--radius:.2rem; /* ~3px */
			--btn-pad-y:.95rem;
			--btn-pad-x:1.2rem;
			--font-lg:1.125rem;
		}
		body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;padding:1rem;background:#ddd;color:#000}
		h2{font-size:1.75rem;margin:0 0 1rem 0}

		.entry,.add-block{border:1px solid #ccc;padding:1rem;margin-bottom:1rem;border-radius:var(--radius);background:#f9f9f9}
		.entry[data-closed="0"]{animation:pulse 2s infinite}
		@keyframes pulse{0%{background:#fff8e1}50%{background:#ffecb3}100%{background:#fff8e1}}
		
		.entry[data-closed="0"].overdue { animation: pulse-red 2s infinite }
		@keyframes pulse-red{0%{background:#ffebee}50%{background:#ffcdd2}100%{background:#ffebee}}

		.add-block{text-align:center;font-size:2rem;background:#f0f0f0;cursor:pointer;user-select:none}
		.add-block.disabled{opacity:.4;pointer-events:none}

		#modalOverlay{position:fixed;top:0;left:0;right:0;bottom:5rem;display:none;align-items:center;justify-content:center;background:rgba(255,255,255,.9);z-index:999;padding:1rem;box-sizing:border-box}
		#modalContent{background:#fff;width:min(92vw,560px);padding:1rem;border-radius:var(--radius);box-shadow:0 2px 16px rgba(0,0,0,.2);max-height:100%;overflow:auto}
		@supports (height:100svh){#modalOverlay{padding:2svh 1rem}#modalContent{max-height:100%}}
		@supports (height:100dvh){#modalOverlay{padding:2dvh 1rem}#modalContent{max-height:100%}}

		.btn{padding:var(--btn-pad-y) var(--btn-pad-x);border:none;border-radius:var(--radius);cursor:pointer;background:#0b66ff;color:#fff;font-size:var(--font-lg);font-weight:600}
		.btn-secondary{background:#666}
		.btn:disabled{opacity:.6;cursor:not-allowed}

		.radio-group{max-height:50vh;overflow:auto;border:1px solid #ccc;padding:.5rem;border-radius:var(--radius);background:#fff}
		.radio-group label{display:block;padding:.4rem 0}

		input[type="text"],textarea{width:100%;box-sizing:border-box;padding:.75rem .85rem;border:1px solid #ccc;border-radius:var(--radius);font-size:1rem}
		textarea{min-height:28svh}

		.spinner{display:inline-block;width:1em;height:1em;border:.18em solid #d0d0d0;border-top-color:#0b66ff;border-radius:50%;animation:spin 1s linear infinite;vertical-align:middle;margin-right:.5rem}
		@keyframes spin{to{transform:rotate(360deg)}}
		.muted{opacity:.75}
		.footer-actions{display:flex;gap:.6rem;margin-top:1rem;justify-content:space-between;align-items:center}
		.footer-actions .btn-secondary{margin-left:auto}
		.entry-label{font-weight:600;color:#666;margin-right:.25rem}
		.geo-status{font-size:.95rem;display:flex;align-items:center;gap:.35rem}
		.geo-status.geo-getting{color:#8a6d3b}
		.geo-status.geo-ready{color:#2e7d32}
		.geo-status.geo-error{color:#c62828}
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
		.app-coords{
			position:fixed;
			top:2px;
			left:3px;
			font-size:6px;
			line-height:1;
			color:#000;
			opacity:.45;
			z-index:1000;
			pointer-events:none;
			font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
		}
		#geoLog{
			margin-top:1rem;
			padding:.5rem;
			background:#222;
			color:#0f0;
			font-family:monospace;
			font-size:.6rem;
			border-radius:var(--radius);
			max-height:150px;
			overflow-y:auto;
			word-break:break-word;
			white-space:pre-wrap;
		}
		.log-line{border-bottom:1px solid #333;padding:2px 0}
		.shift-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; font-size: 1rem; }
		.shift-date { font-weight: 500; font-size: 1.05rem; color: #1e3a8a; }
		.shift-badge { background: #e5e7eb; color: #374151; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.85rem; font-weight: 500; }
		.shift-place, .shift-time { display: flex; align-items: center; gap: 0.4rem; font-size: 1rem; color: #111827; margin-bottom: 0.4rem; }
		.shift-divider { border-top: 1px solid #e5e7eb; margin: 0.5rem 0; }
		.shift-comment { color: #4b5563; font-size: 0.95rem; font-style: italic; }
		.bottom-nav {
			position: fixed; bottom: 0; left: 0; right: 0;
			background: #fff; border-top: 1px solid #e5e7eb;
			display: flex; justify-content: space-around; align-items: center;
			padding: 0.4rem 0.25rem; padding-bottom: calc(0.4rem + env(safe-area-inset-bottom, 0px));
			z-index: 100;
		}
		.nav-item {
			display: flex; flex-direction: column; align-items: center; justify-content: center;
			color: #4b5563; text-decoration: none; font-size: 0.75rem; font-weight: 500;
			padding: 0.4rem 0.8rem; border-radius: 14px; transition: all 0.2s;
			gap: 0.25rem;
		}
		.nav-item.active { color: #0f172a; background-color: #f1f5f9; }
		.nav-item svg { width: 24px; height: 24px; stroke-width: 1.8; }
		body { padding-bottom: 5rem; }
	</style>
	<script src="https://telegram.org/js/telegram-web-app.js"></script>
	<script src="auth.js?v=<?= $APP_VERSION ?>"></script>
</head>
<body>
<div class="app-version">v<?= htmlspecialchars($APP_VERSION) ?></div>
<?php if ($showlogdata): ?>
<div class="app-coords" id="appCoords">Coords: ...</div>
<?php endif; ?>

<h2 style="text-transform: uppercase; font-size: 1.25rem; font-weight: bold; margin-bottom: 0.2rem; color: #1e3a8a;"><?= translate('checkin_title') ?></h2>

<div id="entries">
    <!-- Сюда будут загружены смены через JS -->
    <div style="text-align:center; padding: 2rem;" id="loadingIndicator">
        <div class="spinner"></div> <?= ($userLanguage === 'ru' ? 'Загрузка...' : ($userLanguage === 'uk' ? 'Завантаження...' : 'Loading...')) ?>
    </div>
</div>

<div id="pageDate" style="font-size: 1rem; color: #4b5563; margin-bottom: 0.5rem; text-transform: capitalize; margin-top: 1rem;"></div>
<div class="add-block disabled" id="addBlock" onclick="startCheckin()">+</div>

<?php include 'nav.php'; ?>

<?php if ($showlogdata): ?>
<div id="geoLog"></div>
<?php endif; ?>

<div class="modal-overlay" id="modalOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:5rem;align-items:center;justify-content:center;background:rgba(255,255,255,.9);z-index:999;padding:1rem;box-sizing:border-box">
	<div class="modal" id="modalBody" style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.15);width:100%;max-width:400px;max-height:100%;overflow-y:auto;"></div>
</div>



<script>
	const userLanguage = <?= json_encode($userLanguage) ?>;
	
	// Устанавливаем текущую дату в заголовок
	document.addEventListener('DOMContentLoaded', () => {
		const pageDateObj = new Date();
		const pageDateOpts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
		const pageDateStr = pageDateObj.toLocaleDateString(userLanguage === 'ru' ? 'ru-RU' : 'en-US', pageDateOpts);
		document.getElementById('pageDate').innerText = pageDateStr;
	});

	// ====== КОНСТАНТЫ ======
	const GEO_OPTIONS = {enableHighAccuracy:true, maximumAge:0, timeout:15000};
	const GEO_WAIT_TIMEOUT = 5000;

	// ====== ДАННЫЕ ======
const texts = <?= json_encode(['location_ready' => translate('location_ready'), 'location_error' => translate('location_error'), 'getting_location' => translate('getting_location'), 'finish_work' => translate('finish_work'), 'enter_comment' => translate('enter_comment'), 'checkout' => translate('checkout'), 'close' => translate('close'), 'checkout_error' => translate('checkout_error'), 'select_object' => translate('select_object'), 'search' => translate('search'), 'checkin' => translate('checkin'), 'checkin_error' => translate('checkin_error'), 'select_place' => translate('select_place'), 'date_label' => translate('date_label'), 'start' => translate('start'), 'completed' => translate('completed'), 'comment' => translate('comment'), 'object' => translate('object')], JSON_UNESCAPED_UNICODE) ?>;
let places = [];
let currentWork = false;
const todayStr = <?= json_encode($today) ?>;
const yesterdayStr = <?= json_encode($yesterday) ?>;

// ====== ИНИЦИАЛИЗАЦИЯ ======
async function initApp() {
	try {
		const tgUserId = window.Telegram?.WebApp?.initDataUnsafe?.user?.id || '';
		
		// Оптимизация: загружаем только объекты и смены для быстрого рендера
		const [placesData, timeData] = await Promise.all([
			WebAppAPI.request('/references/objects?status=1'), // Только активные!
			WebAppAPI.request(`/time-entries?date_from=${yesterdayStr}&date_to=${todayStr}&include_open=1` + (tgUserId ? `&telegram_id=${tgUserId}` : ''))
		]);
		
		const extractArray = (res) => Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : (res?.data?.items || res?.data?.data || res?.items || []));

		places = extractArray(placesData);
		const workEntries = extractArray(timeData);

		// Рендерим смены как можно быстрее
		renderEntries(workEntries);

		// Профиль загружаем в фоне, он нужен только для показа кнопки "Объекты"
		WebAppAPI.request('/auth/me')
			.then(profileData => {
				if (profileData && profileData.data && (profileData.data.role === 'admin' || (profileData.data.permissions && profileData.data.permissions.includes('objects.manage')))) {
					const navObj = document.getElementById('navAddObject');
					if (navObj) navObj.style.display = 'flex';
				}
			})
			.catch(err => console.error('Profile fetch error:', err));

	} catch (err) {
		console.error(err);
		document.getElementById('entries').innerHTML = `<div style="text-align:center;color:red;padding:1rem;">` + (userLanguage === 'ru' ? 'Ошибка загрузки данных' : (userLanguage === 'uk' ? 'Помилка завантаження даних' : 'Error loading data')) + `: ${err.message}</div>`;
	}
}

function buildEntryHTML(w, pname, closed) {
	// Исправление проблемы часового пояса, заставляем парсить в локальном времени
	const d = new Date(w.workday + 'T00:00:00');
	const dateOpts = { weekday: 'short', month: 'short', day: 'numeric' };
	const dateStr = d.toLocaleDateString(userLanguage === 'ru' ? 'ru-RU' : 'en-US', dateOpts).toUpperCase();

	const formatTime = (ts) => {
		if (!ts || ts.startsWith('0000')) return '...';
		const parts = ts.split(' ')[1].split(':');
		return parts[0] + ':' + parts[1];
	};

	let badgeStr = '...';
	if (closed && w.work_minutes_rounded !== undefined && w.work_minutes_rounded !== null) {
		const h = Math.floor(w.work_minutes_rounded / 60);
		const m = w.work_minutes_rounded % 60;
		badgeStr = '';
		if (h > 0) badgeStr += h + 'h ';
		if (m > 0 || h === 0) badgeStr += m + 'm';
		badgeStr = badgeStr.trim();
	} else if (closed) {
		badgeStr = '0m';
	} else {
		badgeStr = '...';
	}

	const timeStr = formatTime(w.checkin) + ' - ' + formatTime(w.checkout);

	let html = `
	<div class="shift-header">
		<div class="shift-date">${escapeHtml(dateStr)}</div>
		<div class="shift-badge">${escapeHtml(badgeStr)}</div>
	</div>
	<div class="shift-place">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
		${escapeHtml(pname)}
	</div>
	<div class="shift-time">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
		${escapeHtml(timeStr)}
	</div>
	`;

	const noComment = userLanguage === 'ru' ? 'Нет' : (userLanguage === 'uk' ? 'Немає' : 'None');
	const commentText = w.work_desc ? escapeHtml(w.work_desc) : noComment;
	
	html += `
	<div class="shift-divider"></div>
	<div class="shift-comment">${escapeHtml(texts.comment)}: ${commentText}</div>
	`;

	return html;
}

function renderEntries(entries) {
const container = document.getElementById('entries');
container.innerHTML = '';
let hasOpen = false;

const placeMap = {};
places.forEach(p => { placeMap[p.id] = p.name || p.place_name; });

entries.reverse(); // Хронологический порядок

entries.forEach(w => {
const closed = w.checkout && w.checkout !== '0000-00-00 00:00:00';
if (!closed) hasOpen = true;

const pname = placeMap[w.place_id] || texts.object;

const div = document.createElement('div');
div.className = 'entry';
div.dataset.id_worktime = w.id;
div.dataset.closed = closed ? '1' : '0';

if (!closed) {
	let checkinTimeStr = w.checkin;
	if (!checkinTimeStr || checkinTimeStr === '0000-00-00 00:00:00') {
		checkinTimeStr = w.workday + ' 00:00:00';
	}
	const checkinTime = new Date(checkinTimeStr.replace(' ', 'T'));
	const hoursOpen = (new Date() - checkinTime) / (1000 * 60 * 60);
	if (hoursOpen >= 12) {
		div.classList.add('overdue');
	}
}

div.onclick = function() { handleEntryClick(this); };

div.innerHTML = buildEntryHTML(w, pname, closed);
container.appendChild(div);
});

currentWork = hasOpen;
const addBlock = document.getElementById('addBlock');
if (hasOpen) {
addBlock.classList.add('disabled');
} else {
addBlock.classList.remove('disabled');
}
}


	// ====== LOGGING ======
	function logGeo(msg) {
		const logEl = document.getElementById('geoLog');
		if (!logEl) return;
		const now = new Date();
		const time = now.toLocaleTimeString('en-GB', {hour12:false}) + '.' + String(now.getMilliseconds()).padStart(3, '0');
		const line = document.createElement('div');
		line.className = 'log-line';
		line.textContent = `[${time}] ${msg}`;
		logEl.appendChild(line);
		logEl.scrollTop = logEl.scrollHeight;
		console.log(`[GeoLog] ${msg}`);
	}

	// ====== GEO ======
	const GEO = {
		watchId:null,
		last:null,
		error:null
	};

	function updateGeoStatus(state){
		const el = document.getElementById('geoStatus');
		if (el) {
			el.classList.remove('geo-getting', 'geo-ready', 'geo-error');
			if (state === 'ready') {
				el.classList.add('geo-ready');
				el.textContent = '🟢 ' + (texts.location_ready || 'Location ready');
			} else if (state === 'error') {
				el.classList.add('geo-error');
				el.textContent = '🔴 ' + (texts.location_error || 'Location error');
			} else {
				el.classList.add('geo-getting');
				el.textContent = '🟡 ' + (texts.getting_location || 'Getting location');
			}
		}
		
		const coordsEl = document.getElementById('appCoords');
		if (coordsEl) {
			if (state === 'ready' && GEO.last) {
				const c = GEO.last.coords;
				coordsEl.textContent = `${c.latitude.toFixed(5)}, ${c.longitude.toFixed(5)} (±${Math.round(c.accuracy)}m)`;
			} else if (state === 'error') {
				coordsEl.textContent = 'Coords: unavailable';
			} else {
				if (!GEO.last) coordsEl.textContent = 'Coords: ...';
			}
		}
	}

	function syncGeoStatus(){
		if (GEO.last) {
			updateGeoStatus('ready');
			return;
		}
		if (GEO.error) {
			updateGeoStatus('error');
			return;
		}
		updateGeoStatus('getting');
	}

	function startGeoTracking(){
		logGeo('startGeoTracking called');
		if (!('geolocation' in navigator)) {
			const err = new Error('Geolocation unavailable');
			GEO.error = err;
			logGeo('Error: Geolocation API not supported');
			updateGeoStatus('error');
			return;
		}
		if (GEO.watchId !== null) {
			logGeo('watchPosition already active (id=' + GEO.watchId + ')');
			return;
		}
		logGeo('Starting watchPosition...');
		GEO.watchId = navigator.geolocation.watchPosition(handleGeoSuccess, handleGeoError, GEO_OPTIONS);
		logGeo('watchPosition started, id=' + GEO.watchId);
	}

	function stopGeoTracking(){
		if (GEO.watchId !== null) {
			logGeo('Stopping watchPosition (id=' + GEO.watchId + ')');
			navigator.geolocation.clearWatch(GEO.watchId);
			GEO.watchId = null;
		}
	}

	function handleGeoSuccess(pos){
		const c = pos.coords;
		logGeo(`Updated: ${c.latitude.toFixed(6)}, ${c.longitude.toFixed(6)} (acc=${Math.round(c.accuracy)}m)`);
		GEO.last = pos;
		GEO.error = null;
		updateGeoStatus('ready');
	}

	function handleGeoError(err){
		logGeo(`Watch Error: ${err.code} - ${err.message}`);
		console.warn('watchPosition error', err);
		GEO.error = err;
		updateGeoStatus('error');
	}

	function getLocationForAction(){
		logGeo('getLocationForAction called');
		if (!('geolocation' in navigator)) {
			return Promise.reject(new Error('Geolocation unavailable'));
		}

		if (GEO.last) {
			logGeo('Using cached coordinates');
			updateGeoStatus('ready');
			return Promise.resolve(GEO.last);
		}

		updateGeoStatus('getting');

		if (GEO.watchId !== null) {
			logGeo('Waiting for watchPosition result...');
			return new Promise((resolve, reject) => {
				let waited = 0;
				const checkInterval = setInterval(() => {
					if (GEO.last) {
						clearInterval(checkInterval);
						logGeo('Got coordinates from watchPosition after ' + waited + 'ms');
						updateGeoStatus('ready');
						resolve(GEO.last);
					}
					waited += 200;
					if (waited >= GEO_WAIT_TIMEOUT) {
						clearInterval(checkInterval);
						logGeo('Timeout waiting for watchPosition. Trying direct request...');
						requestDirectPosition(resolve, reject);
					}
				}, 200);
			});
		}

		logGeo('watchPosition not active. Requesting direct position...');
		return new Promise((resolve, reject) => {
			requestDirectPosition(resolve, reject);
		});
	}

	function requestDirectPosition(resolve, reject) {
		navigator.geolocation.getCurrentPosition(pos => {
			logGeo('Direct request success');
			handleGeoSuccess(pos);
			resolve(pos);
		}, err => {
			logGeo(`Direct request error: ${err.code} - ${err.message}`);
			console.warn('getCurrentPosition error', err);
			GEO.error = err;
			updateGeoStatus('error');
			reject(err);
		}, GEO_OPTIONS);
	}

	// ====== UI ======
	function openModal(html){
		document.getElementById('modalBody').innerHTML = html;
		document.getElementById('modalOverlay').style.display = 'flex';
		syncGeoStatus();
	}
	function closeModal(){
		document.getElementById('modalOverlay').style.display = 'none';
		document.getElementById('modalBody').innerHTML = '';
	}

	let busy = false;

	// ====== ACTIONS ======
	function handleEntryClick(block){
		if (block.dataset.closed === '1') {
			return;
		}
		const id_worktime = block.dataset.id_worktime;
		openModal(`
			<h3 style="margin:.25rem 0 1rem 0;font-size:1.35rem;">${texts.finish_work}</h3>
			<textarea id="checkoutComment" placeholder="${texts.enter_comment}"></textarea>
			<div class="footer-actions">
				<button id="btnCheckout" class="btn" onclick="submitCheckout(${id_worktime})">${texts.checkout}</button>
				<button class="btn btn-secondary" onclick="closeModal()">${texts.close}</button>
			</div>
			<div id="geoStatus" class="geo-status" style="margin-top:.75rem;"></div>
		`);
	}

	function submitCheckout(id_worktime){
		if (busy) return;
		busy = true;
		logGeo('Submitting checkout...');
		
		const btn = document.getElementById('btnCheckout');
		if (btn) btn.disabled = true;

		const comment = (document.getElementById('checkoutComment').value || '').trim();
		if (!comment) {
			busy = false;
			if (btn) btn.disabled = false;
			return alert(texts.enter_comment);
		}

		getLocationForAction()
.then(pos => {
logGeo('Got coords for checkout. Sending to server...');
return WebAppAPI.request('/time-entries/check-out', 'POST', {
id: id_worktime,
work_desc: comment,
latitude: pos.coords.latitude,
longitude: pos.coords.longitude
});
})
.then(data => {
				busy = false;
				if (btn) btn.disabled = false;
				if (data && data.success) {
					logGeo('Checkout success');
					closeModal();
					
					// Async update
					const entry = document.querySelector(`.entry[data-id_worktime="${id_worktime}"]`);
					if (entry && data.data) {
						entry.dataset.closed = '1';
						const pname = places.find(p => p.id == data.data.place_id)?.name || texts.object;
						entry.innerHTML = buildEntryHTML(data.data, pname, true);
					}
					
					currentWork = false;
					const addBlock = document.getElementById('addBlock');
					if (addBlock) addBlock.classList.remove('disabled');
				} else {
					logGeo('Checkout failed: ' + (data ? data.error : 'unknown'));
					alert(texts.checkout_error);
				}
			})
			.catch(err => {
				busy = false;
				if (btn) btn.disabled = false;
				logGeo('Checkout network error: ' + err.message);
				alert(texts.checkout_error);
			});
	}

	function startCheckin(){
		if (currentWork) return;
		logGeo('Opening checkin modal...');

		const listHtml = places.map(p => (
			`<label><input type="radio" name="place" value="${p.id}"> ${escapeHtml(p.name)}</label>`
		)).join('');

		openModal(`
			<h3 style="margin:.25rem 0 1rem 0;font-size:1.35rem;">${texts.select_object}</h3>
			<input type="text" id="placeSearch" placeholder="${texts.search}">
			<div class="radio-group" id="placeList">${listHtml}</div>
			<div class="footer-actions">
				<button id="btnCheckin" class="btn" onclick="submitCheckin()">${texts.checkin}</button>
				<button class="btn btn-secondary" onclick="closeModal()">${texts.close}</button>
			</div>
			<div id="geoStatus" class="geo-status" style="margin-top:.75rem;"></div>
		`);

		document.getElementById('placeSearch').addEventListener('input', function(){
			const val = this.value.toLowerCase();
			const labels = document.querySelectorAll('#placeList label');
			labels.forEach(l => {
				l.style.display = l.textContent.toLowerCase().includes(val) ? '' : 'none';
			});
		});
	}

	function submitCheckin(){
		if (busy) return;
		busy = true;
		logGeo('Submitting checkin...');

		const btn = document.getElementById('btnCheckin');
		if (btn) btn.disabled = true;

		const selected = document.querySelector('input[name="place"]:checked');
		if (!selected) {
			busy = false;
			if (btn) btn.disabled = false;
			return alert(texts.select_place);
		}
		const id_place = selected.value;

		getLocationForAction()
			.then(pos => {
				logGeo('Got coords for checkin. Sending to server...');
				return WebAppAPI.request('/time-entries/check-in', 'POST', {
					place_id: id_place,
					latitude: pos.coords.latitude,
					longitude: pos.coords.longitude
				});
			})
			.then(data => {
				busy = false;
				if (btn) btn.disabled = false;
				if (data && data.success) {
					logGeo('Checkin success');
					closeModal();
					
					// Async update
					const placeName = places.find(p => p.id == data.data.place_id)?.name || texts.object;
					const w = data.data;
					if (!w.workday) {
						const now = new Date();
						const pad = n => n.toString().padStart(2, '0');
						w.workday = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
					}
					
					const div = document.createElement('div');
					div.className = 'entry';
					div.dataset.id_worktime = w.id;
					div.dataset.closed = '0';
					div.onclick = function() { handleEntryClick(this); };
					div.innerHTML = buildEntryHTML(w, placeName, false);
					
					document.getElementById('entries').appendChild(div);
					
					currentWork = true;
					const addBlock = document.getElementById('addBlock');
					if (addBlock) addBlock.classList.add('disabled');
				} else {
					logGeo('Checkin failed: ' + (data ? data.error : 'unknown'));
					alert(texts.checkin_error + ': ' + (data && data.error ? data.error : (userLanguage === 'ru' ? 'Неизвестная ошибка' : 'Unknown error')));
				}
			})
			.catch(err => {
				busy = false;
				if (btn) btn.disabled = false;
				logGeo('Checkin network error: ' + err.message);
				alert(texts.checkin_error + ': ' + err.message);
			});
	}

	function escapeHtml(s){
		return String(s).replace(/[&<>\"']/g, m => ({
			'&':'&amp;',
			'<':'&lt;',
			'>':'&gt;',
			'"':'&quot;',
			"'":'&#039;'
		}[m]));
	}

	window.addEventListener('beforeunload', stopGeoTracking);

	if (window.Telegram && Telegram.WebApp && typeof Telegram.WebApp.onEvent === 'function') {
		try {
			Telegram.WebApp.onEvent('web_app_close', stopGeoTracking);
		} catch (e) {}
		try {
			Telegram.WebApp.onEvent('backButtonPressed', stopGeoTracking);
		} catch (e) {}
	}

	startGeoTracking();
	initApp();

</script>
</body>
</html>