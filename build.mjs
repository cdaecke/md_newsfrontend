import { cpSync, rmSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const nm = (pkg) => resolve(__dirname, 'node_modules', pkg);
const js = (path) => resolve(__dirname, 'Resources/Public/Js', path);
const css = (path) => resolve(__dirname, 'Resources/Public/Css', path);

// jQuery slim
cpSync(nm('jquery/dist/jquery.slim.min.js'), js('jquery.slim.min.js'));

// flatpickr
cpSync(nm('flatpickr/dist/flatpickr.min.js'), js('flatpickr.min.js'));
cpSync(nm('flatpickr/dist/flatpickr.min.css'), css('flatpickr.min.css'));

// Parsley
cpSync(nm('parsleyjs/dist/parsley.min.js'), js('Parsley/parsley.min.js'));
cpSync(nm('parsleyjs/dist/i18n'), js('Parsley/i18n'), { recursive: true });

// TinyMCE — clear first, then copy fresh
rmSync(js('tinymce'), { recursive: true, force: true });
cpSync(nm('tinymce'), js('tinymce'), { recursive: true });

console.log('Assets built successfully.');
