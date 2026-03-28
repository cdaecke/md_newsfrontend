# TYPO3 Extension ``md_newsfrontend``

This extension enables a frontend user to add ``ext:news``-records. Beside adding content for the normal text fields like ``title``, ``teaser``, ``bodytext`` it is also possible to upload an image and a file download as well as selecting a category.

Templates are ready to use with the [bootstrap framework](https://getbootstrap.com/) and icons will be shown, if you have [Font Awesome](https://fontawesome.com/) icon set included in your project. Validation of input is done in the frontend, rich text editing enabled for the textarea and a date picker in place for the archive date.

## Requirements

- TYPO3 ^13.4 || ^14.0
- ext:news ^11.0 || ^12.0 || ^13.0 || ^14.0

## Installation

- Install the extension via Composer or the Extension Manager
- Add the Site Set **"News Frontend" (`mediadreams/md-newsfrontend`)** to your site configuration (`config/sites/<yoursite>/config.yaml`)
- Configure the extension settings directly in the site configuration GUI or via `settings.yaml`

> **Important:** If a TypoScript template record exists on your root page, make sure the checkboxes **"Clear constants"** and **"Clear setup"** are **unchecked**. When checked, they wipe all TypoScript loaded by Site Sets before the template record is processed, which results in empty extension settings.

### Legacy TypoScript include

If you prefer the classic approach, you can still include the TypoScript manually from `EXT:md_newsfrontend/Configuration/TypoScript/` and override constants as needed.

## Usage

- Add the pluign ``News frontend`` on a page, which is restricted by the frontend user login
- Select a storage page in the plugin-tab in the field ``Record Storage Page``
- Now a frontend user is able to add, edit and delete own records

## PSR-14 Events

Following PSR-14 events are available:

- `Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent`: Called just before saving a new record
- `Mediadreams\MdNewsfrontend\Event\CreateActionAfterPersistEvent`: Called after a new record was saved (new record Id is available)
- `Mediadreams\MdNewsfrontend\Event\UpdateActionBeforeSaveEvent`: Called just before an existing record will be updated
- `Mediadreams\MdNewsfrontend\Event\DeleteActionBeforeDeleteEvent`: Called just before a record will be deleted
- `Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent`: Called during file upload validation to modify the allowed MIME types for a given file extension

### Register an event

Add following lines in `Configuration/Services.yaml` of your own extension:

```yaml
services:
  Vendor\Extension\EventListener\MyListener:
    tags:
      - name: event.listener
        identifier: 'ext-mdnewsfrontend/createActionBeforeSaveEvent'
        method: 'enrichNews'
        event: Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent
```

Add the class `Vendor\Extension\EventListener\MyListener` with the method `enrichNews` in your extension:

```php
namespace Vendor\Extension\EventListener;

use Mediadreams\MdNewsfrontend\Event\CreateActionBeforeSaveEvent;

final class MyListener
{
    public function enrichNews(CreateActionBeforeSaveEvent $obj)
    {
        // Get news object
        $news = $obj->getNews();
        $news->setTeaser('Set some teaser...');

        // Get NewsController
        $newsController = $obj->getNewsController();
    }
}
```

### ModifyAllowedMimeTypesEvent

The `ModifyAllowedMimeTypesEvent` is dispatched during file upload validation and allows you to extend or override the built-in MIME type map. This is useful when you need to permit file extensions that are not covered by the default configuration (e.g. `svg`, `heic`, `odt`).

The event provides the following methods:

- `getExtension(): string` — the file extension being validated (lowercase, e.g. `'svg'`)
- `getMimeTypes(): array` — the current list of allowed MIME types for this extension
- `setMimeTypes(array $mimeTypes): void` — replace the entire list of allowed MIME types
- `addMimeType(string $mimeType): void` — append a single MIME type to the existing list

**Example:** Allow SVG uploads by adding a listener in `Configuration/Services.yaml`:

```yaml
services:
  Vendor\Extension\EventListener\AddSvgMimeType:
    tags:
      - name: event.listener
        identifier: 'my-extension/add-svg-mime-type'
        event: Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent
```

```php
namespace Vendor\Extension\EventListener;

use Mediadreams\MdNewsfrontend\Event\ModifyAllowedMimeTypesEvent;

final class AddSvgMimeType
{
    public function __invoke(ModifyAllowedMimeTypesEvent $event): void
    {
        if ($event->getExtension() === 'svg') {
            $event->setMimeTypes(['image/svg+xml']);
        }
    }
}
```

> **Note:** Extensions not present in the MIME type map skip the MIME check entirely. To enforce MIME validation for a new extension, add it via this event. To disable MIME validation for an existing extension, call `$event->setMimeTypes([])` for that extension.

## Bugs and Known Issues
If you find a bug, it would be nice if you add an issue on [Github](https://github.com/cdaecke/md_newsfrontend/issues).

# THANKS

Thanks a lot to all who make this outstanding TYPO3 project possible!

## Credits

- Extension icon was copied from [ext:news](https://github.com/georgringer/news) and enriched with a pen from [Font Awesome](https://fontawesome.com/icons/pencil-alt?style=solid).
- Thanks to [Parsley.js](http://parsleyjs.org), which I use for validating the form in the frontend.
- Thanks to [tiny](https://www.tiny.cloud), which I use as rich text editor in the frontend.
- Thanks to [flatpickr](https://flatpickr.js.org/), which I use as date picker in the frontend.
- And last but not least thanks to the guys at [jQuery](http://jquery.com/).
