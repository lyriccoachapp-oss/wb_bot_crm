<?php
$currentRoute = $_GET['route'] ?? 'dashboard';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/?route=dashboard" class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <!-- Логотип как в Dasher -->
                <img src="/public/img/logo1.svg" style="width:100%; height:100%; object-fit: contain;" alt="logo">
            </div>
            <span class="sidebar-logo-text">WorkBangers</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Дашборд -->
        <a href="/?route=dashboard" class="nav-item <?= $currentRoute === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
            <span class="nav-item-text">Дашборд</span>
        </a>

        <!-- Управление -->
        <div class="nav-section-title">Управление</div>
        <a href="/?route=users" class="nav-item <?= $currentRoute === 'users' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="nav-item-text">Сотрудники</span>
        </a>
        <a href="/?route=objects" class="nav-item <?= $currentRoute === 'objects' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
            <span class="nav-item-text">Объекты</span>
        </a>
        <a href="/?route=companies" class="nav-item <?= $currentRoute === 'companies' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/><path d="M9 7h6"/><path d="M9 11h6"/></svg></span>
            <span class="nav-item-text">Компании</span>
        </a>

        <!-- Аналитика -->
        <div class="nav-section-title">Аналитика</div>
        <a href="/?route=reports" class="nav-item <?= $currentRoute === 'reports' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
            <span class="nav-item-text">Отчеты</span>
        </a>
        <a href="/?route=receipts" class="nav-item <?= $currentRoute === 'receipts' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
            <span class="nav-item-text">Чеки</span>
        </a>
        <a href="/?route=geolocation" class="nav-item <?= $currentRoute === 'geolocation' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
            <span class="nav-item-text">Геолокация</span>
        </a>

        <!-- Администрирование -->
        <div class="nav-section-title">Администрирование</div>
        <a href="/?route=roles" class="nav-item <?= $currentRoute === 'roles' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
            <span class="nav-item-text">Роли и права</span>
        </a>
        <a href="/?route=content" class="nav-item <?= $currentRoute === 'content' ? 'active' : '' ?>">
            <span class="nav-item-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></span>
            <span class="nav-item-text">Тексты сайта</span>
        </a>
    </nav>
</aside>
