import '@fortawesome/fontawesome-free/js/fontawesome';
import '@fortawesome/fontawesome-free/js/solid';
import '@fortawesome/fontawesome-free/js/regular';
import '@fortawesome/fontawesome-free/js/brands';

import { columnManager } from './columnManager.js';
window.columnManager = columnManager;

import { filtersManager } from './filtersManager.js';
window.filtersManager = filtersManager;

import Alpine from 'alpinejs';
import Tooltip from '@ryangjchandler/alpine-tooltip';
Alpine.plugin(Tooltip);
window.Alpine = Alpine;
Alpine.start();

import Chart from 'chart.js/auto';

window.Chart = Chart;
