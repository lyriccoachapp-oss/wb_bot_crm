const TRANSLATIONS = {
	ru: {
		confirm_delete: "Вы уверены, что хотите окончательно удалить этот чек? Это действие удалит изображение с Google Drive и запись из базы данных.",
		delete_success: "Чек успешно удален!",
		delete_error: "Ошибка при удалении: ",
		save_success: "Чек успешно обновлен!",
		save_error: "Ошибка при сохранении: ",
		error: "Ошибка",
		deleting: "Удаление...",
		saving: "Сохранение...",
		org_required: "Ошибка: Пожалуйста, выберите Компанию перед сохранением.",
		emp_required: "Ошибка: Пожалуйста, выберите Сотрудника перед сохранением.",
		obj_required: "Ошибка: Пожалуйста, выберите Объект перед сохранением.",
	},
	uk: {
		confirm_delete: "Ви впевнені, що хочете остаточно видалити цей чек? Ця дія видалить зображення з Google Drive та запис з бази даних.",
		delete_success: "Чек успішно видалено!",
		delete_error: "Помилка при видаленні: ",
		save_success: "Чек успішно оновлено!",
		save_error: "Помилка при збереженні: ",
		error: "Помилка",
		deleting: "Видалення...",
		saving: "Збереження...",
		org_required: "Помилка: Будь ласка, виберіть Компанію перед збереженням.",
		emp_required: "Помилка: Будь ласка, виберіть Співробітника перед збереженням.",
		obj_required: "Помилка: Будь ласка, виберіть Об'єкт перед збереженням.",
	},
	en: {
		confirm_delete: "Are you sure you want to permanently delete this receipt? This action will delete the image from Google Drive and the record from the database.",
		delete_success: "Receipt deleted successfully!",
		delete_error: "Error deleting: ",
		save_success: "Receipt updated successfully!",
		save_error: "Error saving: ",
		error: "Error",
		deleting: "Deleting...",
		saving: "Saving...",
		org_required: "Error: Please select a Company before saving.",
		emp_required: "Error: Please select an Employee before saving.",
		obj_required: "Error: Please select an Object before saving.",
	}
};

function __(key) {
	const lang = window.CURRENT_LANG || 'ru';
	return (TRANSLATIONS[lang] || TRANSLATIONS['ru'])[key] || key;
}

async function apiFetch(url, options = {}) {
	if (!options.headers) {
		options.headers = {};
	}
	options.headers['Authorization'] = 'Bearer ' + localStorage.getItem('access_token');
	options.headers['Accept'] = 'application/json';

	try {
		let response = await fetch(url, options);

		if (response.status === 401) {
			// Попытка обновить токен через PHP-сессию
			const refreshResponse = await fetch('/?route=refresh-token');
			if (refreshResponse.ok) {
				const refreshData = await refreshResponse.json();
				if (refreshData.success && refreshData.access_token) {
					localStorage.setItem('access_token', refreshData.access_token);
					options.headers['Authorization'] = 'Bearer ' + refreshData.access_token;
					return await fetch(url, options);
				}
			}
			window.location.href = '/?route=logout';
			throw new Error('Unauthorized');
		}

		return response;
	} catch (error) {
		console.error('API request failed:', error);
		throw error;
	}
}

/* ========================= JS — Блок 10: переменные/ссылки ========================= */
const picker = document.getElementById('picker');
const tasks = document.getElementById('tasks');
const refInfo = document.getElementById('refInfo');

const upBar=document.getElementById('upBar'), recBar=document.getElementById('recBar');
const upCount=document.getElementById('upCount'), upTotal=document.getElementById('upTotal');
const recCount=document.getElementById('recCount'), recTotal=document.getElementById('recTotal');

const globCompany = document.getElementById('global-company');
const globEmployee = document.getElementById('global-employee');
const globObject = document.getElementById('global-object');

const editModal = document.getElementById('editModal');
const emClose = document.getElementById('emClose');
const emSave = document.getElementById('emSave');
const emImg = document.getElementById('emImg');
const emLens = document.getElementById('emLens');
const emRotate = document.getElementById('emRotate');
const emRerun = document.getElementById('emRerun');
const emCrop = document.getElementById('emCrop');
const emReset = document.getElementById('emReset');
const emApply = document.getElementById('emApply');
const emForm = document.getElementById('emForm');

let cropperInstance = null;
let isImageModified = false;
let isCroppingActive = false;

let EMP=[], OBJ=[], COMP=[], refReady=false;
let pollInterval = null;
const state = new Map();

let uploaded=0, totalToUpload=0, recognized=0, totalToRecognize=0;

function setUploadTotals(t){ totalToUpload=t; upTotal.textContent=t; upBar.style.width = (t?Math.round(uploaded/t*100):0)+'%'; }
function incUploaded(){ uploaded++; upCount.textContent=uploaded; upBar.style.width = Math.round(uploaded/totalToUpload*100)+'%'; }
function setRecTotals(t){ totalToRecognize=t; recTotal.textContent=t; recBar.style.width = (t?Math.round(recognized/t*100):0)+'%'; }
function incRecognized(){ recognized++; recCount.textContent=recognized; recBar.style.width = Math.round(recognized/totalToRecognize*100)+'%'; }

