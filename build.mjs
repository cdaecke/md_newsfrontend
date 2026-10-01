import { mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const nm = (...parts) => resolve(__dirname, 'node_modules', ...parts);
const js = (...parts) => resolve(__dirname, 'Resources/Public/Js', ...parts);
const css = (...parts) => resolve(__dirname, 'Resources/Public/Css', ...parts);

// Copy a single file, creating parent directories as needed.
// Uses read/write instead of fs.cpSync(), which fails with EACCES on Docker bind mounts (e.g. DDEV on macOS).
const cp = (src, dst) => {
    mkdirSync(dirname(dst), { recursive: true });
    writeFileSync(dst, readFileSync(src));
};

// Copy a directory recursively
const cpDir = (src, dst) => {
    for (const entry of readdirSync(src, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            cpDir(resolve(src, entry.name), resolve(dst, entry.name));
        } else {
            cp(resolve(src, entry.name), resolve(dst, entry.name));
        }
    }
};

// jQuery slim
cp(nm('jquery/dist/jquery.slim.min.js'), js('jquery.slim.min.js'));

// flatpickr
cp(nm('flatpickr/dist/flatpickr.min.js'), js('flatpickr.min.js'));
cp(nm('flatpickr/dist/flatpickr.min.css'), css('flatpickr.min.css'));

// Parsley
cp(nm('parsleyjs/dist/parsley.min.js'), js('Parsley/parsley.min.js'));
cpDir(nm('parsleyjs/dist/i18n'), js('Parsley/i18n'));

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
cpDir(nm('tinymce-i18n/langs7'), js('tinymce/langs'));

console.log('Assets built successfully.');
