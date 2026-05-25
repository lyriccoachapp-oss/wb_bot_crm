<?php
date_default_timezone_set('America/Halifax');

$APP_VERSION = '1.0.30';

// Подключаем i18n
require_once('../lib/i18n.php');
$userLanguage = isset($_GET['lang']) ? $_GET['lang'] : 'en';
I18n::load($userLanguage);

function translate($key) {
	return __('webapp.objects.'.$key) ?: __('webapp.wt.'.$key);
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($userLanguage) ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<title><?= translate('object') ?></title>
	<style>
		:root {
			--primary: #0b66ff;
			--bg: #f3f4f6;
			--text: #1f2937;
			--text-light: #6b7280;
			--radius: 12px;
		}

		* { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		body { background-color: var(--bg); color: var(--text); padding: 1rem; padding-bottom: 5rem; }

		.header { text-align: center; margin-bottom: 1.5rem; }
		.header h2 { font-size: 1.5rem; color: #111827; }

		.card { background: #fff; border-radius: var(--radius); padding: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1rem; }
		.card h3 { font-size: 1.2rem; margin-bottom: 1rem; text-align: center; }

		.form-group { margin-bottom: 1rem; position: relative; }
		.form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #4b5563; }
		.form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: #fff; }
		.form-group input[readonly] { background: #f3f4f6; }

		#objAutocompleteResults {
			display: none; position: absolute; top: 100%; left: 0; right: 0;
			background: #fff; border: 1px solid #d1d5db; border-radius: 0 0 8px 8px;
			max-height: 200px; overflow-y: auto; z-index: 1001; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
		}
		#objAutocompleteResults div { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; }
		#objAutocompleteResults div:last-child { border-bottom: none; }

		.btn { width: 100%; padding: 0.75rem; border: none; border-radius: 8px; background: var(--primary); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; text-align: center; display: block; }
		.btn:disabled { opacity: 0.7; cursor: not-allowed; }

		.objects-list { margin-top: 2rem; }
		.object-item { background: #fff; border-radius: 8px; padding: 1rem; margin-bottom: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
		.object-item h4 { margin-bottom: 0.25rem; color: #111827; }
		.object-item p { font-size: 0.875rem; color: #6b7280; }
		.object-badge { display: inline-block; padding: 0.25rem 0.5rem; background: #e0e7ff; color: #4338ca; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-top: 0.5rem; }
		.app-version{ position:fixed; top:2px; right:3px; font-size:6px; line-height:1; color:#000; opacity:.45; z-index:1000; pointer-events:none;}
	</style>
	<script src="https://telegram.org/js/telegram-web-app.js"></script>
	<script src="auth.js?v=<?= $APP_VERSION ?>"></script>
</head>
<body>
	<div class="app-version">v<?= htmlspecialchars($APP_VERSION) ?></div>
	<div class="header">
		<h2><?= translate('title') ?></h2>
	</div>

	<div class="card">
		<h3><?= translate('add_new') ?></h3>
		
		<div class="form-group">
			<label><?= ($userLanguage === 'ru' ? 'Поиск адреса' : ($userLanguage === 'uk' ? 'Пошук адреси' : 'Search address')) ?></label>
			<input type="text" id="objSearchAddress" placeholder="<?= ($userLanguage === 'ru' ? 'Начните вводить адрес...' : 'Start typing address...') ?>" autocomplete="off">
			<div id="objAutocompleteResults"></div>
		</div>

		<div class="form-group">
			<label><?= ($userLanguage === 'ru' ? 'Название объекта' : ($userLanguage === 'uk' ? 'Назва об\'єкта' : 'Object Name')) ?></label>
			<input type="text" id="objAddName">
		</div>

		<div class="form-group">
			<label><?= ($userLanguage === 'ru' ? 'Адрес (Полный)' : ($userLanguage === 'uk' ? 'Адреса (Повна)' : 'Address (Full)')) ?></label>
			<input type="text" id="objAddAddress" readonly>
		</div>

		<div class="form-group">
			<label><?= ($userLanguage === 'ru' ? 'Тип работ' : ($userLanguage === 'uk' ? 'Тип робіт' : 'Work Type')) ?></label>
			<select id="objAddWorksType">
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

		<button onclick="saveObject()" id="btnSaveObject" class="btn"><?= translate('add_new') ?></button>
	</div>

	<div class="objects-list">
		<h3 style="margin-bottom: 1rem; color: #111827; font-size: 1.2rem;"><?= ($userLanguage === 'ru' ? 'Существующие объекты' : ($userLanguage === 'uk' ? 'Існуючі об\'єкти' : 'Existing Objects')) ?></h3>
		<div id="objectsContainer">
			<div style="text-align: center; color: #6b7280; padding: 2rem;"><?= ($userLanguage === 'ru' ? 'Загрузка...' : 'Loading...') ?></div>
		</div>
	</div>

	<!-- Модалка редактирования -->
	<div class="modal-overlay" id="modalEditObject" style="display:none;position:fixed;top:0;left:0;right:0;bottom:5rem;align-items:center;justify-content:center;background:rgba(255,255,255,.9);z-index:999;padding:1rem;box-sizing:border-box">
		<div class="modal" style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.15);width:100%;max-width:400px;max-height:100%;overflow-y:auto;">
			<h3 style="margin-top:0;margin-bottom:1.5rem;font-size:1.2rem;text-align:center;"><?= translate('edit_title') ?: 'Edit Object' ?></h3>
			
			<input type="hidden" id="objEditId">
			
			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Название' : 'Name') ?></label>
				<input type="text" id="objEditName">
			</div>

			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Адрес' : 'Address') ?></label>
				<input type="text" id="objEditAddress">
			</div>

			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Тип работ' : 'Work Type') ?></label>
				<select id="objEditWorksType">
					<option value="Roof">Roof</option>
					<option value="Siding">Siding</option>
					<option value="External works">External works</option>
					<option value="Internal works">Internal works</option>
					<option value="Electric">Electric</option>
					<option value="Plumbing">Plumbing</option>
					<option value="Other works">Other works</option>
				</select>
			</div>

			<div class="form-group">
				<label><?= ($userLanguage === 'ru' ? 'Статус' : 'Status') ?></label>
				<select id="objEditActive">
					<option value="1"><?= ($userLanguage === 'ru' ? 'Активен' : 'Active') ?></option>
					<option value="0"><?= ($userLanguage === 'ru' ? 'Архив' : 'Archive') ?></option>
				</select>
			</div>

			<button onclick="submitEditObject()" id="btnSubmitEdit" class="btn" style="margin-bottom:0.5rem;"><?= translate('btn_save') ?></button>
			<button onclick="closeEditModal()" class="btn" style="background:#6b7280;"><?= translate('close') ?: 'Cancel' ?></button>
		</div>
	</div>

	<?php include 'nav.php'; ?>

	<script>
		const userLanguage = <?= json_encode($userLanguage) ?>;
		const API_URL = '/api/v1/';
		let telegramId = 0;
		let profileData = null;
		let canEdit = false;

		async function initApp() {
			try {
				const [profileReq, placesData] = await Promise.all([
					WebAppAPI.request('/auth/me'),
					WebAppAPI.request('/objects?limit=100') // fetch all data
				]);
				profileData = profileReq.data || profileReq;
				telegramId = profileData.telegram_id || 0;

				canEdit = profileData.role === 'admin' || (profileData.permissions && profileData.permissions.includes('objects.edit'));

				const navObj = document.getElementById('navAddObject');
				if (navObj && (profileData.role === 'admin' || (profileData.permissions && profileData.permissions.includes('objects.manage')))) {
					navObj.style.display = 'flex';
				} else {
					document.body.innerHTML = '<div style="text-align:center; padding: 2rem; color: red;">' + (userLanguage === 'ru' ? 'У вас нет прав' : 'Access denied') + '</div>';
					return;
				}

				const canViewList = profileData.role === 'admin' || (profileData.permissions && profileData.permissions.includes('objects.edit'));
				
				if (!canViewList) {
					const listBlock = document.querySelector('.objects-list');
					if (listBlock) listBlock.style.display = 'none';
				} else {
					renderObjects(placesData?.data?.items || placesData?.items || placesData?.data || placesData || []);
				}
			} catch (err) {
				console.error('Error init:', err);
				window.Telegram.WebApp.showAlert((userLanguage === 'ru' ? 'Ошибка загрузки данных: ' : (userLanguage === 'uk' ? 'Помилка завантаження даних: ' : 'Data load error: ')) + err.message);
			}
		}

		function renderObjects(places) {
			const container = document.getElementById('objectsContainer');
			if (!places || places.length === 0) {
				container.innerHTML = '<div style="text-align: center; color: #6b7280; padding: 2rem;">' + (userLanguage === 'ru' ? 'Нет активных объектов' : 'No active objects') + '</div>';
				return;
			}
			container.innerHTML = '';
			places.forEach(p => {
				const div = document.createElement('div');
				div.className = 'object-item';
				div.style.position = 'relative';

				let editHtml = '';
				if (canEdit) {
					const escName = escapeHtml(p.name).replace(/'/g, "\\'");
					const escAddress = escapeHtml(p.address).replace(/'/g, "\\'");
					const escType = escapeHtml(p.works_type).replace(/'/g, "\\'");
					editHtml = `<button onclick="openEditModal(${p.id}, '${escName}', '${escAddress}', '${escType}', ${p.active})" style="position:absolute; right:1rem; top:1rem; background:none; border:none; color:var(--primary); cursor:pointer;">
						<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
					</button>`;
				}

				div.innerHTML = `
					<h4>${escapeHtml(p.name)}</h4>
					<p>${escapeHtml(p.address)}</p>
					${p.works_type ? `<span class="object-badge">${escapeHtml(p.works_type)}</span>` : ''}
					${!p.active ? `<span class="object-badge" style="background:#fee2e2;color:#b91c1c;">${(userLanguage === 'ru' ? 'Архив' : (userLanguage === 'uk' ? 'Архів' : 'Archive'))}</span>` : ''}
					${editHtml}
				`;
				container.appendChild(div);
			});
		}

		function escapeHtml(s) {
			if (!s) return '';
			return String(s).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
		}

		function formatAddress(item) {
			if (!item.address) return item.display_name;
			const addr = item.address;
			const house = addr.house_number || '';
			const road = addr.road || addr.pedestrian || addr.street || '';
			const city = addr.city || addr.town || addr.village || addr.municipality || '';
			const state = addr.state || addr.province || '';
			const postcode = addr.postcode || '';
			
			// Сокращения провинций Канады как в Google Maps
			const provinces = {
				'Alberta': 'AB', 'British Columbia': 'BC', 'Manitoba': 'MB', 'New Brunswick': 'NB',
				'Newfoundland and Labrador': 'NL', 'Nova Scotia': 'NS', 'Northwest Territories': 'NT',
				'Nunavut': 'NU', 'Ontario': 'ON', 'Prince Edward Island': 'PE', 'Quebec': 'QC',
				'Saskatchewan': 'SK', 'Yukon': 'YT'
			};
			const stateAbbr = provinces[state] || state;
			
			let parts = [];
			
			let streetPart = '';
			if (house && road) streetPart = `${house} ${road}`;
			else if (road) streetPart = road;
			else if (house) streetPart = house;
			if (streetPart) parts.push(streetPart);
			
			if (city && city !== streetPart) parts.push(city);
			
			let stateZip = [];
			if (stateAbbr) stateZip.push(stateAbbr);
			if (postcode) stateZip.push(postcode);
			if (stateZip.length > 0) parts.push(stateZip.join(' '));
			
			const formatted = parts.join(', ');
			return formatted || item.display_name;
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
							const cleanAddr = formatAddress(item);
							div.textContent = cleanAddr;
							
							div.onclick = () => {
								document.getElementById('objSearchAddress').value = cleanAddr;
								document.getElementById('objAddAddress').value = cleanAddr;
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
								if (!objName) objName = cleanAddr.split(',')[0];
								
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
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Введите название объекта' : (userLanguage === 'uk' ? 'Введіть назву об\'єкта' : 'Enter object name'));
				return;
			}
			if (!address) {
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Выберите полный адрес из подсказок' : (userLanguage === 'uk' ? 'Оберіть повну адресу з підказок' : 'Select full address from suggestions'));
				return;
			}
			if (!worksType) {
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Выберите тип работ' : (userLanguage === 'uk' ? 'Оберіть тип робіт' : 'Select work type'));
				return;
			}

			const btn = document.getElementById('btnSaveObject');
			btn.disabled = true;
			btn.textContent = userLanguage === 'ru' ? 'Сохранение...' : (userLanguage === 'uk' ? 'Збереження...' : 'Saving...');

			try {
				const res = await WebAppAPI.request('/objects', 'POST', {
					place_name: name,
					place_address: address,
					works_type: worksType
				});

				if (res.success || res.id) {
					window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Объект успешно добавлен!' : (userLanguage === 'uk' ? 'Об\'єкт успішно доданий!' : 'Object added successfully!'));
					document.getElementById('objSearchAddress').value = '';
					document.getElementById('objAddName').value = '';
					document.getElementById('objAddAddress').value = '';
					document.getElementById('objAddWorksType').value = '';
					initApp(); // Перезагружаем список
				} else {
					window.Telegram.WebApp.showAlert(res.error || (userLanguage === 'ru' ? 'Ошибка при сохранении' : 'Save error'));
				}
			} catch (error) {
				console.error('Error adding object:', error);
				window.Telegram.WebApp.showAlert(userLanguage === 'ru' ? 'Сетевая ошибка при добавлении объекта' : 'Network error adding object');
			} finally {
				btn.disabled = false;
				btn.textContent = userLanguage === 'ru' ? 'Добавить объект' : (userLanguage === 'uk' ? 'Додати об\'єкт' : 'Add Object');
			}
		}

		function openEditModal(id, name, address, type, active) {
			document.getElementById('objEditId').value = id;
			document.getElementById('objEditName').value = name;
			document.getElementById('objEditAddress').value = address;
			document.getElementById('objEditWorksType').value = type;
			document.getElementById('objEditActive').value = active ? '1' : '0';
			document.getElementById('modalEditObject').style.display = 'flex';
		}

		function closeEditModal() {
			document.getElementById('modalEditObject').style.display = 'none';
		}

		async function submitEditObject() {
			const id = document.getElementById('objEditId').value;
			const name = document.getElementById('objEditName').value.trim();
			const address = document.getElementById('objEditAddress').value.trim();
			const worksType = document.getElementById('objEditWorksType').value;
			const active = document.getElementById('objEditActive').value === '1';

			if (!name) {
				window.Telegram.WebApp.showAlert('Введите название объекта');
				return;
			}

			const btn = document.getElementById('btnSubmitEdit');
			btn.disabled = true;
			btn.textContent = 'Сохранение...';

			try {
				const res = await WebAppAPI.request('/objects/' + id, 'PUT', {
					place_name: name,
					place_address: address,
					works_type: worksType,
					active: active
				});

				if (res.success || res.id) {
					window.Telegram.WebApp.showAlert('Объект успешно обновлен!');
					closeEditModal();
					document.getElementById('objectsContainer').innerHTML = '<div style="text-align: center; color: #6b7280; padding: 2rem;">Загрузка...</div>';
					initApp(); // Перезагружаем список
				} else {
					window.Telegram.WebApp.showAlert(res.error || 'Ошибка при сохранении');
				}
			} catch (error) {
				console.error('Error editing object:', error);
				window.Telegram.WebApp.showAlert('Сетевая ошибка при обновлении');
			} finally {
				btn.disabled = false;
				btn.textContent = 'Сохранить';
			}
		}

		initApp();
	</script>
</body>
</html>
