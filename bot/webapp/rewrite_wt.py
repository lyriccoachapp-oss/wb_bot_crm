import re

with open('wt.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Replace the PHP head block
head_pattern = r'<\?php\n.*?\$hasOpen\s*=\s*false;\nforeach\s*\(\$workEntries\s*as\s*\$w\).*?\n.*?\n.*?\n.*?\n\$backendUrl\s*=\s*.*?\n\?>'
new_head = """<?php
date_default_timezone_set('America/Halifax');

$APP_VERSION = '2.0.0';
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
?>"""
content = re.sub(head_pattern, new_head, content, flags=re.DOTALL)

# 2. Replace the HTML rendering of entries
html_pattern = r'<div id="entries">\s*<\?php\s*\n\$placeMap.*?</div>\s*</div>'
new_html = """<div id="entries">
    <!-- Сюда будут загружены смены через JS -->
    <div style="text-align:center; padding: 2rem;" id="loadingIndicator">
        <div class="spinner"></div> Загрузка...
    </div>
</div>"""
content = re.sub(html_pattern, new_html, content, flags=re.DOTALL)

# 3. Fix the addBlock dynamic class
content = content.replace('<div class="add-block<?= $hasOpen ? \' disabled\' : \'\' ?>" id="addBlock" onclick="startCheckin()">+</div>', '<div class="add-block disabled" id="addBlock" onclick="startCheckin()">+</div>')

# 4. Inject auth.js script
content = content.replace('</style>', '</style>\n\t<script src="https://telegram.org/js/telegram-web-app.js"></script>\n\t<script src="auth.js"></script>')

# 5. Update JS logic
script_pattern = r'// ====== ДАННЫЕ ======.*?let currentWork = <\?= \$hasOpen \? \'true\' : \'false\' \?>;'
new_script = """// ====== ДАННЫЕ ======
const texts = <?= json_encode(['location_ready' => translate('location_ready'), 'location_error' => translate('location_error'), 'getting_location' => translate('getting_location'), 'finish_work' => translate('finish_work'), 'enter_comment' => translate('enter_comment'), 'checkout' => translate('checkout'), 'close' => translate('close'), 'checkout_error' => translate('checkout_error'), 'select_object' => translate('select_object'), 'search' => translate('search'), 'checkin' => translate('checkin'), 'checkin_error' => translate('checkin_error'), 'select_place' => translate('select_place'), 'date_label' => translate('date_label'), 'start' => translate('start'), 'completed' => translate('completed'), 'comment' => translate('comment'), 'object' => translate('object')], JSON_UNESCAPED_UNICODE) ?>;
let places = [];
let currentWork = false;
const todayStr = <?= json_encode($today) ?>;
const yesterdayStr = <?= json_encode($yesterday) ?>;

// ====== ИНИЦИАЛИЗАЦИЯ ======
async function initApp() {
try {
const [placesData, timeData] = await Promise.all([
WebAppAPI.request('/references/objects?status=1'), // Только активные!
WebAppAPI.request(`/time-entries?date_from=${yesterdayStr}&date_to=${todayStr}`)
]);
places = placesData.data || [];

const workEntries = timeData.data || [];
renderEntries(workEntries);

} catch (err) {
console.error(err);
document.getElementById('entries').innerHTML = `<div style="text-align:center;color:red;padding:1rem;">Ошибка загрузки данных: ${err.message}</div>`;
}
}

function renderEntries(entries) {
const container = document.getElementById('entries');
container.innerHTML = '';
let hasOpen = false;

const placeMap = {};
places.forEach(p => { placeMap[p.id] = p.name || p.place_name; });

entries.forEach(w => {
const closed = w.checkout && w.checkout !== '0000-00-00 00:00:00';
if (!closed) hasOpen = true;

const pname = placeMap[w.place_id] || texts.object;
const dayLabel = w.workday === todayStr ? todayStr : yesterdayStr;

const div = document.createElement('div');
div.className = 'entry';
div.dataset.id_worktime = w.id;
div.dataset.closed = closed ? '1' : '0';
div.onclick = function() { handleEntryClick(this); };

let html = `<strong>${escapeHtml(pname)}</strong><br>
<span class='entry-label'>${texts.date_label}</span>${escapeHtml(dayLabel)}<br>
<span class='entry-label'>${texts.start}: </span> ${escapeHtml(w.checkin)}`;

if (closed) {
html += `<br><span class='entry-label'>${texts.completed}: </span> ${escapeHtml(w.checkout)}
<br><span class='entry-label'>${texts.comment}: </span> ${escapeHtml(w.work_desc || '')}`;
}
div.innerHTML = html;
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
"""
content = re.sub(script_pattern, new_script, content, flags=re.DOTALL)

# 6. Update submitCheckout to use WebAppAPI
checkout_pattern = r'getLocationForAction\(\)\s*\.then\(pos => \{\s*logGeo.*?return fetch\(backendUrl, \{.*?\n\s*method:\'POST\'.*?\}\);\s*\}\).*?\.then\(res => res\.json\(\)\)\s*\.then\(data => \{'
new_checkout = """getLocationForAction()
.then(pos => {
logGeo('Got coords for checkout. Sending to server...');
return WebAppAPI.request('/time-entries/check-out', 'POST', {
id: id_worktime,
comment: comment,
latitude: pos.coords.latitude,
longitude: pos.coords.longitude
});
})
.then(data => {"""
content = re.sub(checkout_pattern, new_checkout, content, flags=re.DOTALL)

# 7. Update submitCheckin to use WebAppAPI
checkin_pattern = r'getLocationForAction\(\)\s*\.then\(pos => \{\s*logGeo.*?return fetch\(backendUrl, \{.*?\n\s*method:\'POST\'.*?\}\);\s*\}\).*?\.then\(res => res\.json\(\)\)\s*\.then\(data => \{'
new_checkin = """getLocationForAction()
.then(pos => {
logGeo('Got coords for checkin. Sending to server...');
return WebAppAPI.request('/time-entries/check-in', 'POST', {
place_id: id_place,
latitude: pos.coords.latitude,
longitude: pos.coords.longitude
});
})
.then(data => {"""
content = re.sub(checkin_pattern, new_checkin, content, flags=re.DOTALL)


# Update checkout success handler
content = content.replace("if (data && data.status === 'ok')", "if (data && data.success)")
content = content.replace("div.dataset.id_worktime = data.id_worktime;", "div.dataset.id_worktime = data.data.id;")
content = content.replace("const checkinTime = data.checkin_time;", "const checkinTime = data.data.checkin;")

# Also start initApp on load
content = content.replace("startGeoTracking();\n</script>", "startGeoTracking();\n\tinitApp();\n</script>")


with open('wt.php', 'w', encoding='utf-8') as f:
    f.write(content)
