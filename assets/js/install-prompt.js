(function () {
    const storageKey = 'aquellasLunasInstallCardDismissedUntil';
    const embeddedDismissKey = 'aquellasLunasEmbeddedInstallNoticeDismissed';
    const installIntentParameter = 'install';
    const installIntentWaitMs = 5000;
    const dismissDays = 30;
    const installCardDismissMs = dismissDays * 24 * 60 * 60 * 1000;
    let installAcceptedTracked = false;
    let lastInstallSource = 'browser';
    let installIntentActive = false;
    let installIntentTimer = null;
    let installOutcomePending = false;
    if (!('deferredInstallPrompt' in window)) window.deferredInstallPrompt = null;

    function canUseStorage() {
        try {
            return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';
        } catch (error) {
            return false;
        }
    }

    function readDismissedUntil() {
        if (!canUseStorage()) {
            return 0;
        }
        const value = window.localStorage.getItem(storageKey);
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function writeDismissedUntil(value) {
        if (!canUseStorage()) {
            return;
        }
        window.localStorage.setItem(storageKey, String(value));
    }

    function clearDismissedUntil() {
        if (!canUseStorage()) {
            return;
        }
        window.localStorage.removeItem(storageKey);
    }

    function canUseSessionStorage() {
        try {
            return typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined';
        } catch (error) {
            return false;
        }
    }

    function embeddedNoticeDismissed() {
        return canUseSessionStorage() && window.sessionStorage.getItem(embeddedDismissKey) === '1';
    }

    function dismissEmbeddedNotice() {
        if (canUseSessionStorage()) {
            window.sessionStorage.setItem(embeddedDismissKey, '1');
        }
    }

    function isInstalledApp() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.navigator.standalone === true;
    }

    function getPlatform() {
        const userAgent = navigator.userAgent || '';
        const isIOS = /iPad|iPhone|iPod/.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /Android/.test(userAgent);
        const isMac = /Macintosh|Mac OS X/.test(userAgent);
        const isDesktop = !isIOS && !isAndroid && /Windows|Macintosh|Linux/.test(userAgent);
        let name = 'other';
        if (isAndroid) {
            name = 'android';
        } else if (isIOS) {
            name = 'ios';
        } else if (/Windows/.test(userAgent)) {
            name = 'windows';
        } else if (isMac) {
            name = 'macos';
        } else if (/Linux/.test(userAgent)) {
            name = 'linux';
        }
        return { isIOS, isAndroid, isDesktop, isMac, name };
    }

    function getEmbeddedBrowserContext() {
        const userAgent = navigator.userAgent || '';
        const platform = getPlatform();
        let provider = null;
        if (/Instagram/i.test(userAgent)) {
            provider = 'instagram';
        } else if (/FBAN|FBAV|FB_IAB|FB4A|FBIOS/i.test(userAgent)) {
            provider = 'facebook';
        }
        return {
            embedded: provider !== null,
            provider,
            providerLabel: provider === 'instagram' ? 'Instagram' : (provider === 'facebook' ? 'Facebook' : ''),
            isAndroid: platform.isAndroid,
            isIOS: platform.isIOS,
        };
    }

    function getBrowser() {
        const userAgent = navigator.userAgent || '';
        if (/SamsungBrowser\//.test(userAgent)) {
            return 'samsung_internet';
        }
        if (/Edg\//.test(userAgent)) {
            return 'edge';
        }
        if (/OPR\//.test(userAgent)) {
            return 'opera';
        }
        if (/Firefox\/|FxiOS\//.test(userAgent)) {
            return 'firefox';
        }
        if (/CriOS\//.test(userAgent)) {
            return 'chrome';
        }
        if (/Chrome\//.test(userAgent)) {
            return 'chrome';
        }
        if (/Safari\//.test(userAgent)) {
            return 'safari';
        }
        return 'other';
    }

    function getDisplayMode() {
        return isInstalledApp() ? 'standalone' : 'browser';
    }

    function installSource(element) {
        return element?.dataset?.installSource || 'other';
    }

    function trackInstallEvent(name, source, action) {
        if (typeof window.aquellasLunasTrackAnalyticsEvent !== 'function') {
            return false;
        }
        return window.aquellasLunasTrackAnalyticsEvent(name, {
            source: source || 'other',
            platform: getPlatform().name,
            browser: getBrowser(),
            display_mode: getDisplayMode(),
            action: action || name,
        });
    }

    function getInstallExperienceState() {
        const platform = getPlatform();
        const browser = getBrowser();
        const embedded = getEmbeddedBrowserContext();
        if (isInstalledApp() || installOutcomePending) {
            return { kind: 'installed', visible: false };
        }
        if (embedded.embedded) {
            return {
                kind: 'open_external_browser',
                visible: true,
                label: embedded.isIOS ? 'Cómo abrir en Safari' : 'Abrir en el navegador',
                title: 'Abrí Aquellas Lunas en tu navegador',
                copy: `El navegador interno de ${embedded.providerLabel} no permite instalar la aplicación ni agregar un acceso directo.`,
                help: embeddedInstructions(embedded),
            };
        }
        if (platform.isIOS) {
            return {
                kind: 'ios_home_screen',
                visible: true,
                label: 'Agregar a pantalla de inicio',
                title: 'Agregar Aquellas Lunas a la pantalla de inicio',
                copy: 'En iPhone y iPad esta acción se completa manualmente desde Safari.',
                help: 'Abrí Compartir, elegí “Agregar a pantalla de inicio” y confirmá con Agregar.',
            };
        }
        if (window.deferredInstallPrompt) {
            return {
                kind: 'pwa',
                visible: true,
                label: 'Instalar aplicación',
                title: 'Instalar Aquellas Lunas',
                copy: 'Instalá Aquellas Lunas como aplicación para abrirla en modo independiente.',
                help: '',
            };
        }
        if (platform.isAndroid) {
            return {
                kind: 'shortcut',
                visible: true,
                label: 'Agregar acceso directo',
                title: 'Agregar un acceso directo',
                copy: 'El navegador no ofrece ahora la instalación de la aplicación. Podés crear un acceso directo manualmente.',
                help: 'Abrí el menú del navegador y elegí “Agregar a pantalla de inicio” o “Agregar a pantalla principal”.',
            };
        }
        if (platform.isDesktop && ['chrome', 'edge'].includes(browser)) {
            return {
                kind: 'shortcut',
                visible: true,
                label: 'Agregar acceso directo',
                title: 'Agregar un acceso directo',
                copy: 'El navegador no ofrece ahora la instalación de la aplicación. Podés crear un acceso directo manualmente.',
                help: browser === 'edge'
                    ? 'Abrí el menú de Edge y buscá la opción para crear un acceso directo a esta página.'
                    : 'Abrí el menú de Chrome, entrá en “Transmitir, guardar y compartir” y elegí “Crear acceso directo”.',
            };
        }
        return { kind: 'unavailable', visible: false };
    }

    function updateInstallTriggers(state) {
        document.querySelectorAll('[data-install-trigger]').forEach(function (trigger) {
            trigger.hidden = !state.visible;
            if (!state.visible) return;
            trigger.textContent = state.label;
            trigger.setAttribute('aria-label', state.label + ' para Aquellas Lunas');
            trigger.dataset.installState = state.kind;
        });
    }

    function updateCardContent() {
        const card = document.querySelector('[data-install-card]');
        const actionButton = document.querySelector('[data-install-action]');
        const secondaryButton = document.querySelector('[data-install-secondary]');
        const helpPanel = document.querySelector('[data-install-help]');
        const helpToggle = document.querySelector('[data-install-help-toggle]');
        const status = document.querySelector('[data-install-status]');
        const title = document.querySelector('[data-install-title]');
        const copy = document.querySelector('[data-install-copy]');
        const embedded = getEmbeddedBrowserContext();
        const state = getInstallExperienceState();

        updateInstallTriggers(state);

        if (!card || !actionButton) {
            return;
        }

        if (!state.visible) {
            card.hidden = true;
            if (status) {
                status.textContent = '';
            }
            return;
        }

        if (state.kind === 'open_external_browser') {
            if (embeddedNoticeDismissed()) {
                card.hidden = true;
                return;
            }
            card.hidden = false;
            if (title) title.textContent = state.title;
            if (copy) copy.textContent = state.copy;
            actionButton.textContent = state.label;
            if (secondaryButton) secondaryButton.hidden = true;
            if (helpToggle) helpToggle.hidden = true;
            if (helpPanel) {
                helpPanel.hidden = false;
                helpPanel.innerHTML = embedded.isIOS
                    ? `<p>Abrí el menú de ${embedded.providerLabel} y elegí <strong>Abrir en navegador</strong>. Si esa opción no aparece, copiá el enlace y abrilo en Safari.</p>`
                    : `<p>Si el botón no abre otro navegador, usá el menú de ${embedded.providerLabel} y elegí <strong>Abrir en navegador</strong>.</p>`;
            }
            if (status) status.textContent = '';
            return;
        }

        const dismissedUntil = readDismissedUntil();
        if (dismissedUntil > Date.now()) {
            card.hidden = true;
            return;
        }

        card.hidden = false;
        actionButton.textContent = state.label;
        if (secondaryButton) secondaryButton.hidden = true;
        if (helpToggle) helpToggle.hidden = true;
        if (copy) copy.textContent = state.copy;
        if (title) title.textContent = state.title;
        if (helpPanel) helpPanel.hidden = true;
    }

    function showTemporaryStatus(message) {
        const status = document.querySelector('[data-install-status]');
        if (status) {
            status.textContent = message;
        }
    }

    function showHelpPanel() {
        const helpPanel = document.querySelector('[data-install-help]');
        if (helpPanel) {
            helpPanel.hidden = false;
        }
    }

    function currentExternalUrl() {
        try {
            const url = new URL(window.location.href);
            if (!['http:', 'https:'].includes(url.protocol)) return null;
            url.hash = '';
            return url;
        } catch (error) {
            return null;
        }
    }

    function addInstallIntent(url) {
        if (!(url instanceof URL) || !['http:', 'https:'].includes(url.protocol)) return null;
        const target = new URL(url.href);
        target.searchParams.set(installIntentParameter, '1');
        return target;
    }

    function hasInstallIntent() {
        if (getPlatform().isIOS || getEmbeddedBrowserContext().embedded || isInstalledApp()) return false;
        try {
            return getPlatform().isAndroid
                && new URL(window.location.href).searchParams.get(installIntentParameter) === '1';
        } catch (error) {
            return false;
        }
    }

    function clearInstallIntent() {
        try {
            const url = new URL(window.location.href);
            if (url.searchParams.get(installIntentParameter) !== '1') return;
            url.searchParams.delete(installIntentParameter);
            const cleanUrl = url.pathname + url.search + url.hash;
            window.history?.replaceState?.(window.history.state, '', cleanUrl);
        } catch (error) {
            // La limpieza es cosmética; nunca debe bloquear la navegación.
        }
    }

    function androidChromeIntent(url) {
        if (!(url instanceof URL) || !['http:', 'https:'].includes(url.protocol)) return null;
        const target = (url.host + url.pathname + url.search).replace(/#/g, '%23');
        return `intent://${target}#Intent;scheme=${url.protocol.slice(0, -1)};package=com.android.chrome;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;end`;
    }

    function ensureInstallIntentDialog() {
        let dialog = document.querySelector('[data-install-intent-dialog]');
        if (dialog) return dialog;
        dialog = document.createElement('dialog');
        dialog.className = 'install-intent-dialog';
        dialog.dataset.installIntentDialog = '';
        dialog.setAttribute('aria-labelledby', 'install-intent-title');
        dialog.setAttribute('aria-describedby', 'install-intent-copy install-intent-status');
        dialog.innerHTML = '<div class="card install-intent-dialog__surface"><button type="button" class="install-intent-dialog__close" data-install-intent-close aria-label="Cerrar">×</button><h2 id="install-intent-title" data-install-intent-title>Instalar Aquellas Lunas</h2><p id="install-intent-copy" data-install-intent-copy></p><p id="install-intent-status" class="install-intent-dialog__status" data-install-intent-status aria-live="polite"></p><div class="install-intent-dialog__actions"><button type="button" class="button button-primary" data-install-intent-action hidden disabled>Instalar</button><button type="button" class="button compact-secondary-button" data-install-intent-close>Seguir navegando</button></div></div>';
        document.body.appendChild(dialog);
        dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeInstallIntentDialog();
            finishInstallIntent();
        });
        return dialog;
    }

    function openInstallIntentDialog(state) {
        const dialog = ensureInstallIntentDialog();
        const title = dialog.querySelector('[data-install-intent-title]');
        const copy = dialog.querySelector('[data-install-intent-copy]');
        const status = dialog.querySelector('[data-install-intent-status]');
        const action = dialog.querySelector('[data-install-intent-action]');
        if (state === 'ready') {
            if (title) title.textContent = '¿Querés instalar Aquellas Lunas?';
            if (copy) copy.textContent = 'Ya estás en el navegador. Tocá Instalar para agregarla a tu dispositivo.';
            if (status) status.textContent = '';
            if (action) { action.hidden = false; action.disabled = false; }
        } else {
            if (title) title.textContent = 'La instalación no está disponible ahora';
            if (copy) copy.textContent = 'Chrome todavía no ofrece instalar Aquellas Lunas desde esta página. Podés seguir navegando normalmente e intentarlo más tarde desde el menú.';
            if (status) status.textContent = '';
            if (action) { action.hidden = true; action.disabled = true; }
        }
        if (!dialog.open) dialog.showModal();
        return dialog;
    }

    function closeInstallIntentDialog() {
        const dialog = document.querySelector('[data-install-intent-dialog]');
        if (dialog?.open) dialog.close();
    }

    function finishInstallIntent() {
        installIntentActive = false;
        if (installIntentTimer !== null) {
            window.clearTimeout(installIntentTimer);
            installIntentTimer = null;
        }
        clearInstallIntent();
    }

    function waitForInstallIntentPrompt() {
        if (!installIntentActive || window.deferredInstallPrompt) return;
        installIntentTimer = window.setTimeout(function () {
            if (!installIntentActive || window.deferredInstallPrompt) return;
            openInstallIntentDialog('unavailable');
            finishInstallIntent();
            trackInstallEvent('pwa_install_intent_unavailable', 'install_intent', 'prompt_timeout');
        }, installIntentWaitMs);
    }

    function embeddedInstructions(context) {
        if (context.isIOS) {
            return `Abrí el menú de ${context.providerLabel} y elegí “Abrir en navegador”. Si no aparece, copiá el enlace y abrilo en Safari.`;
        }
        return `Si no se abre otro navegador, usá el menú de ${context.providerLabel} y elegí “Abrir en navegador”.`;
    }

    function showEmbeddedNotice(trigger, context) {
        if (embeddedNoticeDismissed()) return;
        const state = getInstallExperienceState();
        showInstallNotice(trigger, {
            title: state.title,
            copy: state.copy,
            help: state.help,
            external: context.isAndroid,
        });
    }

    function showInstallNotice(trigger, content) {
        let notice = document.querySelector('[data-install-embedded-notice]');
        if (!notice) {
            notice = document.createElement('section');
            notice.className = 'card install-embedded-notice';
            notice.dataset.installEmbeddedNotice = '';
            notice.setAttribute('role', 'status');
            notice.innerHTML = '<div><strong data-install-embedded-title></strong><p data-install-embedded-copy></p><p data-install-embedded-help></p></div><div class="install-embedded-notice__actions"><button type="button" class="button compact-secondary-button" data-install-external hidden>Abrir en el navegador</button><button type="button" class="install-embedded-notice__dismiss" data-install-embedded-dismiss>Cerrar</button></div>';
            const container = trigger.closest('.capability-feature, .site-menu-panel, .site-nav') || trigger.parentElement;
            container?.appendChild(notice);
        }
        const title = notice.querySelector('[data-install-embedded-title]');
        const copy = notice.querySelector('[data-install-embedded-copy]');
        const help = notice.querySelector('[data-install-embedded-help]');
        const external = notice.querySelector('[data-install-external]');
        if (title) title.textContent = content.title;
        if (copy) copy.textContent = content.copy;
        if (help) help.textContent = content.help || '';
        if (external) external.hidden = content.external !== true;
        notice.hidden = false;
    }

    function openExternalBrowser(context, source) {
        const currentUrl = currentExternalUrl();
        const url = currentUrl ? addInstallIntent(currentUrl) : null;
        if (!context.isAndroid || !url) {
            showTemporaryStatus(embeddedInstructions(context));
            return;
        }
        const intent = androidChromeIntent(url);
        showTemporaryStatus(embeddedInstructions(context));
        trackInstallEvent('pwa_embedded_open_external', source, context.provider || 'embedded');
        if (intent) window.location.href = intent;
    }

    async function requestNativeInstall(source) {
        const prompt = window.deferredInstallPrompt;
        if (!prompt) return 'unavailable';
        window.deferredInstallPrompt = null;
        try {
            prompt.prompt();
            const choice = await prompt.userChoice;
            if (choice?.outcome === 'accepted') {
                installOutcomePending = true;
                if (!installAcceptedTracked) {
                    installAcceptedTracked = true;
                    trackInstallEvent('pwa_install_accepted', source, 'user_choice');
                }
                return 'accepted';
            }
            if (choice?.outcome === 'dismissed') {
                trackInstallEvent('pwa_install_dismissed', source, 'user_choice');
                return 'dismissed';
            }
            return 'dismissed';
        } catch (error) {
            return 'error';
        }
    }

    async function runInstallAction(event, trigger) {
        event.preventDefault();
        const embedded = getEmbeddedBrowserContext();
        const state = getInstallExperienceState();
        const source = installSource(trigger);
        lastInstallSource = source;
        if (state.kind === 'installed' || state.kind === 'unavailable') return;
        if (state.kind === 'open_external_browser') {
            trackInstallEvent('pwa_embedded_browser_notice', source, embedded.provider || 'embedded');
            const card = document.querySelector('[data-install-card]');
            if (!card || card.hidden) showEmbeddedNotice(trigger, embedded);
            if (embedded.isAndroid) {
                openExternalBrowser(embedded, source);
            } else {
                showHelpPanel();
                showTemporaryStatus(embeddedInstructions(embedded));
            }
            return;
        }
        if (state.kind === 'ios_home_screen' || state.kind === 'shortcut') {
            const eventName = state.kind === 'ios_home_screen' ? 'pwa_ios_instructions' : 'pwa_shortcut_instructions';
            trackInstallEvent(eventName, source, 'open_instructions');
            showInstallNotice(trigger, {
                title: state.title,
                copy: state.copy,
                help: state.help,
                external: false,
            });
            showTemporaryStatus(state.help);
            return;
        }
        if (state.kind === 'pwa') {
            trackInstallEvent('pwa_install_prompt', source, 'prompt_available');
            const outcome = await requestNativeInstall(source);
            if (outcome === 'accepted') showTemporaryStatus('La instalación quedó solicitada.');
            else if (outcome === 'dismissed') showTemporaryStatus('La instalación no se completó. Podés agregar un acceso directo desde el menú del navegador.');
            else showTemporaryStatus('No se pudo abrir el diálogo de instalación.');
            updateCardContent();
            return;
        }
    }

    function showFavoritesHelp(source) {
        trackInstallEvent('pwa_favorite_help', source, 'open_help');
        const platform = getPlatform();
        if (platform.isMac) {
            showTemporaryStatus('En macOS, usá ⌘ + D para guardar esta web en favoritos.');
            return;
        }
        showTemporaryStatus('En Windows o Linux, usá Ctrl + D para guardar esta web en favoritos.');
    }

    function dismissCard(event) {
        event.preventDefault();
        const embedded = getEmbeddedBrowserContext();
        trackInstallEvent('pwa_promo_closed', installSource(event.target.closest('[data-install-dismiss]')), embedded.embedded ? 'dismiss_session' : 'dismiss_30_days');
        const card = document.querySelector('[data-install-card]');
        if (card) {
            card.hidden = true;
        }
        if (embedded.embedded) dismissEmbeddedNotice();
        else writeDismissedUntil(Date.now() + installCardDismissMs);
    }

    function bindEvents() {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-install-trigger]');
            if (trigger) {
                event.preventDefault();
                const source = installSource(trigger);
                trackInstallEvent('pwa_install_open', source, 'open');
                runInstallAction(event, trigger);
                return;
            }

            const secondary = event.target.closest('[data-install-secondary]');
            if (secondary) {
                event.preventDefault();
                showFavoritesHelp(installSource(secondary));
                return;
            }

            const helpToggle = event.target.closest('[data-install-help-toggle]');
            if (helpToggle) {
                event.preventDefault();
                trackInstallEvent('pwa_ios_instructions', installSource(helpToggle), 'open_instructions');
                showHelpPanel();
                return;
            }

            const dismissButton = event.target.closest('[data-install-dismiss]');
            if (dismissButton) {
                dismissCard(event);
                return;
            }

            const externalButton = event.target.closest('[data-install-external]');
            if (externalButton) {
                event.preventDefault();
                openExternalBrowser(getEmbeddedBrowserContext(), installSource(externalButton));
                return;
            }

            const embeddedDismiss = event.target.closest('[data-install-embedded-dismiss]');
            if (embeddedDismiss) {
                event.preventDefault();
                dismissEmbeddedNotice();
                const notice = embeddedDismiss.closest('[data-install-embedded-notice]');
                if (notice) notice.hidden = true;
                return;
            }

            const intentAction = event.target.closest('[data-install-intent-action]');
            if (intentAction) {
                event.preventDefault();
                if (intentAction.disabled || !window.deferredInstallPrompt) return;
                intentAction.disabled = true;
                trackInstallEvent('pwa_install_prompt', 'install_intent', 'prompt_available');
                requestNativeInstall('install_intent').then(function () {
                    closeInstallIntentDialog();
                    finishInstallIntent();
                });
                return;
            }

            const intentClose = event.target.closest('[data-install-intent-close]');
            if (intentClose) {
                event.preventDefault();
                closeInstallIntentDialog();
                finishInstallIntent();
            }
        });
    }

    function initialize() {
        bindEvents();
        updateCardContent();
        if (isInstalledApp()) {
            clearInstallIntent();
            return;
        }
        installIntentActive = hasInstallIntent();
        if (installIntentActive) {
            if (window.deferredInstallPrompt) openInstallIntentDialog('ready');
            else waitForInstallIntentPrompt();
        }
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        updateCardContent();
        if (installIntentActive) {
            if (installIntentTimer !== null) {
                window.clearTimeout(installIntentTimer);
                installIntentTimer = null;
            }
            openInstallIntentDialog('ready');
        }
    });

    window.addEventListener('appinstalled', function () {
        installOutcomePending = true;
        if (!installAcceptedTracked) {
            installAcceptedTracked = true;
            trackInstallEvent('pwa_install_accepted', lastInstallSource, 'appinstalled');
        }
        const card = document.querySelector('[data-install-card]');
        if (card) {
            card.hidden = true;
        }
        document.querySelectorAll('[data-install-trigger]').forEach(function (trigger) {
            trigger.hidden = true;
        });
        closeInstallIntentDialog();
        finishInstallIntent();
        clearDismissedUntil();
    });

    window.aquellasLunasInstallContext = {
        getEmbeddedBrowserContext,
        getInstallExperienceState,
        addInstallIntent,
        androidChromeIntent,
        hasInstallIntent,
        isInstalledApp,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
