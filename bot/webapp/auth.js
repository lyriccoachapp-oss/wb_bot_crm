/**
 * auth.js
 * Библиотека для прозрачной JWT-авторизации Telegram WebApp
 */
const WebAppAPI = (function() {
	const API_BASE = 'https://crm.workbangers.com/api/v1';
	let isLoggingIn = false;
	let loginQueue = []; // Очередь запросов, ожидающих завершения логина

	function getToken() {
		const tgUserId = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
		const storedId = localStorage.getItem('webapp_telegram_id');
		
		if (tgUserId && (!storedId || String(tgUserId) !== String(storedId))) {
			// Пользователь сменился или версия обновилась! Сбрасываем старый токен.
			setToken(null);
			return null;
		}
		
		return localStorage.getItem('webapp_access_token');
	}

	function setToken(token) {
		if (token) {
			localStorage.setItem('webapp_access_token', token);
			const tgUserId = window.Telegram?.WebApp?.initDataUnsafe?.user?.id;
			if (tgUserId) {
				localStorage.setItem('webapp_telegram_id', String(tgUserId));
			}
		} else {
			localStorage.removeItem('webapp_access_token');
			localStorage.removeItem('webapp_telegram_id');
		}
	}

	// Инициализация SDK (Шаг 3 и 4)
	if (window.Telegram && window.Telegram.WebApp) {
		try {
			window.Telegram.WebApp.ready();
			window.Telegram.WebApp.expand();
		} catch (e) {
			console.error('Telegram WebApp init error:', e);
		}
	}

	/**
	 * Автоматический вход через Telegram initData
	 */
	function login(retryCount = 0) {
		if (isLoggingIn && retryCount === 0) {
			return new Promise((resolve, reject) => {
				loginQueue.push({ resolve, reject });
			});
		}

		isLoggingIn = true;
		return new Promise((resolve, reject) => {
			let initData = window.Telegram?.WebApp?.initData;
			const initDataUnsafe = window.Telegram?.WebApp?.initDataUnsafe;
			
			// План Б: Если SDK не распарсил данные, попробуем вытащить их из URL вручную (редкий случай бага SDK)
			if (!initData && window.location.hash) {
				try {
					const hashParams = new URLSearchParams(window.location.hash.substring(1));
					initData = hashParams.get('tgWebAppData');
					if (initData) console.log('initData found manually in hash');
				} catch (e) {
					console.error('Manual hash parsing failed', e);
				}
			}

			// Шаг 2: Попытка повторной инициализации, если данные еще не подтянулись (некоторые устройства медленные)
			if (!initData && retryCount < 5) {
				console.log(`initData missing, retry ${retryCount + 1}/5...`);
				setTimeout(() => {
					login(retryCount + 1).then(resolve).catch(reject);
				}, 500);
				return;
			}

			// Если данных нет совсем — вероятно, открыто не в Telegram (Шаг 6)
			if (!initData) {
				const isWeb = !window.Telegram || !window.Telegram.WebApp || window.Telegram.WebApp.platform === 'unknown';
				let errMsg = 'No Telegram WebApp initData found.';
				
				if (isWeb) {
					errMsg = 'Приложение должно быть открыто внутри Telegram. Если вы видите это в Telegram — попробуйте обновить приложение или очистить кэш (Settings -> Advanced -> Experimental -> Clear WebApp Cache).';
				}

				// Проверка на поддержку localStorage
				let storageAvailable = false;
				try {
					localStorage.setItem('test', '1');
					localStorage.removeItem('test');
					storageAvailable = true;
				} catch(e) {}

				// Шаг 5: Детальное логирование для отладки
				const debugInfo = {
					hasTelegram: !!window.Telegram,
					hasWebApp: !!window.Telegram?.WebApp,
					platform: window.Telegram?.WebApp?.platform,
					version: window.Telegram?.WebApp?.version,
					userAgent: navigator.userAgent,
					hasInitData: !!initData,
					hasUnsafe: !!initDataUnsafe,
					hasHash: !!window.location.hash,
					storage: storageAvailable,
					retries: retryCount
				};
				console.warn('Telegram WebApp environment check:', debugInfo);
				
				const err = new Error(errMsg);
				isLoggingIn = false;
				reject(err);
				processQueue(err, null);
				
				// Показываем визуальное предупреждение (Шаг 1 и 6)
				if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.platform !== 'unknown') {
					window.Telegram.WebApp.showAlert(errMsg + '\n\nDebug: ' + JSON.stringify(debugInfo));
				} else {
					// Если мы в обычном браузере — рисуем кнопку/предупреждение прямо в теле страницы
					const div = document.createElement('div');
					div.id = 'webapp-error-overlay';
					div.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:#fff;z-index:9999;padding:2rem;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:sans-serif;';
					div.innerHTML = `
						<div style="font-size:3rem;margin-bottom:1rem;">🤖</div>
						<h2 style="margin-bottom:1rem;color:#1e3a8a;">Telegram Mini App</h2>
						<p style="margin-bottom:2rem;line-height:1.5;max-width:300px;">${errMsg}</p>
						<div style="font-size:10px; color:#999; margin-bottom:1rem; word-break:break-all;">Debug: ${JSON.stringify(debugInfo)}</div>
						<a href="https://t.me/WorkBangersBot" style="display:inline-block;padding:1rem 2rem;background:#0b66ff;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Открыть в Telegram</a>
						<button onclick="location.reload()" style="margin-top:1rem; background:none; border:none; color:#0b66ff; text-decoration:underline; cursor:pointer;">Обновить страницу</button>
					`;
					if (!document.getElementById('webapp-error-overlay')) {
						document.body.prepend(div);
					}
				}
				return;
			}

			fetch(`${API_BASE}/auth/telegram`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ init_data: initData })
			})
			.then(res => res.json())
			.then(data => {
				isLoggingIn = false;
				if (data.success && data.data && data.data.access_token) {
					setToken(data.data.access_token);
					resolve(data.data.access_token);
					processQueue(null, data.data.access_token);
				} else {
					const errMessage = data.error || data.message || 'Failed to authenticate';
					
					// Если initData просрочен (более 24 часов) или не валиден
					if (window.Telegram && window.Telegram.WebApp) {
						window.Telegram.WebApp.showAlert('Сессия устарела (прошло более 24 часов). Пожалуйста, закройте и откройте приложение заново через меню бота.', function() {
							window.Telegram.WebApp.close();
						});
					}

					const err = new Error(errMessage);
					reject(err);
					processQueue(err, null);
				}
			})
			.catch(err => {
				isLoggingIn = false;
				
				// Обработка сетевой ошибки
				if (window.Telegram && window.Telegram.WebApp && !err.message.includes('fetch')) {
					window.Telegram.WebApp.showAlert('Ошибка авторизации. Попробуйте перезапустить приложение.', function() {
						window.Telegram.WebApp.close();
					});
				}
				
				reject(err);
				processQueue(err, null);
			});
		});
	}

	function processQueue(err, token) {
		const queue = [...loginQueue];
		loginQueue = [];
		queue.forEach(item => {
			if (err) item.reject(err);
			else item.resolve(token);
		});
	}

	/**
	 * Основной метод запроса
	 */
	async function request(endpoint, method = 'GET', body = null) {
		let token = getToken();

		// Если токена нет, получаем его сразу
		if (!token) {
			token = await login();
		}

		let options = {
			method: method,
			headers: {
				'Authorization': `Bearer ${token}`,
				'Accept': 'application/json',
			}
		};

		if (body) {
			if (body instanceof FormData) {
				options.body = body;
				// fetch automatically sets Content-Type to multipart/form-data with boundary
			} else {
				options.headers['Content-Type'] = 'application/json';
				options.body = JSON.stringify(body);
			}
		}

		let res = await fetch(`${API_BASE}${endpoint}`, options);

		// Если токен протух (401), пробуем обновить через login()
		if (res.status === 401) {
			token = await login();
			options.headers['Authorization'] = `Bearer ${token}`;
			res = await fetch(`${API_BASE}${endpoint}`, options);
		}

		if (!res.ok) {
			const errBody = await res.json().catch(() => ({}));
			throw new Error(errBody.error || errBody.message || `API Error ${res.status}`);
		}

		const data = await res.json();
		return data;
	}

	return {
		login,
		request
	};
})();

