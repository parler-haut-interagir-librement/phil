import { Application } from '@hotwired/stimulus';
import accordion from './controllers/accordion_controller.js';
import animatedHeadline from './controllers/animated_headline_controller.js';
import beforeAfter from './controllers/before_after_controller.js';
import carousel from './controllers/carousel_controller.js';
import colorPicker from './controllers/color_picker_controller.js';
import copyButton from './controllers/copy_button_controller.js';
import datePicker from './controllers/date_picker_controller.js';
import dialog from './controllers/dialog_controller.js';
import dropdownMenu from './controllers/dropdown_menu_controller.js';
import dropdownMenuSub from './controllers/dropdown_menu_sub_controller.js';
import fileInput from './controllers/file_input_controller.js';
import imagePreview from './controllers/image_preview_controller.js';
import lightbox from './controllers/lightbox_controller.js';
import numberInput from './controllers/number_input_controller.js';
import popover from './controllers/popover_controller.js';
import relativeTime from './controllers/relative_time_controller.js';
import select from './controllers/select_controller.js';
import tabs from './controllers/tabs_controller.js';
import tag from './controllers/tag_controller.js';
import tooltip from './controllers/tooltip_controller.js';
import tree from './controllers/tree_controller.js';

const application = new Application();

await application.start();

for (const [identifier, controller] of Object.entries({
    accordion,
    'animated-headline': animatedHeadline,
    'before-after': beforeAfter,
    carousel,
    'color-picker': colorPicker,
    'copy-button': copyButton,
    'date-picker': datePicker,
    dialog,
    'dropdown-menu': dropdownMenu,
    'dropdown-menu-sub': dropdownMenuSub,
    'file-input': fileInput,
    'image-preview': imagePreview,
    lightbox,
    'number-input': numberInput,
    popover,
    'relative-time': relativeTime,
    select,
    tabs,
    tag,
    tooltip,
    tree,
})) {
    application.register(identifier, controller);
}

document.documentElement.dataset.started = 'true';
