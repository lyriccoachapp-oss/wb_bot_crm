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
const emForm = document.getElementById('emForm');

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
    const reqOpts = { headers: { 'Authorization': 'Bearer ' + localStorage.getItem('access_token'), 'Accept': 'application/json' } };
    const eFetch = await fetch('/api/v1/references/employees', reqOpts);
    const oFetch = await fetch('/api/v1/references/objects', reqOpts);
    const cFetch = await fetch('/api/v1/references/companies', reqOpts);
    
    if (eFetch.status === 401 || oFetch.status === 401 || cFetch.status === 401) {
        window.location.href = '/?route=logout';
        return;
    }

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

        const r = await fetch('/api/v1/receipts/queue', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('access_token'),
                'Accept': 'application/json'
            },
            body: fd
        });

        if (r.status === 401) { window.location.href = '/?route=logout'; return; }
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
    }
}

function startPolling() {
    pollQueue();
    pollInterval = setInterval(pollQueue, 3000);
}

async function pollQueue() {
    try {
        const r = await fetch('/api/v1/receipts/queue', {
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('access_token'),
                'Accept': 'application/json'
            }
        });
        if (r.status === 401) { window.location.href = '/?route=logout'; return; }
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
        const r = await fetch(`/api/v1/receipts/queue/${card.id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('access_token'),
                'Accept': 'application/json'
            }
        });
        if (r.status === 401) { window.location.href = '/?route=logout'; return; }
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

function openEditModal(card) {
    activeCard = card;
    
    // Подготовка картинки
    emImg.src = card.url;
    emImg.style.transform = `rotate(${card.rotateQuarterTurns * 90}deg)`;
    
    // Заполнение формы
    const form = emForm;
    for (const key in card.data) {
        const input = form.querySelector(`[name="${key}"]`);
        if (input) input.value = card.data[key];
    }
    
    setupMagnifier(card);
    editModal.classList.add('open');
}

function closeEditModal() {
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
            alert('Ошибка: Пожалуйста, выберите Компанию перед сохранением.');
            return;
        }
        if (!activeCard.data.id_telegram || activeCard.data.id_telegram === '0') {
            alert('Ошибка: Пожалуйста, выберите Сотрудника перед сохранением.');
            return;
        }
        if (!activeCard.data.place_id || activeCard.data.place_id === '0') {
            alert('Ошибка: Пожалуйста, выберите Объект перед сохранением.');
            return;
        }
        
        const oldText = emSave.innerText;
        emSave.innerText = 'Сохранение...';
        emSave.disabled = true;

        const success = await receipt_save(activeCard);
        
        emSave.innerText = oldText;
        emSave.disabled = false;

        if (success) {
            closeEditModal();
        }
    });
}

if (emRotate) {
    emRotate.addEventListener('click', () => {
        if (!activeCard) return;
        activeCard.rotateQuarterTurns = (activeCard.rotateQuarterTurns + 1) % 4;
        emImg.style.transform = `rotate(${activeCard.rotateQuarterTurns * 90}deg)`;
    });
}

if (emRerun) {
    emRerun.addEventListener('click', () => {
        if (!activeCard) return;
        closeEditModal();
        activeCard.el.classList.add('blurred');
        activeCard.statusText.textContent = 'В очереди (повтор)…';
        enqueue(activeCard);
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
  
  box.onmouseenter = () => emLens.style.display = 'block';
  box.onmouseleave = () => emLens.style.display = 'none';
  box.onmousemove = updateLens;
}

/* ========================= JS — Блок 18: сохранение на сервер ========================= */
async function receipt_save(card){
  // Check if missing, and if global has value, use it (for quick save)
  if (!card.data.receipt_org && globCompany && globCompany.value) {
      card.data.receipt_org = globCompany.value;
  }
  if (!card.data.id_telegram && globEmployee && globEmployee.value) {
      card.data.id_telegram = globEmployee.value;
  }
  if (!card.data.place_id && globObject && globObject.value) {
      card.data.place_id = globObject.value;
  }

  if (!card.data.receipt_org || card.data.receipt_org === '0') {
      alert('Ошибка: Не выбрана компания. Выберите её сверху или откройте чек для редактирования.');
      return false;
  }
  if (!card.data.id_telegram || card.data.id_telegram === '0') {
      alert('Ошибка: Не выбран сотрудник. Выберите его сверху или откройте чек для редактирования.');
      return false;
  }
  if (!card.data.place_id || card.data.place_id === '0') {
      alert('Ошибка: Не выбран объект. Выберите его сверху или откройте чек для редактирования.');
      return false;
  }

  card.statusText.className='cc-status text-muted';
  card.metaEl.innerHTML = '<span class="cc-status text-muted">Сохранение…</span>';
  card.btnSave.disabled = true;

  try{
    const fd = new FormData();
    
    for (const key in card.data) {
        if (card.data[key]) {
            fd.append(key, card.data[key]);
        }
    }

    const r = await fetch(`/api/v1/receipts/queue/${card.id}/save`, {
      method:'POST',
      headers:{ 
        'Authorization': 'Bearer ' + localStorage.getItem('access_token'),
        'Accept': 'application/json'
      },
      body: fd
    });
    
    if (r.status === 401) { window.location.href = '/?route=logout'; return false; }
    
    const data = await r.json();
    if(!data.success) throw new Error(data.error||'Save failed');

    card.el.style.borderColor = 'var(--success-color)';
    card.metaEl.innerHTML = '<span class="cc-status text-success">✓ Сохранено</span>';
    card.btnSave.style.display = 'none';
    // Disable click to open modal
    card.el.classList.add('blurred');
    card.el.style.opacity = '1';
    card.el.style.filter = 'none';
    card.el.style.pointerEvents = 'none';
    
    return true;

  }catch(err){
    card.el.style.borderColor = 'var(--danger-color)';
    const errMsg = err.message || err;
    card.metaEl.innerHTML = '<span class="cc-status text-danger">Ошибка: ' + errMsg + '</span>';
    card.btnSave.disabled = false;
    alert('Ошибка при сохранении: ' + errMsg);
    return false;
  }
}

// Запускаем опрос очереди при загрузке скрипта
startPolling();
