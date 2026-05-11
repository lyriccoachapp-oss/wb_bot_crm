<?php
$emojiAvatar = trim($_SESSION['user_info']['reset_password_validity'] ?? '');
$userInitials = $emojiAvatar !== '' ? $emojiAvatar : strtoupper(substr($_SESSION['user_info']['email'] ?? 'U', 0, 1));
$userName = !empty($_SESSION['user_info']['name']) ? $_SESSION['user_info']['name'] : 'User';
$rawRole = $_SESSION['user_info']['role'] ?? 'user';
$userRoleDisplay = ($rawRole === 'admin') ? 'Администратор' : 'Пользователь';
$pageTitleText = $pageTitle ?? 'Панель управления';
?>
<header class="topbar">
    <div class="topbar-left">
        <!-- Кнопка скрытия/раскрытия меню для десктопа -->
        <button class="theme-btn" id="collapseBtn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar" style="margin-right: 10px;">
            <svg viewBox="0 0 24 24"><path d="M21 12H3"/><path d="M21 6H3"/><path d="M21 18H3"/><path d="M8 8l-4 4 4 4"/></svg>
        </button>
        
        <!-- Бургер для мобилки (по умолчанию скрыт CSS) -->
        <button class="burger-btn" onclick="toggleSidebarMobile()">
            <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="topbar-title">
            <h1 id="topbarCurrentDate" class="topbar-date">Загрузка...</h1>
            <p><?= htmlspecialchars($pageTitleText) ?></p>
        </div>
    </div>
    <div class="topbar-right" style="display: flex; align-items: center; gap: 15px;">
        <!-- Переключатель темы -->
        <button class="theme-btn" onclick="toggleTheme()" title="Switch Theme" style="background: none; border: none; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0;">
            <svg id="themeIcon" viewBox="0 0 24 24" style="width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <!-- Уведомления -->
        <button class="notify-btn" title="Notifications" style="background: none; border: none; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; padding: 0;">
            <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span style="position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background-color: #ff4d4f; border-radius: 50%; border: 2px solid var(--bg-card);"></span>
        </button>

        <!-- Настройки -->
        <a href="/?route=settings" class="settings-btn" title="Settings" style="color: var(--text-muted); display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" style="width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        </a>

        <!-- Вертикальный разделитель -->
        <div style="width: 1px; height: 24px; background: var(--border-color); margin: 0 5px;"></div>

        <!-- Профиль с дропдауном -->
        <div class="topbar-profile" style="position: relative;">
            <button class="avatar-btn" onclick="toggleProfileMenu()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 10px; outline: none; transition: transform 0.2s; padding: 0; text-align: left; font-family: inherit;">
                <span class="avatar-icon" style="font-size: 1.75rem; line-height: 1;"><?= $userInitials ?></span>
                <span class="avatar-info" style="display: flex; flex-direction: column;">
                    <span class="avatar-name" style="color: var(--text-main); font-weight: 600; font-size: 0.95rem; white-space: nowrap;"><?= htmlspecialchars($userName) ?></span>
                    <span class="avatar-role" style="color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;"><?= htmlspecialchars($userRoleDisplay) ?></span>
                </span>
            </button>
            <div id="profileDropdown" class="profile-dropdown" style="display: none; position: absolute; top: 50px; right: 0; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 180px; z-index: 1000; overflow: hidden;">
                <a href="/?route=settings" style="display: flex; align-items: center; padding: 12px 16px; color: var(--text-main); text-decoration: none; transition: background 0.2s;">
                    <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 10px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Настройки
                </a>
                <div style="margin: 0 16px; border-bottom: 1px dashed var(--border-color);"></div>
                <a href="/?route=logout" style="display: flex; align-items: center; padding: 12px 16px; color: var(--danger-color); text-decoration: none; transition: background 0.2s;">
                    <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 10px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Выйти
                </a>
            </div>
        </div>
    </div>
</header>

<style>
    .profile-dropdown a:hover {
        background: rgba(0,0,0,0.05);
    }
    html[data-theme="dark"] .profile-dropdown a:hover {
        background: rgba(255,255,255,0.05);
    }
    .avatar-btn:active {
        transform: scale(0.95);
    }
    .theme-btn:hover, .notify-btn:hover, .settings-btn:hover {
        color: var(--primary-color) !important;
    }
    .topbar-date {
        font-size: 1.1rem !important;
        margin: 0 !important;
        line-height: 1.2 !important;
    }
    @media (max-width: 768px) {
        .topbar-date { font-size: 0.9rem !important; line-height: 1.3 !important; }
        .avatar-info { display: none !important; }
        .topbar-right { gap: 10px !important; }
    }
</style>

<script>
    // Инициализация часов
    function updateTopbarTime() {
        const el = document.getElementById('topbarCurrentDate');
        if (!el) return;
        const now = new Date();
        
        if (window.innerWidth <= 768) {
            const dateOpts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
            const timeOpts = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            let dateStr = now.toLocaleDateString('ru-RU', dateOpts);
            dateStr = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
            let timeStr = now.toLocaleTimeString('ru-RU', timeOpts);
            el.innerHTML = dateStr + '<br>' + timeStr;
        } else {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            let str = now.toLocaleDateString('ru-RU', options);
            str = str.charAt(0).toUpperCase() + str.slice(1);
            el.innerHTML = str;
        }
    }
    setInterval(updateTopbarTime, 1000);
    updateTopbarTime();

    // Логика дропдауна
    function toggleProfileMenu() {
        const dd = document.getElementById('profileDropdown');
        dd.style.display = dd.style.display === 'none' || dd.style.display === '' ? 'block' : 'none';
    }

    // Закрытие при клике вне
    document.addEventListener('click', function(event) {
        const profileDiv = document.querySelector('.topbar-profile');
        const dd = document.getElementById('profileDropdown');
        if (profileDiv && dd && !profileDiv.contains(event.target)) {
            dd.style.display = 'none';
        }
    });

    // Глобальный перехватчик fetch для обработки 401 (истечение токена)
    const originalFetch = window.fetch;
    window.fetch = async function() {
        try {
            const response = await originalFetch.apply(this, arguments);
            if (response.status === 401) {
                window.location.href = '/?route=login';
            }
            return response;
        } catch (error) {
            throw error;
        }
    };
</script>
