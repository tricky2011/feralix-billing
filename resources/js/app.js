import './bootstrap';

import Alpine from 'alpinejs';
import { adminPanel } from './admin';
import { loginPage } from './login';

window.Alpine = Alpine;

Alpine.data('adminPanel', adminPanel);
Alpine.data('loginPage', loginPage);

Alpine.start();
