(function () {
  'use strict';
  var app = document.getElementById('app');
  var CSRF = app.getAttribute('data-csrf');
  var state = { products: [], settings: {}, orders: [], tochka: false, telegram: false, tab: 'products', dirty: false };

  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
  var rub = function (n) { return Number(n).toLocaleString('ru-RU') + ' ₽'; };
  var $ = function (s, r) { return (r || document).querySelector(s); };

  function toast(msg, bad) {
    var t = $('#toast'); t.textContent = msg; t.className = 'toast' + (bad ? ' bad' : ''); t.hidden = false;
    clearTimeout(toast._t); toast._t = setTimeout(function () { t.hidden = true; }, 3200);
  }

  function api(action, body, form) {
    var opt = { method: 'POST', headers: { 'X-CSRF': CSRF }, credentials: 'same-origin' };
    if (form) opt.body = form; else { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body || {}); }
    return fetch('api.php?action=' + action, opt).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Ошибка сервера' }; }).then(function (j) {
        if (r.status === 401) { location.reload(); }
        return j;
      });
    });
  }

  /* ---------- Товары ---------- */
  function productsView() {
    var html = '<h2>Товары</h2>' +
      '<p class="hint">Меняйте название, цену, состав и фото прямо в карточках. Кнопки ↑ ↓ меняют порядок на сайте. ' +
      'Галочка «Показывать» скрывает товар с сайта, не удаляя его. Изменения попадут на сайт после нажатия <b>«Сохранить»</b>.</p>' +
      '<div class="bar"><button class="btn btn--soft" id="add" type="button">+ Добавить товар</button></div><div class="plist">';
    state.products.forEach(function (p, i) {
      html += '<div class="prow' + (p.active === false ? ' off' : '') + '" data-i="' + i + '">' +
        '<div class="prow__img"><img src="../' + esc(p.image || '') + '" alt="" onerror="this.style.visibility=\'hidden\'">' +
        '<label class="up">Заменить фото<input type="file" accept="image/jpeg,image/png,image/webp" data-photo></label></div>' +
        '<div class="prow__f">' +
        '<div><label>Название</label><input data-k="name" value="' + esc(p.name) + '" maxlength="120"></div>' +
        '<div><label>Цена, ₽</label><input data-k="price" type="number" min="1" step="1" inputmode="numeric" value="' + esc(p.price) + '"></div>' +
        '<div class="full"><label>Состав / краткое описание</label><input data-k="desc" value="' + esc(p.desc) + '" maxlength="300"></div>' +
        '<div class="prow__act">' +
        '<label class="check" style="margin:0"><input type="checkbox" data-k="active"' + (p.active !== false ? ' checked' : '') + '> Показывать на сайте</label>' +
        '<span style="flex:1"></span>' +
        '<button class="mini" data-mv="-1" type="button" aria-label="Выше"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
        '<button class="mini" data-mv="1" type="button" aria-label="Ниже"' + (i === state.products.length - 1 ? ' disabled' : '') + '>↓</button>' +
        '<button class="mini danger" data-del type="button">Удалить</button>' +
        '</div></div></div>';
    });
    html += '</div><div class="savebar"><span id="dirty">' + (state.dirty ? 'Есть несохранённые изменения' : 'Все изменения сохранены') + '</span>' +
      '<button class="btn btn--primary" id="saveP" type="button">Сохранить</button></div>';
    app.innerHTML = html;
  }

  function markDirty() { state.dirty = true; var d = $('#dirty'); if (d) d.textContent = 'Есть несохранённые изменения'; }

  function bindProducts() {
    app.addEventListener('input', function (e) {
      var row = e.target.closest('.prow'); if (!row || state.tab !== 'products') return;
      var k = e.target.getAttribute('data-k'); if (!k) return;
      var p = state.products[+row.getAttribute('data-i')];
      if (k === 'active') { p.active = e.target.checked; row.classList.toggle('off', !p.active); }
      else p[k] = k === 'price' ? e.target.value : e.target.value;
      markDirty();
    });
    app.addEventListener('change', function (e) {
      if (!e.target.hasAttribute('data-photo')) return;
      var row = e.target.closest('.prow'); var p = state.products[+row.getAttribute('data-i')];
      var f = e.target.files[0]; if (!f) return;
      var fd = new FormData(); fd.append('photo', f);
      toast('Загружаем фото…');
      api('upload', null, fd).then(function (j) {
        if (!j.ok) return toast(j.error || 'Ошибка загрузки', true);
        p.image = j.image; row.querySelector('img').src = '../' + j.image; row.querySelector('img').style.visibility = '';
        markDirty(); toast('Фото загружено. Нажмите «Сохранить».');
      });
    });
    app.addEventListener('click', function (e) {
      if (state.tab !== 'products') return;
      var t = e.target;
      if (t.id === 'add') {
        state.products.unshift({ id: '', name: 'Новый букет', price: 3000, desc: '', image: '', active: true });
        markDirty(); productsView(); window.scrollTo(0, 0); return;
      }
      if (t.id === 'saveP') return saveProducts();
      var row = t.closest('.prow'); if (!row) return;
      var i = +row.getAttribute('data-i');
      if (t.hasAttribute('data-mv')) {
        var j = i + (+t.getAttribute('data-mv'));
        if (j >= 0 && j < state.products.length) { var x = state.products[i]; state.products[i] = state.products[j]; state.products[j] = x; markDirty(); productsView(); }
      } else if (t.hasAttribute('data-del')) {
        if (confirm('Удалить «' + state.products[i].name + '» из каталога?')) { state.products.splice(i, 1); markDirty(); productsView(); }
      }
    });
  }

  function saveProducts() {
    var b = $('#saveP'); b.disabled = true;
    api('save_products', { products: state.products }).then(function (j) {
      b.disabled = false;
      if (!j.ok) return toast(j.error || 'Не удалось сохранить', true);
      state.products = j.products; state.dirty = false; productsView(); toast('Сохранено — изменения уже на сайте ✓');
    });
  }

  /* ---------- Заказы ---------- */
  var ST = { new: 'Новый', awaiting_payment: 'Ждём оплату', pay_link_failed: 'Нет ссылки на оплату', paid: 'Оплачен', done: 'Выполнен', cancelled: 'Отменён' };
  function ordersView() {
    var html = '<h2>Заказы</h2>';
    if (!state.tochka) html += '<p class="hint">Онлайн-оплата Точка Банка ещё не подключена (нет токена в <b>private/config.php</b>). Заказы всё равно приходят сюда, оплата — по договорённости.</p>';
    if (!state.orders.length) html += '<p class="muted">Заказов пока нет.</p>';
    html += '<div class="tbl">';
    state.orders.forEach(function (o) {
      html += '<div class="ord" data-n="' + esc(o.number) + '"><div class="ord__h"><b>№' + esc(o.number) + ' · ' + esc(o.product_name) + ' · ' + rub(o.amount) + '</b>' +
        '<span class="badge ' + esc(o.status) + '">' + esc(ST[o.status] || o.status) + '</span></div>' +
        '<div>📅 ' + esc(o.date) + (o.time ? ', ' + esc(o.time) : '') + ' &nbsp; 📍 ' + esc(o.address) + '</div>' +
        '<div>👤 ' + esc(o.name) + ', <a href="tel:' + esc(o.phone) + '">' + esc(o.phone) + '</a></div>' +
        '<div>🎁 ' + esc(o.rname) + ', <a href="tel:' + esc(o.rphone) + '">' + esc(o.rphone) + '</a>' + (o.anon ? ' · анонимно' : '') + '</div>' +
        (o.card ? '<div>💌 ' + esc(o.card) + '</div>' : '') + (o.comment ? '<div>💬 ' + esc(o.comment) + '</div>' : '') +
        '<div class="muted">' + esc(new Date(o.created).toLocaleString('ru-RU')) + ' · оплата: ' + (o.pay_method === 'online' ? 'онлайн' : 'по договорённости') + '</div>' +
        '<div class="acts">' +
        (o.payment_id ? '<button class="mini" data-chk type="button">Проверить оплату</button>' : '') +
        '<button class="mini" data-st="paid" type="button">Оплачен</button>' +
        '<button class="mini" data-st="done" type="button">Выполнен</button>' +
        '<button class="mini danger" data-st="cancelled" type="button">Отменить</button></div></div>';
    });
    app.innerHTML = html + '</div>';
  }
  function bindOrders() {
    app.addEventListener('click', function (e) {
      if (state.tab !== 'orders') return;
      var box = e.target.closest('.ord'); if (!box) return;
      var n = box.getAttribute('data-n');
      if (e.target.hasAttribute('data-st')) {
        api('order_status', { number: n, status: e.target.getAttribute('data-st') }).then(function (j) {
          if (!j.ok) return toast(j.error, true);
          state.orders.forEach(function (o) { if (o.number === n) o.status = e.target.getAttribute('data-st'); }); ordersView();
        });
      } else if (e.target.hasAttribute('data-chk')) {
        toast('Проверяем…');
        api('check_payment', { number: n }).then(function (j) {
          if (!j.ok) return toast(j.error, true);
          state.orders.forEach(function (o) { if (o.number === n) o.status = j.order_status; }); ordersView();
          toast('Статус в банке: ' + j.status);
        });
      }
    });
  }

  /* ---------- Контакты ---------- */
  function settingsView() {
    var s = state.settings;
    app.innerHTML = '<h2>Контакты</h2><p class="hint">Эти данные используются в кнопках связи на сайте. Пустое поле — кнопка не показывается.</p>' +
      '<div class="panel">' +
      '<div class="f"><label>Телефон (как показывать)</label><input id="s_phone" value="' + esc(s.phone) + '" placeholder="+7 (900) 000-00-00"></div>' +
      '<div class="f"><label>WhatsApp — номер цифрами</label><input id="s_whatsapp" value="' + esc(s.whatsapp) + '" placeholder="79001234567" inputmode="numeric"></div>' +
      '<div class="f"><label>Telegram — имя пользователя</label><input id="s_telegram" value="' + esc(s.telegram) + '" placeholder="username (без @)"></div>' +
      '<div class="f"><label>Max — ссылка на профиль/чат</label><input id="s_max" value="' + esc(s.max) + '" placeholder="https://max.ru/..."></div>' +
      '<div class="f"><label>Время работы</label><input id="s_hours" value="' + esc(s.hours) + '" placeholder="ежедневно, 9:00–21:00"></div>' +
      '<button class="btn btn--primary" id="saveS" type="button">Сохранить</button>' +
      '<p class="muted" style="margin-top:14px">Уведомления в Telegram: ' + (state.telegram ? 'подключены ✓' : 'не подключены (см. private/config.php)') + '. Онлайн-оплата: ' + (state.tochka ? 'подключена ✓' : 'не подключена') + '.</p></div>';
  }
  function bindSettings() {
    app.addEventListener('click', function (e) {
      if (e.target.id !== 'saveS') return;
      var g = function (id) { return $('#s_' + id).value; };
      var s = { phone: g('phone'), whatsapp: g('whatsapp'), telegram: g('telegram'), max: g('max'), hours: g('hours') };
      api('save_settings', { settings: s }).then(function (j) {
        if (!j.ok) return toast(j.error, true);
        state.settings = s; toast('Сохранено ✓');
      });
    });
  }

  /* ---------- Пароль ---------- */
  function accountView() {
    app.innerHTML = '<h2>Смена пароля</h2><div class="panel">' +
      '<div class="f"><label>Текущий пароль</label><input id="pw0" type="password" autocomplete="current-password"></div>' +
      '<div class="f"><label>Новый пароль (не короче 8 символов)</label><input id="pw1" type="password" autocomplete="new-password"></div>' +
      '<button class="btn btn--primary" id="savePw" type="button">Сменить пароль</button></div>';
  }
  function bindAccount() {
    app.addEventListener('click', function (e) {
      if (e.target.id !== 'savePw') return;
      api('change_password', { current: $('#pw0').value, password: $('#pw1').value }).then(function (j) {
        if (!j.ok) return toast(j.error, true);
        $('#pw0').value = ''; $('#pw1').value = ''; toast('Пароль изменён ✓');
      });
    });
  }

  /* ---------- навигация ---------- */
  function show(tab) {
    if (state.dirty && state.tab === 'products' && tab !== 'products' && !confirm('Есть несохранённые изменения в товарах. Перейти без сохранения?')) return;
    if (state.tab === 'products' && tab !== 'products') state.dirty = false;
    state.tab = tab;
    Array.prototype.forEach.call(document.querySelectorAll('#tabs button'), function (b) { b.classList.toggle('on', b.getAttribute('data-tab') === tab); });
    ({ products: productsView, orders: ordersView, settings: settingsView, account: accountView })[tab]();
  }
  $('#tabs').addEventListener('click', function (e) { var t = e.target.getAttribute('data-tab'); if (t) show(t); });
  window.addEventListener('beforeunload', function (e) { if (state.dirty) { e.preventDefault(); e.returnValue = ''; } });

  bindProducts(); bindOrders(); bindSettings(); bindAccount();
  api('load').then(function (j) {
    if (!j.ok) { app.innerHTML = '<p class="err">' + esc(j.error || 'Ошибка загрузки') + '</p>'; return; }
    state.products = j.products; state.settings = j.settings; state.orders = j.orders; state.tochka = j.tochka; state.telegram = j.telegram;
    show('products');
  });
})();
