import { onMounted } from 'vue';
import { useToast } from 'vuestic-ui';
import FirebaseMessagingService from '@/Services/FirebaseMessagingService';

/**
 * Orchestrates the application-wide notification system.
 * Initializes Firebase Cloud Messaging, requests permissions, and maps incoming
 * foreground payloads to the in-app Vuestic UI toast system and native browser alerts.
 * * @returns {void}
 */
export function useAppNotifications() {
    const { init: initToast } = useToast();

    onMounted(() => {
        FirebaseMessagingService.init();
        
        FirebaseMessagingService.requestPermissionAndGetToken();

        FirebaseMessagingService.onForegroundMessage((payload) => {
            console.log('Foreground Push Notification received: ', payload);
            
            initToast({
                title: payload.notification.title,
                message: payload.notification.body,
                color: 'primary',
                position: 'bottom-right',
                duration: 5000,
                icon: 'notifications_active'
            });

            if (Notification.permission === 'granted') {
                new Notification(payload.notification.title, {
                    body: payload.notification.body,
                    icon: '/icons/Icon-192.png'
                });
            }
        });
    });
}