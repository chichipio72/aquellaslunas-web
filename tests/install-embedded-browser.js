const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync(__dirname + '/../assets/js/install-prompt.js', 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert((source.match(/function runInstallAction\(/g) || []).length === 1, 'Hay más de una función central de instalación.');
assert((source.match(/\n\s+runInstallAction\(event, trigger\);/g) || []).length === 1, 'Los triggers no convergen en un único handler.');

function storage(initial = {}) {
  const values = { ...initial };
  return {
    getItem: (key) => Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null,
    setItem: (key, value) => { values[key] = String(value); },
    removeItem: (key) => { delete values[key]; },
    values,
  };
}

function element(dataset = {}) {
  const queryMap = {};
  const item = {
    dataset,
    hidden: true,
    disabled: false,
    open: false,
    textContent: '',
    attributes: {},
    eventHandlers: {},
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, handler) { this.eventHandlers[name] = handler; },
    querySelector(selector) { return queryMap[selector] || null; },
    closest() { return null; },
    showModal() { this.open = true; },
    close() { this.open = false; },
  };
  Object.defineProperty(item, 'innerHTML', {
    get() { return this._innerHTML || ''; },
    set(value) {
      this._innerHTML = value;
      if (value.includes('data-install-intent-title')) {
        queryMap['[data-install-intent-title]'] = element();
        queryMap['[data-install-intent-copy]'] = element();
        queryMap['[data-install-intent-status]'] = element();
        queryMap['[data-install-intent-action]'] = element();
      }
      if (value.includes('data-install-embedded-title')) {
        queryMap['[data-install-embedded-title]'] = element();
        queryMap['[data-install-embedded-copy]'] = element();
        queryMap['[data-install-embedded-help]'] = element();
        queryMap['[data-install-external]'] = element();
      }
    },
  });
  return item;
}

function load(userAgent, { standalone = false, href = 'https://example.test/astro/index.php?from=instagram#section', localStorageInitial = {} } = {}) {
  const card = element();
  const action = element({ installSource: 'home_card' });
  const secondary = element();
  const help = element();
  const helpToggle = element();
  const status = element();
  const title = element();
  const copy = element();
  const menuTrigger = element({ installSource: 'menu' });
  menuTrigger.textContent = 'Instalar Aquellas Lunas';
  const selectors = {
    '[data-install-card]': card,
    '[data-install-action]': action,
    '[data-install-secondary]': secondary,
    '[data-install-help]': help,
    '[data-install-help-toggle]': helpToggle,
    '[data-install-status]': status,
    '[data-install-title]': title,
    '[data-install-copy]': copy,
  };
  let readyHandler = null;
  let clickHandler = null;
  const windowHandlers = {};
  const timers = [];
  const dynamicSelectors = {};
  const session = storage();
  const context = {
    URL,
    navigator: { userAgent, platform: '', maxTouchPoints: 0, standalone: standalone || undefined },
    document: {
      readyState: 'loading',
      querySelector: (selector) => dynamicSelectors[selector] || selectors[selector] || null,
      querySelectorAll: (selector) => selector === '[data-install-trigger]' ? [menuTrigger, action] : [],
      addEventListener(name, handler) {
        if (name === 'DOMContentLoaded') readyHandler = handler;
        if (name === 'click') clickHandler = handler;
      },
      createElement: () => element(),
      body: {
        appendChild(child) {
          if (child.dataset.installIntentDialog !== undefined) dynamicSelectors['[data-install-intent-dialog]'] = child;
          if (child.dataset.installEmbeddedNotice !== undefined) dynamicSelectors['[data-install-embedded-notice]'] = child;
        },
      },
    },
    window: {
      location: { href },
      localStorage: storage(localStorageInitial),
      sessionStorage: session,
      navigator: null,
      matchMedia: () => ({ matches: standalone }),
      history: {
        state: null,
        cleanUrl: null,
        replaceState(state, title, url) { this.cleanUrl = url; },
      },
      setTimeout(handler) { timers.push(handler); return timers.length; },
      clearTimeout() {},
      addEventListener(name, handler) { windowHandlers[name] = handler; },
    },
    console,
    setTimeout,
  };
  context.window.navigator = context.navigator;
  menuTrigger.parentElement = context.document.body;
  vm.runInNewContext(source, context);
  readyHandler();
  return { context, card, action, help, title, copy, menuTrigger, session, clickHandler, windowHandlers, timers, dynamicSelectors };
}

const chrome = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36');
assert(chrome.context.window.aquellasLunasInstallContext.getEmbeddedBrowserContext().embedded === false, 'Chrome Android fue detectado como embebido.');
assert(chrome.context.window.aquellasLunasInstallContext.getInstallExperienceState().kind === 'shortcut', 'Chrome Android sin prompt no resolvió el estado shortcut.');
assert(chrome.action.hidden === false && chrome.menuTrigger.hidden === false, 'Chrome Android ocultó el fallback de acceso directo.');
assert(chrome.action.textContent === 'Agregar acceso directo' && chrome.menuTrigger.textContent === 'Agregar acceso directo', 'Las superficies no comparten la etiqueta de acceso directo.');

const instagramAndroid = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36 Instagram 335.0.0.0.0 Android');
const instagramContext = instagramAndroid.context.window.aquellasLunasInstallContext.getEmbeddedBrowserContext();
assert(instagramContext.provider === 'instagram' && instagramContext.isAndroid, 'Instagram Android no fue detectado.');
assert(instagramAndroid.action.textContent === 'Abrir en el navegador', 'Instagram Android conserva un falso botón Instalar.');
assert(instagramAndroid.copy.textContent.includes('Instagram') && instagramAndroid.help.innerHTML.includes('Abrir en navegador'), 'Instagram Android no explica el fallback.');
const intentUrl = instagramAndroid.context.window.aquellasLunasInstallContext.addInstallIntent(new URL('https://example.test/astro/?x=1#fragment'));
const intent = instagramAndroid.context.window.aquellasLunasInstallContext.androidChromeIntent(intentUrl);
assert(intent.includes('package=com.android.chrome') && intent.includes('example.test/astro/?x=1&install=1') && !intent.includes('fragment'), 'El intent Android no conserva la URL ni agrega install=1 de forma segura.');
const instagramActionTarget = { closest: (selector) => selector === '[data-install-trigger]' ? instagramAndroid.action : null };
instagramAndroid.clickHandler({ target: instagramActionTarget, preventDefault() {} });
assert(instagramAndroid.context.window.location.href.includes('from=instagram&install=1'), 'Instagram Android no agregó install=1 al abrir Chrome.');

const instagramAndroidMenu = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36 Instagram 335.0.0.0.0 Android');
const instagramMenuTarget = { closest: (selector) => selector === '[data-install-trigger]' ? instagramAndroidMenu.menuTrigger : null };
instagramAndroidMenu.clickHandler({ target: instagramMenuTarget, preventDefault() {} });
assert(instagramAndroidMenu.context.window.location.href.includes('from=instagram&install=1'), 'El menú de Instagram Android no usa el mismo salto con install=1.');

const facebookAndroid = load('Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 [FBAN/FB4A;FBAV/470.0.0.0.0;]');
assert(facebookAndroid.context.window.aquellasLunasInstallContext.getEmbeddedBrowserContext().provider === 'facebook', 'Facebook Android no fue detectado.');

const instagramIOS = load('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Instagram 335.0.0.0.0');
assert(instagramIOS.action.textContent === 'Cómo abrir en Safari', 'Instagram iOS intenta instalar o abrir Safari automáticamente.');
assert(instagramIOS.help.innerHTML.includes('Safari'), 'Instagram iOS no muestra instrucciones específicas.');

const safari = load('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1');
assert(safari.context.window.aquellasLunasInstallContext.getEmbeddedBrowserContext().embedded === false, 'Safari normal fue detectado como embebido.');
assert(safari.action.textContent === 'Agregar a pantalla de inicio', 'Safari perdió su flujo normal.');
assert(safari.menuTrigger.textContent === safari.action.textContent, 'iOS no usa la misma acción en todas las superficies.');
const safariMenuTarget = { closest: (selector) => selector === '[data-install-trigger]' ? safari.menuTrigger : null };
safari.card.hidden = true;
safari.clickHandler({ target: safariMenuTarget, preventDefault() {} });
const safariMenuNotice = safari.dynamicSelectors['[data-install-embedded-notice]'];
assert(safariMenuNotice && safariMenuNotice.hidden === false, 'El menú de iOS no mostró instrucciones cuando la tarjeta estaba oculta.');
assert(safariMenuNotice.querySelector('[data-install-embedded-help]').textContent.includes('Agregar a pantalla de inicio'), 'Las instrucciones del menú de iOS no son las correctas.');

const installed = load('Mozilla/5.0 (Linux; Android 14) Instagram 335.0.0.0.0', { standalone: true });
assert(installed.card.hidden === true, 'La PWA instalada conserva una invitación de instalación.');
assert(installed.menuTrigger.hidden === true, 'La PWA instalada conserva Instalar en el menú.');
const installedHref = installed.context.window.location.href;
installed.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? installed.menuTrigger : null}, preventDefault() {}});
assert(installed.context.window.location.href === installedHref, 'Un trigger residual intentó instalar la PWA ya instalada.');
const installedWithIntent = load(
  'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36',
  { standalone: true, href: 'https://example.test/astro/?install=1' }
);
assert(!installedWithIntent.dynamicSelectors['[data-install-intent-dialog]'], 'La PWA instalada mostró el modal install=1.');
assert(installedWithIntent.context.window.history.cleanUrl === '/astro/', 'La PWA instalada no limpió install=1.');

