import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";
import axios from "axios";

/**
 * Service responsible for managing Firebase Cloud Messaging within the Vue application.
 * Handles initialization, permission requests, and foreground message processing.
 */
class FirebaseMessagingService {
    constructor() {
        this.app = null;
        this.messaging = null;
        this.isInitialized = false;
    }

    /**
     * Initializes the Firebase application using environment variables.
     * Prevents multiple initializations.
     *
     * @returns {void}
     */
    init() {
        if (this.isInitialized) return;

        const firebaseConfig = {
            apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
            authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
            projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
            storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
            messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
            appId: import.meta.env.VITE_FIREBASE_APP_ID
        };

        this.app = initializeApp(firebaseConfig);
        this.messaging = getMessaging(this.app);
        this.isInitialized = true;
    }

    /**
     * Requests notification permissions from the user and retrieves the FCM token.
     * Dispatches the token to the backend API.
     *
     * @returns {Promise<string|null>} The generated FCM token or null if denied.
     */
    async requestPermissionAndGetToken() {
        if (!this.isInitialized) this.init();

        try {
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                // Generates the device specific token using the VAPID key
                // Note: The vapidKey is optional but highly recommended for web push.
                // For now, we fetch the default token.
                const currentToken = await getToken(this.messaging, {
                    vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY || null
                });

                if (currentToken) {
                    await this.registerTokenWithBackend(currentToken);
                    return currentToken;
                } else {
                    console.warn('No registration token available. Request permission to generate one.');
                    return null;
                }
            } else {
                console.warn('Notification permission denied by user.');
                return null;
            }
        } catch (error) {
            console.error('An error occurred while retrieving token: ', error);
            return null;
        }
    }

    /**
     * Sends the retrieved FCM token to the Laravel API for persistence.
     * Utilizes the web route to automatically leverage Inertia/Sanctum session cookies.
     *
     * @param {string} token The FCM device token.
     * @returns {Promise<void>}
     */
    async registerTokenWithBackend(token) {
        try {
            // Removemos o /api/ para usar as rotas Web com autenticação baseada em Cookies
            await axios.post('/fcm-token', {
                token: token,
                device_type: 'web'
            });
            console.log('FCM Token successfully registered with the backend.');
        } catch (error) {
            console.error('Failed to register FCM token with backend: ', error);
        }
    }

    /**
     * Registers a callback function to handle messages received while the app is in the foreground.
     *
     * @param {Function} callback The function to execute when a message arrives.
     * @returns {void}
     */
    onForegroundMessage(callback) {
        if (!this.isInitialized) this.init();
        
        onMessage(this.messaging, (payload) => {
            console.log('Message received in foreground: ', payload);
            callback(payload);
        });
    }
}

// Export as a singleton instance
export default new FirebaseMessagingService();