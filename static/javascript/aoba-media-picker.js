/*!
 * aoba-media-picker.js — серверная версия (работает у всех посетителей).
 *
 * Над полем прикрепа файла добавляет переключатель «картинка или ролик» / «другое».
 * В первом режиме вместо родного поля показывается отдельное поле с
 * accept="image/*,video/*" (на Android Chrome/Firefox открывается лёгкая шторка
 * Photo Picker), а при отправке файл переносится в настоящее поле #imagefile
 * и уходит вместе с постом. Скрипт ничего не меняет в серверной части.
 *
 * ПОДКЛЮЧЕНИЕ: положить файл, например, в /static/javascript/ и добавить в шаблон
 * страниц с формой (там же, где подключается kusaba.new.js) строку:
 *   <script src="https://aoba.me/static/javascript/aoba-media-picker.js?v=1" defer></script>
 * При обновлении файла увеличивайте ?v=, чтобы сбросить кеш у посетителей.
 */
(function () {
  'use strict';

  // ---- Настройки ----
  // true  — включать только на Android (Photo Picker есть именно там);
  // false — включать у всех.
  var ANDROID_ONLY = true;
  // true — писать ход работы в консоль браузера.
  var DEBUG = false;

  // Защита от двойного подключения.
  if (window.__aobaMediaPicker) return;
  window.__aobaMediaPicker = true;

  function log() {
    if (DEBUG && window.console) {
      console.log.apply(console, ['[aoba-media-picker]'].concat([].slice.call(arguments)));
    }
  }

  if (ANDROID_ONLY) {
    // Признаки Android:
    //  1) «Android» в User-Agent;
    //  2) платформа Android в Client Hints;
    //  3) режим «Версия для ПК» на Android: UA притворяется Linux-десктопом,
    //     но основной указатель — палец и есть касания.
    var ua = navigator.userAgent;
    var uaData = navigator.userAgentData;
    var coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    var isAndroid =
      /Android/i.test(ua) ||
      !!(uaData && uaData.platform === 'Android') ||
      (/Linux|X11/i.test(ua) && !/CrOS/i.test(ua) && coarse && navigator.maxTouchPoints > 0);
    if (!isAndroid) {
      log('не Android, выхожу. UA:', ua);
      return;
    }
  }

  // Несуществующий id формы: элемент с таким form="" не входит ни в одну форму
  // и поэтому НЕ уйдёт на сервер вместе с постом (иначе файл улетел бы дважды).
  const NO_FORM = '__aoba_media_picker_none__';

  const MEDIA_EXT = /\.(jpe?g|png|gif|webp|bmp|avif|apng|svg|heic|heif|mp4|webm|mov|m4v|mkv|ogv|ogg|avi|3gp)$/i;

  function isMedia(file) {
    return /^(image|video)\//.test(file.type) || MEDIA_EXT.test(file.name);
  }

  function init() {
    const form = document.getElementById('postform');
    const real = document.getElementById('imagefile');
    if (!form || !real) return false;
    if (form.dataset.mediaPicker) return true;
    form.dataset.mediaPicker = '1';

    const realLabel = real.closest('label') || real;
    const host = realLabel.parentNode;

    // --- Переключатель (разметка как у родных радиокнопок сайта) ---
    const switcher = document.createElement('div');
    switcher.style.marginBottom = '4px';
    switcher.innerHTML =
      '<label><input class="stradio" type="radio" name="__aoba_kind" form="' + NO_FORM + '" value="media" checked>' +
      '<span> картинка или ролик</span></label> ' +
      '<label><input class="stradio" type="radio" name="__aoba_kind" form="' + NO_FORM + '" value="other">' +
      '<span> другое</span></label>';
    host.insertBefore(switcher, realLabel);

    // --- Поле только для картинок/роликов ---
    // Лежит в shadow DOM, чтобы скрипты сайта (например, voice-recorder.js,
    // который добавляет кнопку «Записать» к каждому input[type=file])
    // не видели его и не дублировали свои кнопки. Из shadow DOM поле
    // и так не входит в форму, так что на сервер само не уйдёт.
    const holder = document.createElement('span');
    const root = holder.attachShadow({ mode: 'open' });

    const mine = document.createElement('input');
    mine.type = 'file';
    mine.accept = 'image/*,video/*';
    mine.style.maxWidth = '100%';
    mine.style.width = '370px';

    const note = document.createElement('span');
    note.style.color = 'red';
    note.style.marginLeft = '6px';

    root.append(mine, note);
    realLabel.insertAdjacentElement('afterend', holder);

    let mode = 'media';

    function setMode(m) {
      mode = m;
      const media = m === 'media';
      realLabel.style.display = media ? 'none' : '';
      holder.style.display = media ? '' : 'none';
      note.textContent = '';
    }

    // Наблюдатель за отправкой: после успешной отправки возвращает «картинка или ролик».
    let watchTimer = null;
    function stopWatch() {
      if (watchTimer) { clearInterval(watchTimer); watchTimer = null; }
    }

    function clearReal() {
      // Очищаем настоящее поле сами и шлём change, чтобы родной скрипт сайта
      // обновил подпись «Файл не выбран». Глобальные функции страницы не нужны.
      try { real.value = ''; } catch (e) { /* ignore */ }
      real.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // При смене режима сбрасываем выбранный файл в обоих полях,
    // чтобы в «другом» не остался файл из «картинки» и наоборот.
    function switchTo(m) {
      stopWatch();
      clearReal();
      mine.value = '';
      const radio = switcher.querySelector('input[value="' + m + '"]');
      if (radio) radio.checked = true;
      setMode(m);
    }

    switcher.addEventListener('change', function (e) {
      if (!e.target || e.target.name !== '__aoba_kind') return;
      switchTo(e.target.value);
    });

    // Нажатие на кнопку «Записать» (voice-recorder.js) автоматически
    // переключает на «другое»: запись — это аудио, а не картинка или ролик.
    // Capture на document срабатывает раньше обработчика самой кнопки.
    document.addEventListener('click', function (e) {
      if (mode !== 'media') return;
      let el = e.target;
      if (!el || typeof el.closest !== 'function') return;
      el = el.closest('button, [role="button"], input[type="button"], a') || el;
      if (!host.contains(el) || switcher.contains(el) || holder.contains(el)) return;
      const text = ((el.value || el.textContent) + '').trim();
      if (text.length < 30 && /запис/i.test(text)) switchTo('other');
    }, true);

    // Кнопка «Очистить» сайта очищает заодно и наше поле.
    document.addEventListener('click', function (e) {
      const t = e.target;
      if (t && t.id === 'fileclear') {
        stopWatch();
        mine.value = '';
        note.textContent = '';
      }
    }, true);

    // Строгая проверка: в режиме «картинка или ролик» другие типы не принимаются
    // (accept на десктопе — лишь подсказка, там можно выбрать «Все файлы»).
    mine.addEventListener('change', function () {
      note.textContent = '';
      const f = mine.files && mine.files[0];
      if (f && !isMedia(f)) {
        mine.value = '';
        note.textContent = 'Это не картинка или ролик — выбери «другое»';
      }
    });

    // --- Перенос файла в настоящее поле ---
    function transfer() {
      if (mode !== 'media') return;
      const f = mine.files && mine.files[0];
      if (!f) return;
      try {
        const dt = new DataTransfer();
        dt.items.add(f);
        real.files = dt.files;
        // чтобы родной скрипт сайта обновил подпись файла
        real.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (err) {
        console.error('[aoba-media-picker] не удалось перенести файл:', err);
      }
    }

    // После отправки ждём признак успеха и возвращаем режим «картинка или ролик».
    // Успех = очистилось поле текста ИЛИ очистилось поле файла (если сайт так делает).
    // Если отправка не удалась (например, не введена капча), поля остаются
    // заполненными, и режим не меняется. Страница перезагрузилась — режим и так
    // по умолчанию «картинка или ролик». Ждём не дольше минуты.
    function watchAfterSend() {
      const msg = document.getElementById('message');
      const hadText = !!(msg && msg.value.trim());
      const hadFile = !!(real.files && real.files.length);
      if (!hadText && !hadFile) return;
      stopWatch();
      let ticks = 0;
      watchTimer = setInterval(function () {
        ticks++;
        const textGone = hadText && msg && msg.value === '';
        const fileGone = hadFile && !(real.files && real.files.length);
        if (textGone || fileGone) {
          switchTo('media'); // внутри останавливает наблюдателя
          log('пост отправлен: режим «картинка или ролик»');
        } else if (ticks > 120) {
          stopWatch();
        }
      }, 500);
    }

    // Capture на document срабатывает раньше inline onsubmit="js_send_postbox()".
    document.addEventListener('submit', function (e) {
      if (e.target !== form) return;
      transfer();
      watchAfterSend();
    }, true);

    // Страховка: если отправка запускается кликом по кнопке.
    const btn = document.getElementById('postform-submit');
    if (btn) btn.addEventListener('click', transfer, true);

    setMode('media');
    log('активен');
    return true;
  }

  var done = false;
  function tryInit() {
    try {
      return init();
    } catch (err) {
      // Любая ошибка не должна ломать страницу — только пишем в консоль.
      if (window.console) console.error('[aoba-media-picker]', err);
      return true; // не пытаться снова
    }
  }

  function start() {
    if (tryInit()) {
      done = true;
    } else {
      // Форма может появиться позже (например, подгружаться динамически).
      var mo = new MutationObserver(function () {
        if (tryInit()) { done = true; mo.disconnect(); }
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
      setTimeout(function () {
        if (!done) log('не нашёл форму #postform / поле #imagefile');
      }, 3000);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