function uid(){ return 't_'+Math.random().toString(36).slice(2,8); }
function escapeHTML(s){ return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

/* ========================= JS — Блок 11: загрузка справочников ========================= */
(async function loadRefs(){
  if (!refInfo) return; // if not on tab
  try{
    const eFetch = await apiFetch('/api/v1/references/employees');
    const oFetch = await apiFetch('/api/v1/references/objects');
    const cFetch = await apiFetch('/api/v1/references/companies');

    if (!eFetch.ok) throw new Error('Employees API Error: ' + eFetch.status);
    if (!oFetch.ok) throw new Error('Objects API Error: ' + oFetch.status);
    if (!cFetch.ok) throw new Error('Companies API Error: ' + cFetch.status);

    const eRes = await eFetch.json();
    const oRes = await oFetch.json();
    const cRes = await cFetch.json();
    
    EMP = normalizeEmployees(eRes.data !== undefined ? eRes.data : eRes); 
    OBJ = normalizeObjects(oRes.data !== undefined ? oRes.data : oRes);
    COMP = normalizeCompanies(cRes.data !== undefined ? cRes.data : cRes);

    refReady = true; 
	refInfo.textContent = `Справочники загружены (Сотрудники: ${EMP.length}, Объекты: ${OBJ.length}, Компании: ${COMP.length})`;

    fillDropdown(globCompany, COMP, 'slug', 'name');
    fillDropdown(globEmployee, EMP, 'id_telegram', 'name');
    fillDropdown(globObject, OBJ, 'id', 'name');

    // Populate modal selects too
    fillDropdown(emForm.querySelector('[name=receipt_org]'), COMP, 'slug', 'name');
    fillDropdown(emForm.querySelector('[name=id_telegram]'), EMP, 'id_telegram', 'name');
    fillDropdown(emForm.querySelector('[name=place_id]'), OBJ, 'id', 'name');

  }catch(err){ refInfo.textContent = 'Ошибка загрузки справочников: '+(err.message||err); }
})();

function normalizeEmployees(raw){ const out=[]; if(Array.isArray(raw)){ for(const it of raw){ const id=it.id_telegram??it.telegram_id??it.id??null; const name=it.name??it.full_name??it.title??it.username??null; if(id&&name) out.push({id_telegram:String(id),name:String(name)}); } } else if(raw&&typeof raw==='object'){ for(const k of Object.keys(raw)) out.push({id_telegram:String(k),name:String(raw[k])}); } const seen=new Set(); return out.filter(x=> (seen.has(x.id_telegram)?false:(seen.add(x.id_telegram),true))); }
function normalizeObjects(raw){ const out=[]; if(Array.isArray(raw)){ for(const it of raw){ const id=it.id??it.object_id??it.obj_id??null; const name=it.name??it.title??it.object_name??null; if(id&&name) out.push({id:String(id),name:String(name)}); } } else if(raw&&typeof raw==='object'){ for(const k of Object.keys(raw)) out.push({id:String(k),name:String(raw[k])}); } const seen=new Set(); return out.filter(x=> (seen.has(x.id)?false:(seen.add(x.id),true))); }
function normalizeCompanies(raw){ const out=[]; if(Array.isArray(raw)){ for(const it of raw){ const slug=it.slug??it.id??null; const name=it.name??it.title??null; if(slug&&name) out.push({slug:String(slug),name:String(name)}); } } return out; }
function fillDropdown(sel,list,idKey,nameKey){ sel.innerHTML='<option value="">— Не выбрано —</option>'; for(const it of list){ const o=document.createElement('option'); o.value=it[idKey]; o.textContent=it[nameKey]; sel.appendChild(o);} }

/* ========================= JS — Блок 13: выбор файлов ========================= */
if (picker) {
	picker.addEventListener('change', ()=>{
	  const files=Array.from(picker.files||[]);
	  if(!files.length) return;
	  for(const f of files) handleFile(f);
	  picker.value='';
	});
}

function parseFilenameHeuristics(name){
  let base = name.replace(/\.[^.]+$/, '');
  base = base.replace(/^[0-9]+(?:__|-{3,})/, ''); 

  const firstUnd = base.indexOf('_');
  const datePart = firstUnd >= 0 ? base.slice(0, firstUnd) : base;
  const rest     = firstUnd >= 0 ? base.slice(firstUnd + 1) : '';

  const date = (function(s){
    let m;
    if ((m = s.match(/^(\d{4})[-_.](\d{2})[-_.](\d{2})$/))) return `${m[1]}-${m[2]}-${m[3]}`;
    if ((m = s.match(/^(\d{4})(\d{2})(\d{2})$/)))          return `${m[1]}-${m[2]}-${m[3]}`;
    if ((m = s.match(/^(\d{2})[-_.](\d{2})[-_.](\d{4})$/)))return `${m[3]}-${m[2]}-${m[1]}`;
    return s;
  })(datePart);

  let object = '';
  let employee = '';
  if (rest) {
    const parts = rest.split('_');
    if (parts.length >= 3) {
      employee = parts.slice(-2).join('_');   
      object   = parts.slice(0, -2).join('_');
    } else {
      const lastUnd = rest.lastIndexOf('_');
      if (lastUnd === -1) object = rest;
      else { object = rest.slice(0, lastUnd); employee = rest.slice(lastUnd + 1); }
    }
  }

  object   = object.replace(/_/g, ' ').trim();
  employee = employee.replace(/\s*\(\d+\)\s*$/, '').trim();

  return { date, object, employee };
}

function normalizeForCompare(s){ return String(s||'').trim().toLowerCase(); }
function findOptionByText(sel, label){
  if(!label) return null;
  const target = String(label).trim();
  const opts = Array.from(sel.options);

  let found = opts.find(o => normalizeForCompare(o.text) === normalizeForCompare(target));
  if(found) return found;

  const variants = [ target.replace(/ /g, '_'), target.replace(/_/g, ' ') ];
  for(const v of variants){
    found = opts.find(o => normalizeForCompare(o.text) === normalizeForCompare(v));
    if(found) return found;
  }
  return null;
}

/* ========================= JS — Блок 14: создание карточки ========================= */
function handleFile(file) {
    const tempId = 'temp_' + uid();
    const tempCard = createTempCard(tempId, file, file.name);
    uploadToQueue(file, tempId, tempCard);
}

function createTempCard(id, file, filename) {
    const el = document.createElement('div');
    el.className = 'compact-card blurred';
    el.id = id;
    el.innerHTML = `
      <img class="cc-thumb" src="${URL.createObjectURL(file)}" alt="Uploading...">
      <div class="cc-info">
        <div class="cc-title" title="${escapeHTML(filename)}">${escapeHTML(filename)}</div>
        <div class="cc-meta">
          <span class="cc-status text-muted">В очереди на распознавание</span>
        </div>
        <div class="cc-summary text-muted" style="font-size: 0.85em; margin-top: 5px; display: none; line-height: 1.2;"></div>
      </div>
    `;
    tasks.prepend(el);
    return el;
}

async function uploadToQueue(file, tempId, tempCard) {
    try {
        const fd = new FormData();
        fd.append('file', file);
        if (file.lastModified) fd.append('file_last_modified', file.lastModified);
        if (globCompany) fd.append('global_company', globCompany.value);
        if (globEmployee) fd.append('global_employee', globEmployee.value);
        if (globObject) fd.append('global_object', globObject.value);

        const r = await apiFetch('/api/v1/receipts/queue', {
            method: 'POST',
            body: fd
        });
        const data = await r.json();
        
        if (!data.success) throw new Error(data.error || 'Upload error');
        
        // Превращаем временную карточку в постоянную (чтобы не моргала картинка)
        const id = String(data.id);
        tempCard.id = id;
        
        // Добавляем кнопки, если их нет
        if (!tempCard.querySelector('.cc-actions')) {
            const actions = document.createElement('div');
            actions.className = 'cc-actions';
            actions.innerHTML = `
              <button class="cc-btn-save" disabled title="Сохранить"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg></button>
              <button class="cc-btn-del" title="Удалить из очереди"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
            `;
            tempCard.appendChild(actions);
        }

        const itemData = {
            id: id,
            original_filename: file.name,
            status: 'pending'
        };
        
        const cardState = buildCardState(id, tempCard, itemData);
        // Сохраняем локальный URL для лупы (чтобы не скачивать картинку заново)
        cardState.url = tempCard.querySelector('.cc-thumb').src;
        state.set(id, cardState);
        
        incUploaded();
        pollQueue(); // Сразу опрашиваем очередь
	} catch (err) {
		tempCard.querySelector('.cc-status').textContent = 'Ошибка загрузки: ' + err.message;
		tempCard.querySelector('.cc-status').className = 'cc-status text-danger';
		tempCard.classList.remove('blurred');

		// Добавляем кнопку удаления, чтобы пользователь мог убрать битую карточку с экрана
		if (!tempCard.querySelector('.cc-actions')) {
			const actions = document.createElement('div');
			actions.className = 'cc-actions';
			actions.innerHTML = `
				<button class="cc-btn-del" title="Удалить"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
			`;
			tempCard.appendChild(actions);

			actions.querySelector('.cc-btn-del').addEventListener('click', (e) => {
				e.stopPropagation();
				tempCard.remove();
			});
		}
	}
}

function startPolling() {
    pollQueue();
    pollInterval = setInterval(pollQueue, 3000);
}

async function pollQueue() {
    try {
        const r = await apiFetch('/api/v1/receipts/queue');
        const data = await r.json();
        if (data.success) {
            renderQueue(data.data);
        }
    } catch (err) {
        console.error('Polling error:', err);
    }
}

function renderQueue(items) {
    // Удаляем из state те элементы, которых больше нет в items (игнорируя temp_*)
    const incomingIds = new Set(items.map(it => String(it.id)));
    for (const [id, card] of state.entries()) {
        if (!id.startsWith('temp_') && !incomingIds.has(String(id))) {
            card.el.remove();
            state.delete(id);
        }
    }

    // Обновляем или создаем карточки
    items.forEach(item => {
        const id = String(item.id);
        if (state.has(id)) {
            updateCardFromItem(state.get(id), item);
        } else {
            createCardFromItem(item);
        }
    });
}

function createCardFromItem(item) {
    const id = String(item.id);
    const el = document.createElement('div');
    el.className = 'compact-card blurred';
    const token = localStorage.getItem('access_token');
    el.innerHTML = `
      <img class="cc-thumb" src="/api/v1/receipts/queue/${id}/image?token=${token}" alt="Receipt">
      <div class="cc-info">
        <div class="cc-title" title="${escapeHTML(item.original_filename)}">${escapeHTML(item.original_filename)}</div>
        <div class="cc-meta">
          <span class="cc-status text-muted">В очереди на распознавание</span>
        </div>
        <div class="cc-summary text-muted" style="font-size: 0.85em; margin-top: 5px; display: none; line-height: 1.2;"></div>
      </div>
      <div class="cc-actions">
          <button class="cc-btn-save" disabled title="Сохранить"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg></button>
          <button class="cc-btn-del" title="Удалить из очереди"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>
      </div>
    `;
    // Новые элементы добавляем в конец (или в начало, как удобнее)
    tasks.appendChild(el);

    const cardState = buildCardState(id, el, item);
    state.set(id, cardState);
    updateCardFromItem(cardState, item);
}

function buildCardState(id, el, item) {
    const cardState = {
        id: id,
        el: el,
        dbItem: item,
        rotateQuarterTurns: 0,
        url: `/api/v1/receipts/queue/${id}/image?token=` + localStorage.getItem('access_token'), // По умолчанию тянем с сервера
        
        img: el.querySelector('.cc-thumb'),
        titleEl: el.querySelector('.cc-title'),
        statusText: el.querySelector('.cc-status'),
        btnSave: el.querySelector('.cc-btn-save'),
        btnDel: el.querySelector('.cc-btn-del'),
        metaEl: el.querySelector('.cc-meta'),
        
        data: {
            receipt_org: item.global_company || '',
            id_telegram: item.global_employee || '',
            place_id: item.global_object || '',
            merchant_name: '',
            merchant_address: '',
            payment_method: '',
            card_last4: '',
            receipt_date: '',
            receipt_time: '',
            subtotal: '',
            tax: '',
            receipt_amount: '',
            receipt_type: '',
            currency: 'CAD',
            comment: '',
            items_json: '',
            ocr_text: ''
        }
    };

    el.addEventListener('click', (e) => {
        if (e.target.closest('.cc-btn-save') || e.target.closest('.cc-btn-del') || el.classList.contains('blurred')) return;
        openEditModal(cardState);
    });

    cardState.btnSave.addEventListener('click', (e) => {
        e.stopPropagation();
        receipt_save(cardState);
    });

    cardState.btnDel.addEventListener('click', (e) => {
        e.stopPropagation();
        if (confirm('Удалить чек из очереди?')) {
            receipt_delete(cardState);
        }
    });

    return cardState;
}

async function receipt_delete(card) {
    card.statusText.textContent = 'Удаление...';
    try {
        const r = await apiFetch(`/api/v1/receipts/queue/${card.id}`, {
            method: 'DELETE'
        });
        // Удаление из DOM произойдет при следующем pollQueue, 
        // но чтобы интерфейс реагировал мгновенно:
        card.el.remove();
        state.delete(card.id);
    } catch (err) {
        card.statusText.textContent = 'Ошибка удаления';
    }
}

function updateCardFromItem(card, item) {
    card.dbItem = item;
    const status = item.status;

    if (status === 'pending') {
        card.statusText.textContent = 'В очереди на распознавание';
        card.statusText.className = 'cc-status text-muted';
        card.el.classList.add('blurred');
        card.btnSave.disabled = true;
    } else if (status === 'processing') {
        card.statusText.textContent = 'Распознавание...';
        card.statusText.className = 'cc-status text-muted';
        card.el.classList.add('blurred');
        card.btnSave.disabled = true;
    } else if (status === 'ready') {
        card.statusText.textContent = 'Распознано';
        card.statusText.className = 'cc-status text-success';
        card.el.classList.remove('blurred');
        card.btnSave.disabled = false;
        
        // Заполняем data из parsed_data если еще не заполнено
        if (item.parsed_data && !card.data._is_mapped) {
            const f = item.parsed_data;
            card.data.merchant_name = f.merchant_name || '';
            card.data.merchant_address = f.merchant_address || '';
            card.data.receipt_date = f.receipt_date || '';
            card.data.receipt_time = f.receipt_time || '';
            card.data.subtotal = f.subtotal || '';
            card.data.tax = f.tax || '';
            card.data.receipt_amount = f.receipt_amount || '';
            card.data.currency = f.currency || 'CAD';
            card.data.payment_method = f.payment_method || '';
            card.data.card_last4 = f.card_last4 || '';
            card.data.receipt_type = f.receipt_type || '';
            card.data.items_json = typeof f.items_json === 'string' ? f.items_json : JSON.stringify(f.items_json || []);
            card.data.ocr_text = f.ocr_text || JSON.stringify(f);
            card.data._is_mapped = true;
            incRecognized();
            
            // Заполняем summary
            const summary = card.el.querySelector('.cc-summary');
            if (summary) {
                const parts = [];
                if (card.data.merchant_name) parts.push(`<b>${escapeHTML(card.data.merchant_name)}</b>`);
                
                let dt = [];
                if (card.data.receipt_date) dt.push(card.data.receipt_date);
                if (card.data.receipt_time) dt.push(card.data.receipt_time);
                if (dt.length > 0) parts.push(`Дата: ${dt.join(' ')}`);

                let am = [];
                if (card.data.subtotal) am.push(`Без налога: ${card.data.subtotal}`);
                if (card.data.tax) am.push(`Налог: ${card.data.tax}`);
                if (card.data.receipt_amount) am.push(`<b>Итог: ${card.data.receipt_amount}</b>`);
                if (am.length > 0) parts.push(am.join(' | '));

                let pmt = [];
                if (card.data.payment_method) pmt.push(card.data.payment_method);
                if (card.data.card_last4) pmt.push(`*${card.data.card_last4}`);
                if (pmt.length > 0) parts.push(`Оплата: ${pmt.join(' ')}`);

                if (parts.length > 0) {
                    summary.innerHTML = parts.join('<br>');
                    summary.style.display = 'block';
                }
            }
        }
        if (typeof updateCardVisuals === 'function') updateCardVisuals(card);
    } else if (status === 'failed') {
        card.statusText.textContent = 'Ошибка: ' + (item.error_message || 'Неизвестная ошибка');
        card.statusText.className = 'cc-status text-danger';
        card.el.classList.remove('blurred');
        card.btnSave.disabled = false; // Можно сохранить вручную
        
        const summary = card.el.querySelector('.cc-summary');
        if (summary) summary.style.display = 'none';
    }
}

/* ========================= JS — Блок 17: Модальное окно и Лупа ========================= */
let activeCard = null;

function editSavedReceipt(receipt) {
	const token = localStorage.getItem('access_token');
	const imgUrl = `/api/v1/receipts/${receipt.id}/image?token=${token}`;
	
	const cardState = {
		id: receipt.id,
		isSavedReceipt: true, // Флаг, что это сохраненный чек
		rotateQuarterTurns: 0,
		url: imgUrl,
		data: {
			receipt_org: receipt.receipt_org || '',
			id_telegram: receipt.telegram_id || '',
			place_id: receipt.place_id || '',
			merchant_name: receipt.merchant_name || '',
			merchant_address: receipt.merchant_address || '',
			payment_method: receipt.payment_method || '',
			card_last4: receipt.card_last4 || '',
			receipt_date: receipt.date || '',
			receipt_time: receipt.time || '',
			subtotal: receipt.subtotal || '',
			tax: receipt.tax || '',
			receipt_amount: receipt.amount || '',
			receipt_type: receipt.category || '',
			currency: receipt.currency || 'CAD',
			comment: receipt.comment || '',
			items_json: receipt.items_json || '[]',
			ocr_text: receipt.ocr_text || ''
		}
	};

	openEditModal(cardState);
}

function initCropper(card) {
	if (cropperInstance) {
		cropperInstance.destroy();
		cropperInstance = null;
	}
	isCroppingActive = false;
	if (emCrop) emCrop.classList.remove('active');
	if (emApply) emApply.style.display = 'none';

	// Сбрасываем CSS-трансформы с превью
	emImg.style.transform = 'none';

	// Инициализируем Cropper.js
	cropperInstance = new Cropper(emImg, {
		viewMode: 1,
		dragMode: 'move', // По умолчанию перетаскивание картинки
		autoCrop: false,  // Сначала без рамки обрезки
		responsive: true,
		restore: false,
		checkCrossOrigin: false,
		ready() {
			// Если у карточки сохранен поворот, поворачиваем в кроппере
			if (card && card.rotateQuarterTurns) {
				cropperInstance.rotate(card.rotateQuarterTurns * 90);
			}
		},
		crop(event) {
			// Проверяем, изменились ли размеры/координаты рамки относительно исходных
			const data = cropperInstance.getData();
			const imageInfo = cropperInstance.getImageData();

			const rotated = data.rotate !== 0;
			const cropped = Math.abs(data.width - imageInfo.naturalWidth) > 5 || 
							Math.abs(data.height - imageInfo.naturalHeight) > 5 ||
							Math.abs(data.x) > 5 ||
							Math.abs(data.y) > 5;

			if (rotated || (isCroppingActive && cropped)) {
				isImageModified = true;
			}
		}
	});
}

function getModifiedBlob() {
	return new Promise((resolve) => {
		if (!cropperInstance || !isImageModified) {
			resolve(null);
			return;
		}

		const canvas = cropperInstance.getCroppedCanvas({
			imageSmoothingEnabled: true,
			imageSmoothingQuality: 'high'
		});

		if (!canvas) {
			resolve(null);
			return;
		}

		canvas.toBlob((blob) => {
			resolve(blob);
		}, 'image/jpeg', 0.9);
	});
}

function openEditModal(card) {
	activeCard = card;
	isImageModified = false;

	// Подготовка картинки
	emImg.src = card.url;

	// Заполнение формы
	const form = emForm;
	for (const key in card.data) {
		const input = form.querySelector(`[name="${key}"]`);
		if (input) input.value = card.data[key];
	}

	// Показываем/скрываем кнопки удаления и перераспознавания
	const emDelete = document.getElementById('emDelete');
	if (emDelete) {
		if (card.isSavedReceipt) {
			emDelete.style.display = 'inline-block';
		} else {
			emDelete.style.display = 'none';
		}
	}

	if (emRerun) {
		emRerun.style.display = 'inline-block'; // Всегда показываем перераспознавание
	}

	// Ждем загрузки изображения для инициализации Cropper
	emImg.onload = () => {
		initCropper(card);
		setupMagnifier(card);
		emImg.onload = null;
	};

	if (emImg.complete) {
		initCropper(card);
		setupMagnifier(card);
		emImg.onload = null;
	}

	editModal.classList.add('open');
}

function closeEditModal() {
	if (cropperInstance) {
		cropperInstance.destroy();
		cropperInstance = null;
	}
	editModal.classList.remove('open');
	activeCard = null;
	emLens.style.display = 'none';
}

if (emClose) emClose.addEventListener('click', closeEditModal);

if (emSave) {
	emSave.addEventListener('click', async () => {
		if (!activeCard) return;
		// Переносим данные из формы обратно в state
		const form = emForm;
		for (const key in activeCard.data) {
			const input = form.querySelector(`[name="${key}"]`);
			if (input) activeCard.data[key] = input.value;
		}
		
		if (!activeCard.data.receipt_org || activeCard.data.receipt_org === '0') {
			alert(__('org_required'));
			return;
		}
		if (!activeCard.data.id_telegram || activeCard.data.id_telegram === '0') {
			alert(__('emp_required'));
			return;
		}
		if (!activeCard.data.place_id || activeCard.data.place_id === '0') {
			alert(__('obj_required'));
			return;
		}
		
		const oldText = emSave.innerText;
		emSave.innerText = __('saving');
		emSave.disabled = true;

		const success = await receipt_save(activeCard);
		
		emSave.innerText = oldText;
		emSave.disabled = false;

		if (success) {
			closeEditModal();
		}
	});
}

const emDelete = document.getElementById('emDelete');
if (emDelete) {
	emDelete.addEventListener('click', async () => {
		if (!activeCard || !activeCard.isSavedReceipt) return;
		
		if (confirm(__('confirm_delete'))) {
			const oldText = emDelete.innerText;
			emDelete.innerText = __('deleting');
			emDelete.disabled = true;
			
			try {
				const r = await apiFetch(`/api/v1/receipts/${activeCard.id}`, {
					method: 'DELETE'
				});
				
				const data = await r.json();
				if (!data.success) throw new Error(data.error || 'Delete failed');
				
				alert(__('delete_success'));
				closeEditModal();
				window.location.reload();
			} catch (err) {
				alert(__('delete_error') + (err.message || err));
				emDelete.innerText = oldText;
				emDelete.disabled = false;
			}
		}
	});
}

if (emRotate) {
	emRotate.addEventListener('click', () => {
		if (!cropperInstance) return;
		cropperInstance.rotate(90);
		isImageModified = true;
		if (activeCard) {
			activeCard.rotateQuarterTurns = (activeCard.rotateQuarterTurns + 1) % 4;
		}
	});
}

if (emCrop) {
	emCrop.addEventListener('click', () => {
		if (!cropperInstance) return;
		if (!isCroppingActive) {
			cropperInstance.crop();
			cropperInstance.setDragMode('crop');
			isCroppingActive = true;
			emCrop.classList.add('active');
			if (emApply) emApply.style.display = 'inline-block';
			if (emLens) emLens.style.display = 'none';
		} else {
			cropperInstance.clear();
			cropperInstance.setDragMode('move');
			isCroppingActive = false;
			emCrop.classList.remove('active');
			if (emApply) emApply.style.display = 'none';
		}
	});
}

if (emApply) {
	emApply.addEventListener('click', () => {
		if (!cropperInstance || !isCroppingActive) return;

		// Получаем обрезанное изображение
		const canvas = cropperInstance.getCroppedCanvas({
			imageSmoothingEnabled: true,
			imageSmoothingQuality: 'high'
		});

		if (!canvas) return;

		canvas.toBlob((blob) => {
			if (!blob) return;

			// Создаем Blob URL
			const newUrl = URL.createObjectURL(blob);

			// Деактивируем кроппер и меняем src
			if (cropperInstance) {
				cropperInstance.destroy();
				cropperInstance = null;
			}

			// Устанавливаем новый src
			emImg.src = newUrl;
			isImageModified = true;
			isCroppingActive = false;

			if (emCrop) emCrop.classList.remove('active');
			if (emApply) emApply.style.display = 'none';

			// Инициализируем кроппер заново с новым изображением
			emImg.onload = () => {
				initCropper(activeCard);
				setupMagnifier(activeCard);
				emImg.onload = null;
			};
			if (emImg.complete) {
				initCropper(activeCard);
				setupMagnifier(activeCard);
				emImg.onload = null;
			}
		}, 'image/jpeg', 0.9);
	});
}

if (emReset) {
	emReset.addEventListener('click', () => {
		if (!activeCard) return;

		if (cropperInstance) {
			cropperInstance.destroy();
			cropperInstance = null;
		}

		emImg.src = activeCard.url;
		isCroppingActive = false;
		isImageModified = false;

		if (emCrop) emCrop.classList.remove('active');
		if (emApply) emApply.style.display = 'none';
		activeCard.rotateQuarterTurns = 0;

		emImg.onload = () => {
			initCropper(activeCard);
			setupMagnifier(activeCard);
			emImg.onload = null;
		};
		if (emImg.complete) {
			initCropper(activeCard);
			setupMagnifier(activeCard);
			emImg.onload = null;
		}
	});
}

if (emRerun) {
	emRerun.addEventListener('click', async () => {
		if (!activeCard) return;

		const oldText = emRerun.innerText;
		emRerun.innerText = 'Ожидание...';
		emRerun.disabled = true;

		try {
			const blob = await getModifiedBlob();
			const fd = new FormData();
			if (blob) {
				fd.append('file', blob, 'receipt.jpg');
			}

			if (activeCard.isSavedReceipt) {
				if (!blob) {
					alert('Пожалуйста, поверните или обрежьте чек перед повторным распознаванием.');
					return;
				}
				// Для сохраненного чека запускаем распознавание синхронно
				const r = await apiFetch('/api/v1/receipts/recognize', {
					method: 'POST',
					body: fd
				});

				const data = await r.json();
				if (!data.success) throw new Error(data.error || 'OCR failed');

				// Заполняем форму распознанными данными
				const f = data.data.parsed || data.parsed;
				if (f) {
					const form = emForm;
					const fieldsMap = {
						merchant_name: f.merchant_name || '',
						merchant_address: f.merchant_address || '',
						receipt_date: f.receipt_date || '',
						receipt_time: f.receipt_time || '',
						subtotal: f.subtotal || '',
						tax: f.tax || '',
						receipt_amount: f.receipt_amount || '',
						payment_method: f.payment_method || '',
						card_last4: f.card_last4 || '',
						receipt_type: f.receipt_type || '',
						currency: f.currency || 'CAD',
						comment: f.comment || '',
						items_json: typeof f.items_json === 'string' ? f.items_json : JSON.stringify(f.items_json || []),
						ocr_text: f.ocr_text || JSON.stringify(f)
					};

					for (const key in fieldsMap) {
						activeCard.data[key] = fieldsMap[key];
						const input = form.querySelector(`[name="${key}"]`);
						if (input) input.value = fieldsMap[key];
					}

					alert('Чек успешно распознан! Проверьте данные и нажмите Сохранить.');
				}
			} else {
				// Для чека из очереди отправляем на фоновое распознавание
				const r = await apiFetch(`/api/v1/receipts/queue/${activeCard.id}/rerun`, {
					method: 'POST',
					body: fd
				});

				const data = await r.json();
				if (!data.success) throw new Error(data.error || 'Rerun failed');

				activeCard.el.classList.add('blurred');
				activeCard.statusText.textContent = 'В очереди (повтор)…';
				activeCard.data._is_mapped = false;
				closeEditModal();
			}
		} catch (err) {
			alert('Ошибка распознавания: ' + (err.message || err));
		} finally {
			emRerun.innerText = oldText;
			emRerun.disabled = false;
		}
	});
}

// Лупа (Lens)
function setupMagnifier(card){
  const box = editModal.querySelector('.preview-box');
  const inner = document.createElement('img');
  inner.src = emImg.src;
  emLens.innerHTML = '';
  emLens.appendChild(inner);
  
  const LENS_SIZE = 300, ZOOM = 4.25;
  emLens.style.width  = LENS_SIZE + 'px';
  emLens.style.height = LENS_SIZE + 'px';
  const clamp = (v,min,max)=>Math.max(min,Math.min(max,v));

  function updateLens(e){
    const rectBox = box.getBoundingClientRect();
    const cx = (e.touches ? e.touches[0].clientX : e.clientX) - rectBox.left;
    const cy = (e.touches ? e.touches[0].clientY : e.clientY) - rectBox.top;

    const nW = emImg.naturalWidth || 1, nH = emImg.naturalHeight || 1;
    const s  = Math.min(box.clientWidth / nW, box.clientHeight / nH);
    const w  = nW * s, h = nH * s;

    const cx0 = rectBox.width / 2, cy0 = rectBox.height / 2;
    const dx = cx - cx0, dy = cy - cy0;

    const turns = card.rotateQuarterTurns || 0;
    const deg   = (turns % 4) * 90;
    const a     = deg * Math.PI / 180;

    const ux =  dx * Math.cos(a) + dy * Math.sin(a);
    const uy = -dx * Math.sin(a) + dy * Math.cos(a);

    let rx = ux + w/2, ry = uy + h/2;
    rx = clamp(rx, 0, w); ry = clamp(ry, 0, h);

    emLens.style.left = (clamp(cx, 0, rectBox.width)  - LENS_SIZE/2) + 'px';
    emLens.style.top  = (clamp(cy, 0, rectBox.height) - LENS_SIZE/2) + 'px';

    inner.style.width  = w + 'px';
    inner.style.height = h + 'px';

    const tx = LENS_SIZE/2 - (ZOOM * ( rx * Math.cos(a) - ry * Math.sin(a) ));
    const ty = LENS_SIZE/2 - (ZOOM * ( rx * Math.sin(a) + ry * Math.cos(a) ));

    inner.style.transform = `translate(${tx}px, ${ty}px) rotate(${deg}deg) scale(${ZOOM})`;
  }
  
  box.onmouseenter = () => {
    if (isCroppingActive) {
      emLens.style.display = 'none';
      return;
    }
    emLens.style.display = 'block';
  };
  box.onmouseleave = () => emLens.style.display = 'none';
  box.onmousemove = (e) => {
    if (isCroppingActive) {
      emLens.style.display = 'none';
      return;
    }
    updateLens(e);
  };
}

/* ========================= JS — Блок 18: сохранение на сервер ========================= */
async function receipt_save(card){
	// Для обычных чеков из очереди проверяем глобальные значения
	if (!card.isSavedReceipt) {
		if (!card.data.receipt_org && globCompany && globCompany.value) {
			card.data.receipt_org = globCompany.value;
		}
		if (!card.data.id_telegram && globEmployee && globEmployee.value) {
			card.data.id_telegram = globEmployee.value;
		}
		if (!card.data.place_id && globObject && globObject.value) {
			card.data.place_id = globObject.value;
		}
	}

	if (!card.data.receipt_org || card.data.receipt_org === '0') {
		alert(__('org_required'));
		return false;
	}
	if (!card.data.id_telegram || card.data.id_telegram === '0') {
		alert(__('emp_required'));
		return false;
	}
	if (!card.data.place_id || card.data.place_id === '0') {
		alert(__('obj_required'));
		return false;
	}

	if (card.statusText) {
		card.statusText.className = 'cc-status text-muted';
		card.statusText.textContent = __('saving');
	}
	if (card.metaEl) {
		card.metaEl.innerHTML = `<span class="cc-status text-muted">${__('saving')}</span>`;
	}
	if (card.btnSave) {
		card.btnSave.disabled = true;
	}

	try{
		let r;
		const blob = await getModifiedBlob();
		if (card.isSavedReceipt) {
			const fd = new FormData();
			fd.append('_method', 'PUT');
			for (const key in card.data) {
				if (card.data[key] !== null && card.data[key] !== undefined) {
					fd.append(key, card.data[key]);
				}
			}
			if (blob) {
				fd.append('file', blob, 'receipt.jpg');
			}
			r = await apiFetch(`/api/v1/receipts/${card.id}`, {
				method: 'POST',
				body: fd
			});
		} else {
			const fd = new FormData();
			for (const key in card.data) {
				if (card.data[key]) {
					fd.append(key, card.data[key]);
				}
			}
			if (blob) {
				fd.append('file', blob, 'receipt.jpg');
			}
			r = await apiFetch(`/api/v1/receipts/queue/${card.id}/save`, {
				method: 'POST',
				body: fd
			});
		}
		
		const data = await r.json();
		if(!data.success) throw new Error(data.error||'Save failed');

		if (card.isSavedReceipt) {
			alert(__('save_success'));
			window.location.reload();
			return true;
		}

		if (card.el) card.el.style.borderColor = 'var(--success-color)';
		if (card.metaEl) card.metaEl.innerHTML = '<span class="cc-status text-success">✓ Сохранено</span>';
		if (card.btnSave) card.btnSave.style.display = 'none';
		
		if (card.el) {
			card.el.classList.add('blurred');
			card.el.style.opacity = '1';
			card.el.style.filter = 'none';
			card.el.style.pointerEvents = 'none';
		}
		
		return true;

	}catch(err){
		const errMsg = err.message || err;
		if (card.el) card.el.style.borderColor = 'var(--danger-color)';
		if (card.metaEl) card.metaEl.innerHTML = `<span class="cc-status text-danger">${__('error')}: ${errMsg}</span>`;
		if (card.btnSave) card.btnSave.disabled = false;
		alert(__('save_error') + errMsg);
		return false;
	}
}

// Запускаем опрос очереди при загрузке скрипта
startPolling();
