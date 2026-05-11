<?php
if (empty($_SESSION['api_token'])) {
	header('Location: /?route=login');
	exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Геолокация — WorkBangers CRM</title>
	<link rel="stylesheet" href="/public/css/style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/dashboard.css?v=<?= time() ?>">
	<link rel="stylesheet" href="/public/css/components.css?v=<?= time() ?>">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
	<script>
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
    <style>
        .geo-layout {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: calc(100vh - 160px);
        }
        .geo-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tabs {
            display: flex;
            gap: 10px;
        }
        .tab-btn {
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .geo-content {
            display: flex;
            gap: 20px;
            flex: 1;
            overflow: hidden;
        }
        .map-container {
            flex: 3;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
        }
        .map-element {
            width: 100%;
            height: 100%;
            z-index: 1; /* fix leaflet overlap */
        }
        .users-list-container {
            flex: 1;
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            overflow-y: auto;
            padding: 15px;
        }
        .user-card {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .user-card:hover {
            background: var(--bg-body);
        }
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        .worktime-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-left: 20px;
        }
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-size: 1.2rem;
            text-align: center;
            padding: 20px;
        }
        html[data-theme="dark"] .leaflet-layer,
        html[data-theme="dark"] .leaflet-control-zoom-in,
        html[data-theme="dark"] .leaflet-control-zoom-out,
        html[data-theme="dark"] .leaflet-control-attribution {
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        
        /* Interactive styles */
        .user-card.dimmed {
            opacity: 0.5;
            filter: grayscale(100%);
        }
        .user-card.active {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }
        .worktime-info {
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: background 0.2s;
        }
        .worktime-info:hover {
            background: rgba(0,0,0,0.05);
        }
        .worktime-info.active {
            font-weight: bold;
            color: var(--primary-color);
            background: var(--bg-hover);
        }

        /* Mobile layout fixes */
        @media (max-width: 768px) {
            .geo-layout {
                height: auto;
            }
            .geo-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            .tabs {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            .date-picker {
                display: flex;
                justify-content: flex-end;
            }
            .geo-content {
                flex-direction: column;
                overflow: visible;
            }
            .map-container {
                height: 400px;
                flex: none;
            }
            .users-list-container {
                height: 350px;
                flex: none;
            }
        }
    </style>
</head>
<body>
	<div class="app-layout">
		<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebarMobile()"></div>
		
		<?php require __DIR__ . '/components/sidebar.php'; ?>

		<main class="main-content">
			<?php $pageTitle = 'Карта сотрудников'; require __DIR__ . '/components/topbar.php'; ?>

			<div class="content-wrapper">
				<div class="geo-layout">
                    <div class="geo-controls">
                        <div class="tabs" id="companyTabs">
                            <!-- Tabs dynamically injected -->
                        </div>
                        <div class="date-picker">
                            <input type="date" id="geoDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="geo-content">
                        <div class="map-container" id="mapContainerWrapper">
                            <div id="map" class="map-element"></div>
                            <div id="mapEmptyState" class="empty-state" style="display:none;">
                                В этот день не обнаружено геоданных
                            </div>
                        </div>
                        <div class="users-list-container" id="usersList">
                            <!-- Users injected here -->
                        </div>
                    </div>
                </div>
			</div>
		</main>
	</div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
	<script>
		const API_TOKEN = '<?= $_SESSION['api_token'] ?? '' ?>';
        
        let map = null;
        let mapData = {}; // Data grouped by company
        let activeCompany = null;
        let markersStore = {}; // worktimeId => array of leaflet circle markers
        
        // Colors for different users (shades of primary and secondary)
        const userColors = [
            '#FF3B30', '#FF9500', '#FFCC00', '#4CD964', '#5AC8FA', 
            '#007AFF', '#5856D6', '#FF2D55', '#AF52DE', '#FF1493',
            '#32CD32', '#00CED1', '#4169E1', '#8A2BE2', '#D2691E'
        ];

        function initMap() {
            map = L.map('map').setView([51.505, -0.09], 13);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
        }

        async function loadData() {
            const date = document.getElementById('geoDate').value;
            try {
                const res = await fetch(`/api/v1/locations?date=${date}`, {
                    headers: { 'Authorization': `Bearer ${API_TOKEN}` }
                });
                const json = await res.json();
                
                if (json.success) {
                    mapData = json.data;
                    renderTabs();
                } else {
                    alert('Ошибка: ' + (json.message || 'Сбой API'));
                }
            } catch(e) {
                console.error(e);
                alert('Ошибка соединения');
            }
        }

        function renderTabs() {
            const tabsContainer = document.getElementById('companyTabs');
            tabsContainer.innerHTML = '';
            const companies = Object.keys(mapData);
            
            if (companies.length === 0) {
                activeCompany = null;
                renderMap();
                return;
            }

            if (!activeCompany || !companies.includes(activeCompany)) {
                activeCompany = companies[0];
            }

            companies.forEach(company => {
                const btn = document.createElement('button');
                btn.className = `tab-btn ${company === activeCompany ? 'active' : ''}`;
                btn.textContent = company;
                btn.onclick = () => {
                    activeCompany = company;
                    renderTabs(); // to update active class
                    renderMap();
                };
                tabsContainer.appendChild(btn);
            });

            renderMap();
        }

        let activeFilterUser = null;
        let activeFilterWorktime = null;

        function updateMapVisibility() {
            // Сбрасываем стили списков
            document.querySelectorAll('.user-card').forEach(el => {
                el.classList.remove('active', 'dimmed');
                if (activeFilterUser || activeFilterWorktime) {
                    if (activeFilterUser === el.dataset.userId) {
                        el.classList.add('active');
                    } else {
                        el.classList.add('dimmed');
                    }
                }
            });

            document.querySelectorAll('.worktime-info').forEach(el => {
                el.classList.remove('active');
                if (activeFilterWorktime === el.dataset.worktimeId) {
                    el.classList.add('active');
                }
            });

            // Обновляем видимость маркеров
            for (const [wtId, layers] of Object.entries(markersStore)) {
                let shouldShow = true;
                
                if (activeFilterWorktime) {
                    shouldShow = (wtId == activeFilterWorktime);
                } else if (activeFilterUser) {
                    // Найдем какому юзеру принадлежит эта смена (wtId)
                    // Для оптимизации мы можем сохранить маппинг wtId -> userId
                    shouldShow = (worktimeUserMap[wtId] == activeFilterUser);
                }

                layers.forEach(m => {
                    if (shouldShow) {
                        if (!map.hasLayer(m)) m.addTo(map);
                    } else {
                        if (map.hasLayer(m)) map.removeLayer(m);
                    }
                });
            }
            
            // Если включен фильтр, отзумим карту на отфильтрованные точки
            let bounds = new L.LatLngBounds();
            let hasVisible = false;
            for (const [wtId, layers] of Object.entries(markersStore)) {
                let shouldShow = true;
                if (activeFilterWorktime) shouldShow = (wtId == activeFilterWorktime);
                else if (activeFilterUser) shouldShow = (worktimeUserMap[wtId] == activeFilterUser);
                
                if (shouldShow) {
                    layers.forEach(m => {
                        if (m instanceof L.CircleMarker) {
                            bounds.extend(m.getLatLng());
                            hasVisible = true;
                        }
                    });
                }
            }
            if (hasVisible) map.fitBounds(bounds, {padding: [50, 50]});
        }

        let worktimeUserMap = {}; // wtId -> userId

        function renderMap() {
            const mapEl = document.getElementById('map');
            const emptyEl = document.getElementById('mapEmptyState');
            const usersList = document.getElementById('usersList');
            
            // Сброс фильтров
            activeFilterUser = null;
            activeFilterWorktime = null;
            worktimeUserMap = {};

            // Clear existing markers
            if (map) {
                map.eachLayer((layer) => {
                    if (layer instanceof L.CircleMarker || layer instanceof L.Polyline) {
                        layer.remove();
                    }
                });
            }
            markersStore = {};
            usersList.innerHTML = '';

            if (!activeCompany || !mapData[activeCompany] || mapData[activeCompany].length === 0) {
                mapEl.style.display = 'none';
                emptyEl.style.display = 'flex';
                return;
            }

            mapEl.style.display = 'block';
            emptyEl.style.display = 'none';

            if (!map) initMap();

            const users = mapData[activeCompany];
            let bounds = new L.LatLngBounds();
            let hasPoints = false;
            let colorIndex = 0;

            users.forEach(u => {
                const color = userColors[colorIndex % userColors.length];
                colorIndex++;

                // Build User Card
                const card = document.createElement('div');
                card.className = 'user-card';
                card.dataset.userId = u.user.id;
                
                // Обработка клика по пользователю
                card.onclick = (e) => {
                    // Если кликнули по смене, игнорируем (всплытие)
                    if (e.target.closest('.worktime-info')) return;
                    
                    if (activeFilterUser == u.user.id && !activeFilterWorktime) {
                        // Сброс
                        activeFilterUser = null;
                    } else {
                        activeFilterUser = u.user.id;
                        activeFilterWorktime = null; // Сброс смены при выборе всего юзера
                    }
                    updateMapVisibility();
                };

                card.innerHTML = `
                    <div class="user-name">
                        <span class="user-color-dot" style="background:${color}"></span>
                        ${u.user.name}
                    </div>
                `;

                u.worktimes.forEach(w => {
                    const wtId = w.id_worktime;
                    markersStore[wtId] = [];
                    worktimeUserMap[wtId] = u.user.id;
                    
                    const timeInfo = document.createElement('div');
                    timeInfo.className = 'worktime-info';
                    timeInfo.dataset.worktimeId = wtId;
                    timeInfo.textContent = `Смена: ${w.checkin ? w.checkin.split(' ')[1] : '?'} - ${w.checkout ? w.checkout.split(' ')[1] : 'в работе'}`;
                    
                    // Обработка клика по смене
                    timeInfo.onclick = (e) => {
                        if (activeFilterWorktime == wtId) {
                            // Сброс конкретной смены (но оставляем юзера активным)
                            activeFilterWorktime = null;
                            activeFilterUser = u.user.id;
                        } else {
                            activeFilterWorktime = wtId;
                            activeFilterUser = u.user.id;
                        }
                        updateMapVisibility();
                    };

                    card.appendChild(timeInfo);

                    let latlngs = [];
                    
                    w.points.forEach(pt => {
                        const latlng = [pt.lat, pt.lng];
                        latlngs.push(latlng);
                        bounds.extend(latlng);
                        hasPoints = true;

                        const marker = L.circleMarker(latlng, {
                            radius: 5,
                            color: color,
                            fillColor: color,
                            fillOpacity: 0.7,
                            weight: 2
                        }).addTo(map);
                        
                        marker.bindPopup(`${u.user.name}<br>${pt.time}`);
                        markersStore[wtId].push(marker);
                    });

                    // Draw line between points
                    if (latlngs.length > 1) {
                        const polyline = L.polyline(latlngs, {color: color, weight: 2, opacity: 0.5}).addTo(map);
                        markersStore[wtId].push(polyline);
                    }
                    
                    // Hover logic (Только десктоп)
                    card.onmouseenter = () => {
                        // Не увеличивать, если активен жесткий фильтр по другому юзеру
                        if (activeFilterUser && activeFilterUser != u.user.id) return;
                        
                        markersStore[wtId].forEach(m => {
                            if (m instanceof L.CircleMarker) m.setRadius(9);
                            if (m instanceof L.Polyline) m.setStyle({weight: 5, opacity: 0.9});
                        });
                    };
                    card.onmouseleave = () => {
                        markersStore[wtId].forEach(m => {
                            if (m instanceof L.CircleMarker) m.setRadius(5);
                            if (m instanceof L.Polyline) m.setStyle({weight: 2, opacity: 0.5});
                        });
                    };
                });

                usersList.appendChild(card);
            });

            if (hasPoints) {
                map.fitBounds(bounds, {padding: [50, 50]});
            } else {
                mapEl.style.display = 'none';
                emptyEl.style.display = 'flex';
            }
        }

        document.getElementById('geoDate').addEventListener('change', loadData);

        // Initial load
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadData();
        });

		// Общие функции
		function toggleSidebarMobile() {
			document.getElementById('sidebar').classList.toggle('open');
			document.getElementById('sidebarOverlay').classList.toggle('active');
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
	</script>
</body>
</html>