const chromeIntent = load(
  'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36',
  { href: 'https://example.test/astro/cielo.php?foo=bar&install=1#hora' }
);
assert(chromeIntent.context.window.aquellasLunasInstallContext.hasInstallIntent() === true, 'Chrome no detectó install=1.');
assert(chromeIntent.timers.length === 1, 'La intención no quedó esperando beforeinstallprompt.');
assert(!chromeIntent.dynamicSelectors['[data-install-intent-dialog]'], 'Se mostró el modal antes de beforeinstallprompt.');
let promptCalls = 0;
const nativePrompt = {
  preventDefault() {},
  prompt() { promptCalls += 1; },
  userChoice: Promise.resolve({ outcome: 'dismissed' }),
};
chromeIntent.windowHandlers.beforeinstallprompt(nativePrompt);
const intentDialog = chromeIntent.dynamicSelectors['[data-install-intent-dialog]'];
const intentAction = intentDialog.querySelector('[data-install-intent-action]');
assert(intentDialog.open && intentAction.hidden === false && intentAction.disabled === false, 'beforeinstallprompt no habilitó el modal de instalación.');
const intentActionTarget = { closest: (selector) => selector === '[data-install-intent-action]' ? intentActionTarget : null };
chromeIntent.clickHandler({ target: intentActionTarget, preventDefault() {} });

