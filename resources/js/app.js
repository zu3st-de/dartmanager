import './bootstrap';
import './bracket';

import Alpine from 'alpinejs';
import { initPlayers } from './modules/players';
import { initKnockout } from './modules/knockout';
import { initGroups } from './modules/groups';

document.addEventListener('DOMContentLoaded', () => {

    const status = window.tournamentStatus;

    let defaultView = 'players';

    /*
    |--------------------------------------------------------------------------
    | 🎯 Automatische View-Auswahl
    |--------------------------------------------------------------------------
    */
    if (status === 'group_running') {
        defaultView = 'groups';
    }

    if (status === 'ko_running' || status === 'finished') {
        defaultView = 'bracket';
    }

    showView(defaultView);
});

window.Alpine = Alpine;

Alpine.start();

// Gruppenphase: Speichern der Ergebnisse
import { initAutoSim } from './modules/autosim';

document.addEventListener('DOMContentLoaded', () => {

    initAutoSim();
    initPlayers();

    if (window.tournamentStatus === 'group_running') {
        initGroups();
    }

    if (window.tournamentStatus === 'ko_running' || window.tournamentStatus === 'finished') {
        initKnockout();
    }

});

window.showView = function (viewName) {

    localStorage.setItem('activeView', viewName);

    document.querySelectorAll('.view-section').forEach(el => {
        el.classList.add('hidden');
    });

    const activeView = document.getElementById(`view-${viewName}`);
    if (activeView) {
        activeView.classList.remove('hidden');
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {

        const isActive = btn.dataset.tab === viewName;

        btn.classList.toggle('text-white', isActive);
        btn.classList.toggle('border-b-2', isActive);
        btn.classList.toggle('border-emerald-400', isActive);

        btn.classList.toggle('text-gray-400', !isActive);
    });
};