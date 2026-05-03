import './bootstrap';

import Alpine from 'alpinejs';
import { adminPanel, masterLokasi, masterOlt } from './admin';
import { loginPage } from './login';

window.Alpine = Alpine;

Alpine.data('adminPanel', adminPanel);
Alpine.data('loginPage', loginPage);
Alpine.data('masterLokasi', () => masterLokasi());
Alpine.data('masterOlt', () => masterOlt());

Alpine.start();
