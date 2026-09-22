import { createApp } from 'vue';
import App from '@/App.vue';
import { aplicarDesdeUrl } from '@/ajustes';
import { i18n } from '@/i18n';
import { router } from '@/router';
import '../css/app.css';

router.isReady().then(() => aplicarDesdeUrl(router.currentRoute.value.query));

createApp(App).use(router).use(i18n).mount('#app');
