import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;
window.lucide = { createIcons: (options = {}) => createIcons({ icons, ...options }) };

Alpine.start();
