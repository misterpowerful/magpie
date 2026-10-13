import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { Quasar } from 'quasar';
import '@quasar/extras/material-icons/material-icons.css';
import App from './App.vue';

createApp(App)
    .use(createPinia())
    .use(Quasar, {
        plugins: {},
    })
    .mount('#app');
