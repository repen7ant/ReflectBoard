import './bootstrap';
import Sortable from 'sortablejs';
import Alpine from 'alpinejs';
import { board } from './board';
import { donePage } from './done';
import { analyticsPage } from './analytics';
import { fab } from './fab';

window.Sortable = Sortable;
window.board = board;
window.donePage = donePage;
window.analyticsPage = analyticsPage;
window.fab = fab;

window.Alpine = Alpine;
Alpine.start();
