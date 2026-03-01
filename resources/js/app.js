import './bootstrap';

import Alpine from 'alpinejs';
import { initPlayers } from './modules/players';
import { initKnockout } from './modules/knockout';
import { initGroups } from './modules/groups';

document.addEventListener('DOMContentLoaded', function () {
    initGroups();
});

document.addEventListener('DOMContentLoaded', function () {
    initPlayers();
    initKnockout();
});

window.Alpine = Alpine;

Alpine.start();
