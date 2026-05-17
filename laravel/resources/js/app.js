import './bootstrap';
import Sortable from 'sortablejs';
import Alpine from 'alpinejs';
import { board } from './board';

window.Sortable = Sortable;
window.board = board;

window.Alpine = Alpine;
Alpine.start();
