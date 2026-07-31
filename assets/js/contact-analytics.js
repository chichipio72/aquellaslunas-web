(function () {
    if (window.aquellasLunasContactAnalyticsBound) {
        return;
    }
    window.aquellasLunasContactAnalyticsBound = true;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-instagram-contact]');
        if (!link || typeof window.aquellasLunasTrackAnalyticsEvent !== 'function') {
            return;
        }
        window.aquellasLunasTrackAnalyticsEvent('instagram_contact_click', {
            source: link.dataset.contactSource || 'other',
            destination: 'instagram_profile',
        });
    });
})();