const normalWithoutIntent = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36');
assert(normalWithoutIntent.timers.length === 0 && !normalWithoutIntent.dynamicSelectors['[data-install-intent-dialog]'], 'Chrome normal activó el flujo install=1.');
normalWithoutIntent.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? normalWithoutIntent.menuTrigger : null}, preventDefault() {}});
const shortcutNotice = normalWithoutIntent.dynamicSelectors['[data-install-embedded-notice]'];
assert(shortcutNotice && shortcutNotice.querySelector('[data-install-embedded-title]').textContent === 'Agregar un acceso directo', 'Chrome sin prompt no mostró instrucciones explícitas de acceso directo.');

const chromeCard = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36');
let cardPromptCalls = 0;
chromeCard.windowHandlers.beforeinstallprompt({preventDefault() {}, prompt() { cardPromptCalls += 1; }, userChoice: Promise.resolve({outcome: 'dismissed'})});
assert(chromeCard.action.hidden === false && chromeCard.action.textContent === 'Instalar aplicación', 'beforeinstallprompt no mostró la tarjeta como instalación real.');
chromeCard.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? chromeCard.action : null}, preventDefault() {}});
assert(cardPromptCalls === 1, 'Chrome normal no abrió el prompt desde la tarjeta.');

const chromeMenu = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36');
let menuPromptCalls = 0;
chromeMenu.windowHandlers.beforeinstallprompt({preventDefault() {}, prompt() { menuPromptCalls += 1; }, userChoice: Promise.resolve({outcome: 'dismissed'})});
assert(chromeMenu.menuTrigger.hidden === false && chromeMenu.menuTrigger.textContent === 'Instalar aplicación', 'beforeinstallprompt no mostró la instalación del menú.');
chromeMenu.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? chromeMenu.menuTrigger : null}, preventDefault() {}});
assert(menuPromptCalls === 1, 'Chrome normal no abrió el prompt desde el menú.');

const chromeAccepted = load('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36');
let acceptedPromptCalls = 0;
chromeAccepted.windowHandlers.beforeinstallprompt({preventDefault() {}, prompt() { acceptedPromptCalls += 1; }, userChoice: Promise.resolve({outcome: 'accepted'})});
chromeAccepted.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? chromeAccepted.menuTrigger : null}, preventDefault() {}});

