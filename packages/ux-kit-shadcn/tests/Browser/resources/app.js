import { Application } from '@hotwired/stimulus';
import animatedHeadline from './controllers/animated_headline_controller.js';
import beforeAfter from './controllers/before_after_controller.js';
import carousel from './controllers/carousel_controller.js';
import celebration from './controllers/celebration_controller.js';
import colorPicker from './controllers/color_picker_controller.js';
import copyButton from './controllers/copy_button_controller.js';
import fileInput from './controllers/file_input_controller.js';
import imagePreview from './controllers/image_preview_controller.js';
import lightbox from './controllers/lightbox_controller.js';
import numberInput from './controllers/number_input_controller.js';
import relativeTime from './controllers/relative_time_controller.js';
import tag from './controllers/tag_controller.js';
import tree from './controllers/tree_controller.js';

const application = new Application();

await application.start();

for (const [identifier, controller] of Object.entries({
    'animated-headline': animatedHeadline,
    'before-after': beforeAfter,
    carousel,
    celebration,
    'color-picker': colorPicker,
    'copy-button': copyButton,
    'file-input': fileInput,
    'image-preview': imagePreview,
    lightbox,
    'number-input': numberInput,
    'relative-time': relativeTime,
    tag,
    tree,
})) {
    application.register(identifier, controller);
}

document.documentElement.dataset.started = 'true';
