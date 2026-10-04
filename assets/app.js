(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
  var rub = function (n) { return Number(n).toLocaleString('ru-RU') + ' ₽'; };

  var products = [];
  var settings = {};
  var current = null;

  var svg = function (d) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' + d + '"/></svg>'; };
  var CHAT = svg('M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z');
  var PHONE = svg('M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.1.37 2.3.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z');
  var ICONS = { wa: CHAT, tg: CHAT, max: CHAT, tel: PHONE };

  function digits(s) { return String(s || '').replace(/\D/g, ''); }

  function links(text) {
    var out = [];
    var msg = encodeURIComponent(text || 'Здравствуйте! Хочу заказать букет.');
    if (digits(settings.whatsapp)) out.push({ k: 'wa', t: 'WhatsApp', u: 'https://wa.me/' + digits(settings.whatsapp) + '?text=' + msg });
    if (settings.telegram) out.push({ k: 'tg', t: 'Telegram', u: 'https://t.me/' + String(settings.telegram).replace(/^@|^https?:\/\/t\.me\//, '') });
    if (settings.max) out.push({ k: 'max', t: 'Max', u: settings.max });
    return out;
  }

  function btn(l, cls) {
    return '<a class="btn ' + cls + '" href="' + esc(l.u) + '" target="_blank" rel="noopener">' + (ICONS[l.k] || '') + esc(l.t) + '</a>';
  }

  function renderContacts() {
    var ls = links();
    var tel = digits(settings.phone);
    $$('.hero__msg').forEach(function (el) { el.innerHTML = ls.map(function (l) { return btn(l, 'btn--ghost'); }).join(''); });
    $$('[data-clarify]').forEach(function (el) {
      var l2 = links('Здравствуйте! Хочу уточнить заказ.');
      el.innerHTML = l2.map(function (l) { return btn(l, 'btn--soft'); }).join('');
    });
    var all = ls.map(function (l) { return btn(l, 'btn--primary'); });
    if (tel) all.push('<a class="btn btn--ghost" href="tel:+' + tel + '">' + ICONS.tel + esc(settings.phone) + '</a>');
    var c = $('[data-contacts]'); if (c) c.innerHTML = all.join('');
    var h = $('[data-hours]'); if (h) h.textContent = settings.hours ? 'Время работы: ' + settings.hours : '';
    var p = $('[data-phone]');
    if (p) { if (tel) { p.textContent = settings.phone; p.href = 'tel:+' + tel; } else p.hidden = true; }
    var fab = $('[data-fab]');
    if (fab && (ls.length || tel)) {
      var f = ls[0] || { u: 'tel:+' + tel, t: 'Позвонить', k: 'tel' };
      fab.innerHTML = btn({ u: f.u, t: 'Написать', k: f.k }, 'btn--primary');
      fab.hidden = false;
    }
  }

  function renderCatalog() {
    var list = products.filter(function (p) { return p.active !== false; });
    var grid = $('#grid');
    if (!list.length) { grid.innerHTML = '<p class="muted">Каталог скоро обновится.</p>'; return; }
    grid.innerHTML = list.map(function (p) {
      return '<article class="card">' +
        '<div class="card__img"><img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" loading="lazy" width="600" height="750"></div>' +
        '<div class="card__body"><h3 class="card__name">' + esc(p.name) + '</h3>' +
        '<p class="card__desc">' + esc(p.desc) + '</p>' +
        '<div class="card__foot"><span class="price">' + rub(p.price) + '</span>' +
        '<button class="btn btn--primary" type="button" data-order="' + esc(p.id) + '">Заказать</button></div></div></article>';
    }).join('');
  }

  /* ---------- форма ---------- */
  var modal = $('#modal'), form = $('#orderForm');
  var sel = $('#product');

  function fillSelect() {
    sel.innerHTML = products.filter(function (p) { return p.active !== false; }).map(function (p) {
      return '<option value="' + esc(p.id) + '">' + esc(p.name) + ' — ' + rub(p.price) + '</option>';
    }).join('');
  }

  function updatePick() {
    var p = products.filter(function (x) { return x.id === sel.value; })[0];
    if (!p) return;
    $('#pickImg').src = p.image; $('#pickImg').alt = p.name;
    $('#pickPrice').textContent = rub(p.price);
    $('#total').textContent = rub(p.price);
  }

  function open(id) {
    fillSelect();
    if (id) sel.value = id;
    updatePick();
    $('#formView').hidden = false; $('#doneView').hidden = true;
    $('#err').hidden = true;
    var d = $('#date'); var t = new Date(); t.setMinutes(t.getMinutes() - t.getTimezoneOffset());
    d.min = t.toISOString().slice(0, 10);
    modal.hidden = false; document.body.classList.add('lock');
    setTimeout(function () { $('#name').focus(); }, 50);
  }
  function close() { modal.hidden = true; document.body.classList.remove('lock'); }

  function maskPhone(el) {
    el.addEventListener('input', function () {
      var d = digits(el.value);
      if (d[0] === '8') d = '7' + d.slice(1);
      if (d[0] !== '7') d = '7' + d;
      d = d.slice(0, 11);
      var s = '+7';
      if (d.length > 1) s += ' (' + d.slice(1, 4);
      if (d.length >= 5) s += ') ' + d.slice(4, 7);
      if (d.length >= 8) s += '-' + d.slice(7, 9);
      if (d.length >= 10) s += '-' + d.slice(9, 11);
      el.value = (el.value.replace(/\D/g, '') === '') ? '' : s;
    });
  }

  function toggleRecipient() {
    var self = $('#self').checked;
    $('#recipientRow').hidden = self;
  }

  function showErr(msg, bad) {
    var e = $('#err'); e.textContent = msg; e.hidden = false;
    $$('.bad').forEach(function (x) { x.classList.remove('bad'); });
    (bad || []).forEach(function (id) { var el = $('#' + id); if (el) el.classList.add('bad'); });
    e.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function submit(ev) {
    ev.preventDefault();
    var v = function (id) { return ($('#' + id).value || '').trim(); };
    var self = $('#self').checked;
    var bad = [];
    if (v('name').length < 2) bad.push('name');
    if (digits(v('phone')).length < 11) bad.push('phone');
    if (!v('date')) bad.push('date');
    if (v('address').length < 5) bad.push('address');
    if (!self) {
      if (v('rname').length < 2) bad.push('rname');
      if (digits(v('rphone')).length < 11) bad.push('rphone');
    }
    if (bad.length) return showErr('Проверьте выделенные поля — они обязательны для заполнения.', bad);

    var data = {
      product: sel.value, name: v('name'), phone: v('phone'), date: v('date'), time: v('time'),
      address: v('address'), self: self,
      rname: self ? v('name') : v('rname'), rphone: self ? v('phone') : v('rphone'),
      anon: $('#anon').checked, card: v('card'), comment: v('comment'),
      pay: (form.querySelector('input[name=pay]:checked') || {}).value || 'online',
      website: form.website.value
    };
    var b = $('#submit'); b.disabled = true; b.textContent = 'Отправляем…';
    $('#err').hidden = true;

    fetch('api/order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().catch(function () { throw new Error('bad'); }); })
      .then(function (res) {
        if (!res.ok) { showErr(res.error || 'Не удалось отправить заказ. Попробуйте ещё раз или напишите нам.', res.fields); return; }
        done(res);
      })
      .catch(function () { showErr('Не удалось отправить заказ. Проверьте интернет или напишите нам в мессенджер.'); })
      .then(function () { b.disabled = false; b.textContent = 'Оформить заказ'; });
  }

  function done(res) {
    $('#formView').hidden = true; $('#doneView').hidden = false;
    var t = 'Номер заказа: ' + res.number + '. ';
    var box = $('#doneBtns'); box.innerHTML = '';
    if (res.paymentUrl) {
      t += 'Сейчас откроется страница оплаты. Если этого не произошло — нажмите кнопку ниже.';
      box.innerHTML = '<a class="btn btn--primary" href="' + esc(res.paymentUrl) + '">Перейти к оплате</a>';
      setTimeout(function () { location.href = res.paymentUrl; }, 1200);
    } else if (res.payPending) {
      t += 'Онлайн-оплата временно недоступна — мы свяжемся с вами и пришлём ссылку на оплату.';
    } else {
      t += 'Мы свяжемся с вами в ближайшее время, чтобы подтвердить детали и согласовать оплату.';
    }
    $('#doneText').textContent = t;
    var ls = links('Здравствуйте! Мой заказ № ' + res.number + '.');
    box.innerHTML += ls.map(function (l) { return btn(l, 'btn--soft'); }).join('');
    form.reset(); toggleRecipient();
  }

  /* ---------- запуск ---------- */
  function load(url) {
    return fetch(url + '?v=' + Date.now()).then(function (r) { if (!r.ok) throw new Error(url); return r.json(); });
  }

  Promise.all([load('data/products.json'), load('data/settings.json').catch(function () { return {}; })])
    .then(function (r) { products = r[0]; settings = r[1] || {}; renderCatalog(); renderContacts(); })
    .catch(function () { $('#grid').innerHTML = '<p class="muted">Не удалось загрузить каталог. Обновите страницу.</p>'; });

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-order]');
    if (o) { open(o.getAttribute('data-order')); return; }
    if (e.target.closest('[data-close]')) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
  sel.addEventListener('change', updatePick);
  $('#self').addEventListener('change', toggleRecipient);
  maskPhone($('#phone')); maskPhone($('#rphone'));
  form.addEventListener('submit', submit);
  $('#year').textContent = new Date().getFullYear();
})();
