<?php
    echo '<p>' . __('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '</p>' . PHP_EOL;

    if (!$is_compatible) {
        echo '<p><strong>' . __('Warning') . '</strong></p>' . PHP_EOL;
        echo __('You use an incompatible version of Omeka.') . '<br />' . PHP_EOL;
        echo __('Upgrade to Omeka 2.04 or above or add two lines in Omeka core in order to allow a good functioning (see "readme.md").') . '<br />' . PHP_EOL;
        echo '<br />';
        return;
    }

    echo __('When all options are set, files will be saved in "files / original / my_collection / item_identifier / original_filename.ext" instead of "files / original / hashed_filename.ext".') . '<br />' . PHP_EOL;
    echo __('Omeka works fine with filenames with Unicode characters ("é", "ñ", "Å"...). In case of issues, see "readme.md".');
    echo ' ' . __('If this is not the case, filenames should use only Ascii characters.') . '<br />' . PHP_EOL;
    if (empty($allow_unicode)) {
        echo '<p>' . __('This server is compatible with Unicode.') . '</p>';
    }
    else {
        echo '<p><strong>' . __('Warning') . '</strong></p>' . PHP_EOL;
        echo __('This server is not fully compatible with Unicode:') . '<br />' . PHP_EOL;
        echo '<ul>';
        if (isset($allow_unicode['ascii'])) {
            echo '<li>' . $allow_unicode['ascii'] . '</li>' . PHP_EOL;
            echo ' ' . __('Use only an Ascii character as first character of your filenames or set the option "Convert first character of filename".') . '<br />' . PHP_EOL;
        }
        if (isset($allow_unicode['cli'])) {
            echo '<li>' . $allow_unicode['cli'] . '</li>' . PHP_EOL;
            echo __('Usually, this is not a problem with this plugin and common plugins.');
            echo ' ' . __('But if you use a plugin that calls a program via the command line of the server, filenames issues can occur.') . '<br />' . PHP_EOL;
        }
        if (isset($allow_unicode['fs'])) {
            echo '<li>' . $allow_unicode['fs'] . '</li>' . PHP_EOL;
            echo __('It is strongly recommanded to convert your filename to ascii.') . '<br />' . PHP_EOL;
        }
        echo '</ul>' . PHP_EOL;
    }

    echo '<p><strong>' . __('Warning') . '</strong></p>' . PHP_EOL;
    echo '<ul>' . PHP_EOL;
    echo '<li>' . __('Currently, changes in these settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Currently, no check is done on the name of files, so if two files have the same name and are in the same folder, the second will overwrite the first.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Currently, no check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '</li>' . PHP_EOL;
    echo '</ul>' . PHP_EOL;
?>
<fieldset id="fieldset-collections"><legend><?php echo __('Collections'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_collection_folder">
                <?php echo __('How do you want to name your collection folder, if any?'); ?>
            </label>
        </div>
        <div class="five columns omega">
            <div class="inputs">
                <?php
                $elements = get_table_options('Element', null, array(
                    'record_types' => array('Item', 'All'),
                    'sort' => 'alphaBySet',
                ));
                // Remove the "Select Below" label.
                unset($elements['']);
                $elementsCollection = array(
                    'None' => __("Don't add folder"),
                    'String' => __('Specific string'),
                    'id' =>__('Internal collection id'),
                ) + $elements;
                echo $this->formSelect('archive_repertory_collection_folder',
                    $collection_folder,
                    array(),
                    $elementsCollection);
                ?>
                <p class="explanation">
                    <?php echo __('If you choose to add a folder, Omeka will add subfolders for each collection in "files" folders, for example "files/original/collection_identifier/".');
                    echo ' ' . __('New files will be stored inside them. Old files will be moved when collection will be updated.') . '<br />' . PHP_EOL;
                    echo __("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
                    echo ' ' . __('So recommended ids are "Dublin Core Identifier", "Internal collection id" and eventually "Dublin Core Title".');
                    echo ' ' . __('You can use a specific string too, below.') . '<br />' . PHP_EOL;
                    echo __('If this identifier does not exists, the Omeka internal item id will be used.'); ?>
                </p>
            </div>
            <div id='collection-list'>
                <?php foreach (loop('collections') as $collection) :
                $id = 'archive_repertory_collection_folder_' . $collection->id; ?>
                <div class="field">
                    <label for="<?php echo $id; ?>">
                        <?php echo __('Folder name for "%s" (#%d)',
                            strip_formatting(metadata('collection', array('Dublin Core', 'Title'))),
                            $collection->id); ?>
                    </label>
                   <div class="inputs">
                        <?php echo get_view()->formText($id, $collection_names[$collection->id], null); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="collection-prefix" class="field">
                <label for="archive_repertory_collection_identifier_prefix">
                    <?php echo __('Prefix of collection Dublin Core identifier to use'); ?>
                </label>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Choose a prefix, for example "collection:", "record:" or "doc:", to select the appropriate Dublin Core identifier.');
                        echo ' ' . __('Let empty to use simply the first Dublin Core identifier.'); ?>
                    </p>
                    <?php echo get_view()->formText('archive_repertory_collection_identifier_prefix', get_option('archive_repertory_collection_identifier_prefix'), null); ?>
                </div>
            </div>
            <div id="collection-ascii" class="field">
                <label for="archive_repertory_collection_convert_name">
                    <?php echo __('Convert collection names'); ?>
                </label>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>
                    <?php echo get_view()->formRadio('archive_repertory_collection_convert_name',
                        get_option('archive_repertory_collection_convert_name'),
                        null,
                        array(
                            'Keep name' => __('Keep name as it')
                                . (isset($allow_unicode['ascii'])
                                    ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                                    : ''),
                            'Spaces' => __('Convert spaces to underscores'),
                            'First letter' => __('Convert first letter only'),
                            'First and spaces' => __('Convert first letter and spaces'),
                            'Full' => __('Full conversion to Ascii')
                                . ((isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                                    ? ' (' . __('recommended because your server is not fully compatible with Unicode') . ')'
                                    : ''),
                        )); ?>
                </div>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-items"><legend><?php echo __('Items'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_item_folder">
                <?php echo __('How do you want to name your item folder, if any?'); ?>
            </label>
        </div>
        <div class="five columns omega">
            <div class="inputs">
                <?php
                $elementsItem = array(
                    'None' => __("Don't add folder"),
                    'id' =>__('Internal item id'),
                ) + $elements;
                echo $this->formSelect('archive_repertory_item_folder',
                    $item_folder,
                    array(),
                    $elementsItem);
                ?>
                <p class="explanation">
                    <?php echo __('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/".');
                    echo ' ' . __('New files will be stored inside them. Old files will be moved when item will be updated.') . '<br />' . PHP_EOL;
                    echo __("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
                    echo ' ' . __('So recommended ids are "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title".') . '<br />' . PHP_EOL;
                    echo __('If this identifier does not exists, the Omeka internal item id will be used.'); ?>
                </p>
            </div>
            <div id="item-prefix" class="field">
                <label for="archive_repertory_item_identifier_prefix">
                    <?php echo __('Prefix of item Dublin Core identifier to use'); ?>
                </label>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate Dublin Core identifier.');
                        echo ' ' . __('Let empty to use simply the first Dublin Core identifier.'); ?>
                    </p>
                    <?php echo get_view()->formText('archive_repertory_item_identifier_prefix', get_option('archive_repertory_item_identifier_prefix'), null); ?>
                </div>
            </div>
            <div id="item-ascii" class="field">
                <label for="archive_repertory_item_convert_name">
                    <?php echo __('Convert folder names'); ?>
                </label>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>
                    <?php echo get_view()->formRadio('archive_repertory_item_convert_name',
                        get_option('archive_repertory_item_convert_name'),
                        null,
                        array(
                            'Keep name' => __('Keep name as it')
                                . (isset($allow_unicode['ascii'])
                                    ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                                    : ''),
                            'Spaces' => __('Convert spaces to underscores'),
                            'First letter' => __('Convert first letter only'),
                            'First and spaces' => __('Convert first letter and spaces'),
                            'Full' => __('Full conversion to Ascii')
                                . ((isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                                    ? ' (' . __('recommended because your server is not fully compatible with Unicode') . ')'
                                    : ''),
                        )); ?>
                </div>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-files"><legend><?php echo __('Files'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_file_keep_original_name">
                <?php echo __('Keep original filenames'); ?>
            </label>
        </div>
        <div class="inputs five columns omega">
            <?php echo get_view()->formCheckbox('archive_repertory_file_keep_original_name', true,
                array('checked' => (boolean) get_option('archive_repertory_file_keep_original_name'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep original filenames of uploaded files and will not hash it.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_file_convert">
                <?php echo __('Convert filenames'); ?>
            </label>
        </div>
        <div class="inputs five columns omega">
            <?php echo get_view()->formRadio('archive_repertory_file_convert_name',
                get_option('archive_repertory_file_convert_name'),
                null,
                array(
                    'Keep name' => __('Keep name as it')
                        . (isset($allow_unicode['ascii'])
                            ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                            : ''),
                    'Spaces' => __('Convert spaces to underscores'),
                    'First letter' => __('Convert first letter only'),
                    'First and spaces' => __('Convert first letter and spaces'),
                    'Full' => __('Full conversion to Ascii')
                        . ((isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                            ? ' (' . __('recommended because your server is not fully compatible with Unicode') . ')'
                            : ''),
                )); ?>
            <p class="explanation">
                <?php echo __('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every file to its Ascii equivalent (or only the first letter).');
                echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_file_base_original_name">
                <?php echo __('Keep only base of original filenames'); ?>
            </label>
        </div>
        <div class="inputs five columns omega">
            <?php echo get_view()->formCheckbox('archive_repertory_file_base_original_name', true,
                array('checked' => (boolean) get_option('archive_repertory_file_base_original_name'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep only the base of original filenames in metadata, not their path or url. This option is independant from previous ones.') . '<br />'; ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-derivative-folders"><legend><?php echo __('Special derivative folders'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_derivative_folders">
                <?php echo __('Other derivative folders'); ?>
            </label>
        </div>
        <div class="inputs five columns omega">
            <?php echo get_view()->formText('archive_repertory_derivative_folders', get_option('archive_repertory_derivative_folders'), null); ?>
            <p class="explanation">
                <?php echo __('By default, Omeka support three derivative folders: "fullsize", "thumbnails" and "square_thumbnails".');
                echo ' ' . __('You can add other ones if needed (comma-separated values, like "special_thumbnails, circles").');
                echo ' ' . __('Folder names should be relative to the files dir "%s".', FILES_DIR);
                echo '<br />' . PHP_EOL;
                echo ' ' . __('If a plugin does not use a standard derivative extension (for example ".jpg" for images), you should specified it just after the folder name, separated with a pipe "|", for example "zoom_tiles|_zdata, circles".');
                echo '<br />' . PHP_EOL;
                echo ' ' . __('When this option is used, you should not change collection or item identifier and, at the same time, use a feature of the plugin that create derivative files.');
                echo ' ' . __('In that case, divide your process and change collection or identifier, save item, then use your plugin.')
                ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-max-download"><legend><?php echo __('Maximum downloads by user'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <label for="archive_repertory_warning_max_size_download">
                <?php echo __('Maximum size without captcha'); ?>
            </label>
        </div>
        <div class='inputs five columns omega'>
            <?php echo get_view()->formText('archive_repertory_warning_max_size_download', get_option('archive_repertory_warning_max_size_download'), null); ?>
            <p class="explanation">
                <?php echo __('Above this size, a captcha will be added to avoid too many downloads from a user.'); ?>
                <?php echo ' ' . __('Set a very high size to allow all files to be downloaded.'); ?>
                <?php echo ' ' . __('Note that the ".htaccess" and eventually "routes.ini" files should be updated too.'); ?>
            </p>
        </div>
    </div>
    <div class='field'>
        <div class="two columns alpha">
            <label><?php echo __('Legal agreement'); ?></label>
        </div>
        <div class='inputs five columns omega'>
            <div class='input-block'>
                <?php echo get_view()->formTextarea(
                    'archive_repertory_legal_text',
                    get_option('archive_repertory_legal_text'),
                    array(
                        'rows' => 5,
                        'cols' => 60,
                        'class' => array('textinput', 'html-editor'),
                     )
                ); ?>
                <p class="explanation">
                    <?php echo __('This text will be shown beside the legal checkbox to download a file.'); ?>
                    <?php echo ' ' . __("Let empty if you don't want to use a legal agreement."); ?>
                </p>
            </div>
        </div>
    </div>
</fieldset>
<?php echo js_tag('vendor/tiny_mce/tiny_mce'); ?>
<script type="text/javascript">
    var dropCollection = document.getElementById("archive_repertory_collection_folder");
    var fieldCollectionList = document.getElementById("collection-list");
    var fieldCollectionPrefix = document.getElementById("collection-prefix");
    var fieldCollectionAscii = document.getElementById("collection-ascii");
    dropCollection.onclick = function() {
        if (dropCollection.value == "None"){
            fieldCollectionList.style.display = "none";
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "none";
        } else if (dropCollection.value == "id") {
            fieldCollectionList.style.display = "none";
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "none";
        } else if (dropCollection.value == "String") {
            fieldCollectionList.style.display = "block";
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "block";
        } else if (dropCollection.value == <?php echo $dublincore_identifier; ?>) {
            fieldCollectionList.style.display = "none";
            fieldCollectionPrefix.style.display = "block";
            fieldCollectionAscii.style.display = "block";
        } else {
            fieldCollectionList.style.display = "none";
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "block";
        }
    }

    var dropItem = document.getElementById("archive_repertory_item_folder");
    var fieldItemPrefix = document.getElementById("item-prefix");
    var fieldItemAscii = document.getElementById("item-ascii");
    dropItem.onclick = function() {
        if (dropItem.value == "None"){
            fieldItemPrefix.style.display = "none";
            fieldItemAscii.style.display = "none";
        } else if (dropItem.value == "id") {
            fieldItemPrefix.style.display = "none";
            fieldItemAscii.style.display = "none";
        } else if (dropItem.value == <?php echo $dublincore_identifier; ?>) {
            fieldItemPrefix.style.display = "block";
            fieldItemAscii.style.display = "block";
        } else {
            fieldItemPrefix.style.display = "none";
            fieldItemAscii.style.display = "block";
        }
    }

    jQuery(document).ready(function () {
        dropCollection.onclick();
        dropItem.onclick();
    });
    jQuery(window).load(function () {
      Omeka.wysiwyg({
        mode: 'specific_textareas',
        editor_selector: 'html-editor'
      });
    });
</script>
