// The entry point of the browser suite's application: Stimulus started with the controllers of controllers.json.
import { startStimulusApp } from '@symfony/stimulus-bundle';

window.application = startStimulusApp();
window.changes = [];
document.addEventListener('phil--form-logic--logic:change', (event) => window.changes.push(event.detail));
document.documentElement.dataset.started = 'true';
