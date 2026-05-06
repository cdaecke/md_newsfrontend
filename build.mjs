import { cpSync, mkdirSync, readdirSync, rmSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const nm = (...parts) => resolve(__dirname, 'node_modules', ...parts);
const js = (...parts) => resolve(__dirname, 'Resources/Public/Js', ...parts);
const css = (...parts) => resolve(__dirname, 'Resources/Public/Css', ...parts);

// Copy a single file, creating parent directories as needed
const cp = (src, dst) => {
    mkdirSync(dirname(dst), { recursive: true });
    cpSync(src, dst);
};

// jQuery slim
cp(nm('jquery/dist/jquery.slim.min.js'), js('jquery.slim.min.js'));

// flatpickr
cp(nm('flatpickr/dist/flatpickr.min.js'), js('flatpickr.min.js'));
cp(nm('flatpickr/dist/flatpickr.min.css'), css('flatpickr.min.css'));

// Parsley
cp(nm('parsleyjs/dist/parsley.min.js'), js('Parsley/parsley.min.js'));
cpSync(nm('parsleyjs/dist/i18n'), js('Parsley/i18n'), { recursive: true });

// TinyMCE — clear first, then copy only production-needed files
rmSync(js('tinymce'), { recursive: true, force: true });

// Core
cp(nm('tinymce/tinymce.min.js'), js('tinymce/tinymce.min.js'));
// DOM model — required since TinyMCE 6
cp(nm('tinymce/models/dom/model.min.js'), js('tinymce/models/dom/model.min.js'));
// Silver theme (default)
cp(nm('tinymce/themes/silver/theme.min.js'), js('tinymce/themes/silver/theme.min.js'));
// Default icon set
cp(nm('tinymce/icons/default/icons.min.js'), js('tinymce/icons/default/icons.min.js'));

// Oxide skin (default UI skin)
cp(nm('tinymce/skins/ui/oxide/skin.min.css'), js('tinymce/skins/ui/oxide/skin.min.css'));
cp(nm('tinymce/skins/ui/oxide/content.min.css'), js('tinymce/skins/ui/oxide/content.min.css'));
cp(nm('tinymce/skins/ui/oxide/content.inline.min.css'), js('tinymce/skins/ui/oxide/content.inline.min.css'));
// Content area styles
cp(nm('tinymce/skins/content/default/content.min.css'), js('tinymce/skins/content/default/content.min.css'));

// Plugins — copy plugin.min.js for every available plugin
for (const plugin of readdirSync(nm('tinymce/plugins'))) {
    cp(nm('tinymce/plugins', plugin, 'plugin.min.js'), js('tinymce/plugins', plugin, 'plugin.min.js'));
}
// Emoticons plugin also needs its emoji data files
cp(nm('tinymce/plugins/emoticons/js/emojis.min.js'), js('tinymce/plugins/emoticons/js/emojis.min.js'));
cp(nm('tinymce/plugins/emoticons/js/emojiimages.min.js'), js('tinymce/plugins/emoticons/js/emojiimages.min.js'));

// Language packs — all locales for TinyMCE 7 (from tinymce-i18n)
cpSync(nm('tinymce-i18n/langs7'), js('tinymce/langs'), { recursive: true });

console.log('Assets built successfully.');
