// Everything is served by the app itself (no CDN): the CSP can stay strict.
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '@fontsource/barlow/400.css';
import '@fontsource/barlow/500.css';
import '@fontsource/barlow/600.css';
import '@fontsource/barlow/700.css';
import '@fontsource/barlow-condensed/600.css';
import '@fontsource/barlow-condensed/700.css';
import '@fontsource/barlow-condensed/800.css';
import '../css/app.css';
import 'bootstrap';

import { initForms } from './lib/forms';
import { initNfcTab } from './lib/nfc';
import { showFlash } from './lib/toast';

// One module per page (<body data-page="...">), loaded only where used.
const pages = {
    'item-create': () => import('./pages/item-create'),
    'item-edit': () => import('./pages/item-form'),
    'item-show': () => import('./pages/item-show'),
    'item-ask': () => import('./pages/item-ask'),
    'location-show': () => import('./pages/location-show'),
    'tag-form': () => import('./pages/tag-form'),
};

initForms();
initNfcTab();
showFlash();

const page = pages[document.body.dataset.page];
if (page) page().then((m) => m.default());
