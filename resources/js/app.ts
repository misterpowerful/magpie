import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { Quasar } from 'quasar'
import '@quasar/extras/material-icons/material-icons.css'
import App from './App.vue'
import router from './router'

createApp(App)
    .use(createPinia())
    .use(router)
    .use(Quasar, {
        plugins: {},
        config: {
            // Always-dark (no light/dark toggle yet — can add one later).
            dark: true,
            brand: {
                // Two distinct charcoal-navy tones so surfaces layer
                // properly: a near-black page behind a slightly lighter
                // card/table surface, with the requested navy reserved
                // for primary (buttons/active accents) so it visibly
                // pops forward against both. Black is secondary, teal is
                // the one accent color against all that navy/black.
                'dark-page': '#0a0f16',
                dark: '#111822',
                primary: '#1e2a3a',
                secondary: '#0a0a0a',
                accent: '#0d9488',
                positive: '#16a34a',
                negative: '#dc2626',
                warning: '#d97706',
                info: '#2563eb',
            },
        },
    })
    .mount('#app')