// Ограничение: один открытый WebApp
(function() {
	try {
		// Ключ сессии инстанса
		const myId = Date.now().toString() + Math.random().toString();
		localStorage.setItem('webapp_active_instance', myId);

		function checkAndClose() {
			const activeId = localStorage.getItem('webapp_active_instance');
			if (activeId && activeId !== myId) {
				if (window.Telegram && window.Telegram.WebApp) {
					window.Telegram.WebApp.close();
				}
			}
		}

		// 1. BroadcastChannel (мгновенно для активных вкладок)
		const channel = new BroadcastChannel('webapp_instance_channel');
		channel.postMessage({ type: 'new_instance', id: myId });
		channel.onmessage = function(event) {
			if (event.data && event.data.type === 'new_instance' && event.data.id !== myId) {
				checkAndClose();
			}
		};

		// 2. Storage event (для кросс-вкладок)
		window.addEventListener('storage', function(e) {
			if (e.key === 'webapp_active_instance') {
				checkAndClose();
			}
		});

		// 3. Visibility API (срабатывает, когда замороженное окно снова становится активным)
		document.addEventListener('visibilitychange', function() {
			if (document.visibilityState === 'visible') {
				checkAndClose();
			}
		});
		window.addEventListener('focus', checkAndClose);

		// 4. Поллинг (на случай, если события не сработают сразу после разморозки WebView)
		setInterval(checkAndClose, 500);

	} catch (e) {
		console.warn('Instance locking not supported', e);
	}
})();
