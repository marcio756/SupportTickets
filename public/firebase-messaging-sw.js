/**
 * Import Firebase compatible scripts for the Service Worker context.
 * We use the compat libraries here because standard modules don't work reliably in all SW environments yet.
 */
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging-compat.js');

/**
 * Configuration explicitly declared since Service Workers cannot access process.env or Vite's import.meta.env
 */
const firebaseConfig = {
    apiKey: "AIzaSyDYcPWGzonpYgerhNT0k3wbSl6jh2Z87Xg",
    authDomain: "support-tickets-app-31f91.firebaseapp.com",
    projectId: "support-tickets-app-31f91",
    storageBucket: "support-tickets-app-31f91.firebasestorage.app",
    messagingSenderId: "930751298095",
    appId: "1:930751298095:web:328512a103bd0dfeb2a4da"
};

// Initialize the Firebase app in the service worker
firebase.initializeApp(firebaseConfig);

// Retrieve the messaging instance
const messaging = firebase.messaging();

/**
 * Background message handler.
 * This triggers when a push notification is received while the web application is closed or in the background.
 */
messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);

    const notificationTitle = payload.notification.title;
    const notificationOptions = {
        body: payload.notification.body,
        icon: '/icons/Icon-192.png', // Fallback to your PWA icon
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});