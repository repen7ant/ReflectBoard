import './bootstrap';
import Sortable from 'sortablejs';
import Alpine from 'alpinejs';
import { board } from './board';
import { donePage } from './done';
import { analyticsPage } from './analytics';

window.Sortable = Sortable;
window.board = board;
window.donePage = donePage;
window.analyticsPage = analyticsPage;

window.Alpine = Alpine;
Alpine.start();