const desktopChrome = load('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/125.0 Safari/537.36');
assert(desktopChrome.context.window.aquellasLunasInstallContext.getInstallExperienceState().kind === 'shortcut'
  && desktopChrome.menuTrigger.textContent === 'Agregar acceso directo', 'Chrome de escritorio no resolvió el fallback shortcut.');
const desktopFirefox = load('Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0');
assert(desktopFirefox.context.window.aquellasLunasInstallContext.getInstallExperienceState().kind === 'unavailable'
  && desktopFirefox.menuTrigger.hidden === true, 'Un escritorio sin mecanismo conocido mostró un CTA falso.');

const dismissedCard = load(
  'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36',
  {localStorageInitial: {aquellasLunasInstallCardDismissedUntil: String(Date.now() + 30 * 24 * 60 * 60 * 1000)}}
);
assert(dismissedCard.card.hidden === true, 'La preferencia de 30 días no ocultó la tarjeta.');
let dismissedMenuPromptCalls = 0;
dismissedCard.windowHandlers.beforeinstallprompt({preventDefault() {}, prompt() { dismissedMenuPromptCalls += 1; }, userChoice: Promise.resolve({outcome: 'dismissed'})});
dismissedCard.clickHandler({target: {closest: (selector) => selector === '[data-install-trigger]' ? dismissedCard.menuTrigger : null}, preventDefault() {}});
assert(dismissedMenuPromptCalls === 1, 'Ocultar la tarjeta bloqueó la instalación desde el menú.');

const safariWithIntent = load(
  'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1',
  { href: 'https://example.test/astro/?install=1' }
);
assert(safariWithIntent.timers.length === 0 && !safariWithIntent.dynamicSelectors['[data-install-intent-dialog]'], 'Safari/iOS usó el flujo beforeinstallprompt de Android.');

const unavailableIntent = load(
  'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36',
  { href: 'https://example.test/astro/?install=1' }
);
unavailableIntent.timers[0]();
const unavailableDialog = unavailableIntent.dynamicSelectors['[data-install-intent-dialog]'];
assert(unavailableDialog.open, 'La falta de beforeinstallprompt no mostró una explicación.');
assert(unavailableDialog.querySelector('[data-install-intent-action]').hidden === true, 'La explicación dejó un botón Instalar roto.');
assert(unavailableIntent.context.window.history.cleanUrl === '/astro/', 'El estado no disponible no limpió install=1.');

const installedEventIntent = load(
  'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/125.0 Mobile Safari/537.36',
  { href: 'https://example.test/astro/?install=1' }
);
installedEventIntent.windowHandlers.beforeinstallprompt({ preventDefault() {}, prompt() {}, userChoice: Promise.resolve({ outcome: 'accepted' }) });
const installedEventDialog = installedEventIntent.dynamicSelectors['[data-install-intent-dialog]'];
installedEventIntent.windowHandlers.appinstalled();
assert(installedEventDialog.open === false, 'appinstalled no cerró el modal.');
assert(installedEventIntent.context.window.history.cleanUrl === '/astro/', 'appinstalled no limpió install=1.');

const dismissTarget = {
  dataset: { installSource: 'home_card' },
  closest(selector) { return selector === '[data-install-dismiss]' ? this : null; },
};
instagramAndroid.clickHandler({ target: dismissTarget, preventDefault() {} });
assert(instagramAndroid.card.hidden === true, 'Cerrar no ocultó el aviso embebido.');
assert(instagramAndroid.session.values.aquellasLunasEmbeddedInstallNoticeDismissed === '1', 'El descarte no se conservó durante la navegación.');

setImmediate(() => {
  assert(promptCalls === 1, 'El botón Instalar no abrió exactamente una vez el prompt nativo.');
  assert(intentDialog.open === false, 'Cancelar no cerró el modal.');
  assert(chromeIntent.context.window.history.cleanUrl === '/astro/cielo.php?foo=bar#hora', 'Cancelar no limpió install=1 o alteró la URL.');
  assert(chromeCard.action.hidden === false && chromeCard.action.textContent === 'Agregar acceso directo', 'Cancelar no cambió coherentemente al fallback shortcut.');
  assert(chromeMenu.menuTrigger.hidden === false && chromeMenu.menuTrigger.textContent === 'Agregar acceso directo', 'El menú no cambió al mismo fallback tras cancelar.');
  assert(acceptedPromptCalls === 1 && chromeAccepted.menuTrigger.hidden === true, 'Aceptar el prompt no ocultó los CTA durante la instalación.');
  console.log('Instalación en navegadores embebidos e intención install=1: OK');
});
