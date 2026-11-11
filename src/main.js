import { createApp } from 'vue';
import App from './App.vue';

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('oab-timesheet-app');
    if (el) {
        const app = createApp(App);
        app.mount(el);
    }
});

