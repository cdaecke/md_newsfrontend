
plugin.tx_mdnewsfrontend_newsfe {
    view {
        # cat=plugin.tx_mdnewsfrontend_newsfe/file; type=string; label=Path to template root (FE)
        templateRootPath = EXT:md_newsfrontend/Resources/Private/Templates/
        # cat=plugin.tx_mdnewsfrontend_newsfe/file; type=string; label=Path to template partials (FE)
        partialRootPath = EXT:md_newsfrontend/Resources/Private/Partials/
        # cat=plugin.tx_mdnewsfrontend_newsfe/file; type=string; label=Path to template layouts (FE)
        layoutRootPath = EXT:md_newsfrontend/Resources/Private/Layouts/
    }

    settings {
        # cat=plugin.tx_mdnewsfrontend_newsfe/a1; type=int+; label=Uid of storage page for news records. On this page all news which are created in the frontend will be saved.
        storagePid = 0

        # cat=plugin.tx_mdnewsfrontend_newsfe/a1; type=int+; label=Show categories of category tree which have this parent uid in the select box of the form. If no parent category was defined, there will be nothing to select in the frontend.
        parentCategory = 

        # cat=plugin.tx_mdnewsfrontend_newsfe/aa1; type=string; label=Path in fileadmin where to store frontend file uploads. Path will be extended by a folder named by user uid.
        uploadPath = md_newsfrontend

        # cat=plugin.tx_mdnewsfrontend_newsfe/aa2; type=string; label=Comma separated file extensions, which are allowed for file upload in field fal_media.
        allowed_falMedia = gif,jpg,jpeg,png

        # cat=plugin.tx_mdnewsfrontend_newsfe/aa3; type=string; label=Comma separated file extensions, which are allowed for file upload in field fal_related_files.
        allowed_falRelatedFiles = pdf

        # cat=plugin.tx_mdnewsfrontend_newsfe/b1; type=boolean; label=Load jQuery library. Use this, if you don't habe jQuery loaded already.
        jquery = 0

        # cat=plugin.tx_mdnewsfrontend_newsfe/c1; type=boolean; label=Load flatpickr for picking of datetime. This is used to enrich fields with a datepicker.
        flatpickr = 1

        # cat=plugin.tx_mdnewsfrontend_newsfe/d1; type=boolean; label=Load Parsley.js library. This is used for validating form fields.
        parsleyjs = 1

        # cat=plugin.tx_mdnewsfrontend_newsfe/d2; type=string; label=Language for Parsley.js error messages (eg. de).
        parsleyjsLang = en

        # cat=plugin.tx_mdnewsfrontend_newsfe/e1; type=boolean; label=Load tinymce library. This is used for rich text editing in textareas.
        tinymce = 1

        # cat=plugin.tx_mdnewsfrontend_newsfe/e2; type=string; label=Language for tinymce
        tinymceLang = en
    }
}
