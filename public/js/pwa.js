(function () {
    'use strict';

    const config = window.pwaConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const onlineClass = 'pwa-is-offline';
    let deferredInstallPrompt = null;

    function setOfflineState() {
        document.documentElement.classList.toggle(onlineClass, !navigator.onLine);
        document.querySelectorAll('[data-pwa-online-required]').forEach((element) => {
            element.setAttribute('aria-disabled', navigator.onLine ? 'false' : 'true');
        });
    }

    function notify(message, type) {
        if (typeof window.showAppToast === 'function') {
            window.showAppToast(type || 'info', message, 'PWA');
            return;
        }
        if (message) window.setTimeout(() => window.alert(message), 0);
    }

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !config.enabled) return;
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(config.serviceWorkerUrl || '/sw.js', { scope: '/', updateViaCache: 'none' })
                .then((registration) => {
                    registration.update().catch(() => {});
                    registration.addEventListener('updatefound', () => {
                        const worker = registration.installing;
                        if (!worker) return;
                        worker.addEventListener('statechange', () => {
                            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                                notify('Versi aplikasi baru tersedia. Muat ulang halaman untuk memperbarui.', 'info');
                            }
                        });
                    });
                })
                .catch((error) => console.warn('PWA service worker gagal didaftarkan.', error));
        });
    }

    function installPrompt() {
        const buttons = document.querySelectorAll('[data-pwa-install]');

        function isStandalone() {
            return window.matchMedia?.('(display-mode: standalone)').matches === true
                || window.navigator.standalone === true;
        }

        function isIos() {
            return /iphone|ipad|ipod/i.test(window.navigator.userAgent || '')
                || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
        }

        function syncInstallButtons() {
            const installed = isStandalone();
            buttons.forEach((button) => {
                button.hidden = installed;
                button.dataset.installReady = deferredInstallPrompt ? 'true' : 'false';
                button.innerHTML = deferredInstallPrompt
                    ? '<i class="bi bi-download me-2"></i>Instal aplikasi'
                    : '<i class="bi bi-phone me-2"></i>Cara memasang aplikasi';
                button.title = deferredInstallPrompt ? 'Instal aplikasi' : 'Lihat cara memasang aplikasi';
            });
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
            syncInstallButtons();
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-pwa-install]');
            if (!button || isStandalone()) return;

            if (!deferredInstallPrompt) {
                notify(isIos()
                    ? 'Di Safari, tekan Bagikan lalu pilih Tambahkan ke Layar Utama.'
                    : 'Buka menu browser lalu pilih Instal aplikasi atau Tambahkan ke layar utama.', 'info');
                return;
            }

            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            syncInstallButtons();
        });

        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            syncInstallButtons();
        });

        window.matchMedia?.('(display-mode: standalone)').addEventListener?.('change', syncInstallButtons);
        syncInstallButtons();
    }

    function base64ToUint8Array(value) {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
    }

    async function currentSubscription() {
        const registration = await navigator.serviceWorker.ready;
        return registration.pushManager.getSubscription();
    }

    async function syncSubscription(subscription) {
        if (!subscription || !config.subscribeUrl) return false;

        const json = subscription.toJSON();
        if (!json.endpoint || !json.keys?.p256dh || !json.keys?.auth) return false;

        const response = await fetch(config.subscribeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: json.keys,
                content_encoding: 'aes128gcm',
                user_agent: navigator.userAgent,
            }),
        });

        if (response.status === 401 || response.status === 419) return false;
        if (!response.ok) {
            throw new Error((await response.json().catch(() => ({}))).message || 'Subscription push gagal disimpan.');
        }

        return true;
    }

    async function subscribePush() {
        if (!config.pushEnabled || !('PushManager' in window) || !('Notification' in window)) {
            throw new Error('Browser ini belum mendukung push notification.');
        }
        if (!config.vapidPublicKey) throw new Error('Kunci push belum dikonfigurasi.');
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Izin notifikasi tidak diberikan.');

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64ToUint8Array(config.vapidPublicKey),
        });
        await syncSubscription(subscription);
        return subscription;
    }

    async function unsubscribePush() {
        const subscription = await currentSubscription();
        if (!subscription) return;
        await fetch(config.unsubscribeUrl, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ endpoint: subscription.endpoint }),
        });
        await subscription.unsubscribe();
    }

    window.pwaBeforeLogout = async function () {
        if (!config.pushEnabled) return;
        try {
            await unsubscribePush();
        } catch (error) {
            console.warn('Subscription push tidak dapat dihapus saat logout.', error);
        }
    };

    function setupPushControls() {
        const buttons = document.querySelectorAll('[data-pwa-push-toggle]');
        if (!buttons.length || !config.pushEnabled) return;
        const sync = async () => {
            const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            const subscription = supported ? await currentSubscription().catch(() => null) : null;
            if (subscription) await syncSubscription(subscription).catch((error) => console.warn('Subscription push gagal disinkronkan.', error));
            buttons.forEach((button) => {
                button.hidden = !supported;
                button.dataset.pushSubscribed = subscription ? 'true' : 'false';
                button.innerHTML = subscription ? '<i class="bi bi-bell-slash me-2"></i>Matikan notifikasi push' : '<i class="bi bi-bell me-2"></i>Aktifkan notifikasi push';
                button.title = subscription ? 'Matikan notifikasi push' : 'Aktifkan notifikasi push';
            });
        };
        buttons.forEach((button) => button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                if (button.dataset.pushSubscribed === 'true') await unsubscribePush();
                else await subscribePush();
                notify(button.dataset.pushSubscribed === 'true' ? 'Notifikasi push dimatikan.' : 'Notifikasi push diaktifkan.', 'success');
            } catch (error) {
                notify(error.message || 'Pengaturan notifikasi push gagal.', 'danger');
            } finally {
                button.disabled = false;
                await sync();
            }
        }));
        navigator.serviceWorker?.ready.then(sync).catch(() => {});
        window.addEventListener('pageshow', () => sync().catch(() => {}));
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) sync().catch(() => {});
        });
    }

    window.addEventListener('online', setOfflineState);
    window.addEventListener('offline', setOfflineState);
    setOfflineState();
    registerServiceWorker();
    installPrompt();
    setupPushControls();
})();
