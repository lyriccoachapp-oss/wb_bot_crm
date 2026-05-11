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

	/**
	 * Автоматический вход через Telegram initData
	 */
	function login() {
		if (isLoggingIn) {
			return new Promise((resolve, reject) => {
				loginQueue.push({ resolve, reject });
			});
		}

		isLoggingIn = true;
		return new Promise((resolve, reject) => {
			const initData = window.Telegram?.WebApp?.initData;
			if (!initData) {
				const err = new Error('No Telegram WebApp initData found.');
				isLoggingIn = false;
				reject(err);
				processQueue(err, null);
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
					const err = new Error(data.error || 'Failed to authenticate');
					reject(err);
					processQueue(err, null);
				}
			})
			.catch(err => {
				isLoggingIn = false;
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
