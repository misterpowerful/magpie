import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { Quasar } from 'quasar';
import '@quasar/extras/material-icons/material-icons.css';
import App from './App.vue';
import router from './router';

createApp(App)
    .use(createPinia())
    .use(router)
    .use(Quasar, {
        plugins: {},
    })
    .mount('#app');
