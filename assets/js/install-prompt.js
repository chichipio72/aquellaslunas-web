(function () {
    const storageKey = 'aquellasLunasInstallCardDismissedUntil';
    const dismissDays = 30;
    const installCardDismissMs = dismissDays * 24 * 60 * 60 * 1000;
    let installAcceptedTracked = false;
    let lastInstallSource = 'browser';

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

    async function runInstallAction(event, trigger) {
        event.preventDefault();
        const platform = getPlatform();
        const source = installSource(trigger);
        lastInstallSource = source;
        if (platform.isIOS) {
            trackInstallEvent('pwa_ios_instructions', source, 'open_instructions');
            showHelpPanel();
            showTemporaryStatus('Abrí el menú de compartir para agregar la web a la pantalla de inicio.');
            return;
        }

        trackInstallEvent('pwa_install_prompt', source, window.deferredInstallPrompt ? 'prompt_available' : 'prompt_unavailable');
        if (window.deferredInstallPrompt) {
            try {
                window.deferredInstallPrompt.prompt();
                const choice = await window.deferredInstallPrompt.userChoice;
                if (choice?.outcome === 'accepted') {
                    if (!installAcceptedTracked) {
                        installAcceptedTracked = true;
                        trackInstallEvent('pwa_install_accepted', source, 'user_choice');
                    }
                    showTemporaryStatus('La instalación quedó solicitada.');
                } else if (choice?.outcome === 'dismissed') {
                    trackInstallEvent('pwa_install_dismissed', source, 'user_choice');
                    showTemporaryStatus('La instalación no se completó.');
                }
            } catch (error) {
                showTemporaryStatus('No se pudo abrir el diálogo de instalación.');
            }
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
        trackInstallEvent('pwa_promo_closed', installSource(event.target.closest('[data-install-dismiss]')), 'dismiss_30_days');
        const card = document.querySelector('[data-install-card]');
        if (card) {
            card.hidden = true;
        }
        writeDismissedUntil(Date.now() + installCardDismissMs);
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
            }
        });
    }

    function initialize() {
        window.deferredInstallPrompt = null;
        bindEvents();
        updateCardContent();
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.deferredInstallPrompt = event;
        updateCardContent();
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
        clearDismissedUntil();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
