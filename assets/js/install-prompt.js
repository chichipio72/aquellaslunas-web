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

    function updateEmbeddedTriggers(context) {
        if (isInstalledApp()) {
            document.querySelectorAll('[data-install-trigger]').forEach(function (trigger) {
                trigger.hidden = true;
            });
            return;
        }
        if (!context.embedded) return;
        document.querySelectorAll('[data-install-trigger]').forEach(function (trigger) {
            trigger.textContent = context.isAndroid ? 'Abrir en el navegador' : 'Cómo abrir en Safari';
            trigger.setAttribute('aria-label', context.isAndroid ? 'Abrir Aquellas Lunas en el navegador' : 'Ver cómo abrir Aquellas Lunas en Safari');
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
        const platform = getPlatform();
        const embedded = getEmbeddedBrowserContext();

        updateEmbeddedTriggers(embedded);

        if (!card || !actionButton) {
            return;
        }

        if (isInstalledApp()) {
            card.hidden = true;
            if (status) {
                status.textContent = '';
            }
            return;
        }

        if (embedded.embedded) {
            if (embeddedNoticeDismissed()) {
                card.hidden = true;
                return;
            }
            card.hidden = false;
            if (title) {
                title.textContent = 'Abrí Aquellas Lunas en tu navegador';
            }
            if (copy) {
                copy.textContent = embedded.isIOS
                    ? `Para instalar Aquellas Lunas como app, abrila primero en Safari. El navegador interno de ${embedded.providerLabel} no permite completar la instalación.`
                    : `Para instalar Aquellas Lunas como app, abrila primero en tu navegador. El navegador interno de ${embedded.providerLabel} no permite completar la instalación.`;
            }
            actionButton.textContent = embedded.isAndroid ? 'Abrir en el navegador' : 'Ver cómo abrir en Safari';
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

        if (platform.isIOS) {
            actionButton.textContent = 'Agregar a pantalla de inicio';
            if (secondaryButton) {
                secondaryButton.hidden = true;
            }
            if (helpToggle) {
                helpToggle.hidden = false;
            }
            if (copy) {
                copy.textContent = 'Guardá Aquellas Lunas en tu dispositivo para abrirla con un toque desde la pantalla de inicio.';
            }
            if (title) {
                title.textContent = 'Tené Aquellas Lunas a mano';
            }
            if (helpPanel) {
                helpPanel.hidden = true;
            }
            return;
        }

        if (platform.isAndroid) {
            actionButton.textContent = 'Instalar';
            if (secondaryButton) {
                secondaryButton.hidden = true;
            }
            if (helpToggle) {
                helpToggle.hidden = true;
            }
            if (copy) {
                copy.textContent = 'Instalá la web como aplicación en tu Android para abrirla desde la pantalla de inicio.';
            }
            if (title) {
                title.textContent = 'Tené Aquellas Lunas a mano';
            }
            if (helpPanel) {
                helpPanel.hidden = true;
            }
            return;
        }

        if (platform.isDesktop) {
            actionButton.textContent = 'Instalar como aplicación';
            if (secondaryButton) {
                secondaryButton.hidden = false;
            }
            if (helpToggle) {
                helpToggle.hidden = true;
            }
            if (copy) {
                copy.textContent = 'Guardá la web como una app de escritorio o como favorita para volver a abrirla rápido.';
            }
            if (title) {
                title.textContent = 'Tené Aquellas Lunas a mano';
            }
            if (helpPanel) {
                helpPanel.hidden = true;
            }
            return;
        }

        card.hidden = true;
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
        showInstallNotice(trigger, {
            title: 'Abrí Aquellas Lunas en tu navegador',
            copy: `El navegador interno de ${context.providerLabel} no permite completar la instalación.`,
            help: embeddedInstructions(context),
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
        const platform = getPlatform();
        const embedded = getEmbeddedBrowserContext();
        const source = installSource(trigger);
        lastInstallSource = source;
        if (isInstalledApp()) return;
        if (embedded.embedded) {
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
        if (platform.isIOS) {
            trackInstallEvent('pwa_ios_instructions', source, 'open_instructions');
            showHelpPanel();
            showTemporaryStatus('Abrí el menú de compartir para agregar la web a la pantalla de inicio.');
            const card = document.querySelector('[data-install-card]');
            if (source === 'menu' && (!card || card.hidden)) {
                showInstallNotice(trigger, {
                    title: 'Instalá Aquellas Lunas desde Safari',
                    copy: 'Abrí el menú Compartir y elegí “Agregar a pantalla de inicio”.',
                    help: 'Después confirmá con Agregar.',
                    external: false,
                });
            }
            return;
        }

        trackInstallEvent('pwa_install_prompt', source, window.deferredInstallPrompt ? 'prompt_available' : 'prompt_unavailable');
        if (window.deferredInstallPrompt) {
            const outcome = await requestNativeInstall(source);
            if (outcome === 'accepted') showTemporaryStatus('La instalación quedó solicitada.');
            else if (outcome === 'dismissed') showTemporaryStatus('La instalación no se completó.');
            else showTemporaryStatus('No se pudo abrir el diálogo de instalación.');
            return;
        }

        if (platform.isAndroid) {
            showTemporaryStatus('Tu navegador todavía no ofrece la instalación desde esta página.');
            return;
        }

        if (platform.isDesktop) {
            showTemporaryStatus('Tu navegador no ofrece la instalación como app en este momento.');
            return;
        }

        showTemporaryStatus('Esta opción no está disponible en este dispositivo.');
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
        if (!installAcceptedTracked) {
            installAcceptedTracked = true;
            trackInstallEvent('pwa_install_accepted', lastInstallSource, 'appinstalled');
        }
        const card = document.querySelector('[data-install-card]');
        if (card) {
            card.hidden = true;
        }
        closeInstallIntentDialog();
        finishInstallIntent();
        clearDismissedUntil();
    });

    window.aquellasLunasInstallContext = { getEmbeddedBrowserContext, addInstallIntent, androidChromeIntent, hasInstallIntent, isInstalledApp };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
