import './bootstrap';
import Sortable from 'sortablejs';
import Alpine from 'alpinejs';
import { board } from './board';
import { donePage } from './done';

window.Sortable = Sortable;
window.board = board;
window.donePage = donePage;

window.Alpine = Alpine;
Alpine.start();
