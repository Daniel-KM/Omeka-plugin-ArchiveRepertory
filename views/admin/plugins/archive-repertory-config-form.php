<?php
    echo __('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '<br />';
    echo __('See %s for more information.', '<a href="https://github.com/Daniel-KM/ArchiveRepertory">ReadMe</a>') . '<br />';
    echo '<br />';
    echo __('When all options are set, files will be saved in "files / original / my_collection / item_identifier / original_filename.ext" instead of "files / original / hashed_filename.ext".') . '<br />';
    echo '<p><strong>' . __('Warning') . '</strong></p>';
    echo '<ul>';
    echo '<li>' . __('Currently, changes in these settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '</li>';
    echo '<li>' . __('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '</li>';
    echo '<li>' . __('Currently, no check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '</li>';
    echo '</ul>';
    echo __('Omeka works fine with filenames with Unicode characters ("é", "ñ", "Å"...).');
    echo ' ' . __('If this is not the case, filenames should use only Ascii characters.') . '<br />';
    if (empty($allow_unicode)) {
        echo '<p>' . __('This server is compatible with Unicode.') . '</p>';
    } else {
        echo '<p><strong>' . __('Warning') . '</strong></p>';
        echo __('This server is not fully compatible with Unicode:') . '<br />';
        echo '<ul>';
        if (isset($allow_unicode['ascii'])) {
            echo '<li>' . $allow_unicode['ascii'] . '</li>';
            echo ' ' . __('Use only an Ascii character as first character of your filenames or set the option "Convert first character of filename".') . '<br />';
        }
        if (isset($allow_unicode['cli'])) {
            echo '<li>' . $allow_unicode['cli'] . '</li>';
            echo __('Usually, this is not a problem with this plugin and common plugins.');
            echo ' ' . __('But if you use a plugin that calls a program via the command line of the server, filenames issues can occur.') . '<br />';
        }
        if (isset($allow_unicode['fs'])) {
            echo '<li>' . $allow_unicode['fs'] . '</li>';
            echo __('It is strongly recommanded to convert your filename to ascii.') . '<br />';
        }
        echo '</ul>';
    }
?>
<fieldset id="fieldset-collections"><legend><?php echo __('Collections'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_repertory_collection_folder',
                __('How do you want to name your collection folder, if any?')); ?>
        </div>
        <div class="five columns omega">
            <div class="inputs">
                <?php
                $elementsCollection = get_table_options('Element', null, array(
                    'record_types' => array('Collection', 'All'),
                    'sort' => 'alphaBySet',
                ));
                // Remove the "Select Below" label.
                unset($elementsCollection['']);
                $elementsCollection = array(
                    '' => __('Don’t add folder'),
                    'id' => __('Internal collection id'),
                ) + $elementsCollection;
                echo $this->formSelect('archive_repertory_collection_folder',
                    get_option('archive_repertory_collection_folder'),
                    array(),
                    $elementsCollection);
                ?>
                <p class="explanation">
                    <?php echo __('If you choose to add a folder, Omeka will add subfolders for each collection in "files" folders, for example "files/original/collection_identifier/".');
                    echo ' ' . __('New files will be stored inside them. Old files will be moved when collection will be updated.') . '<br />';
                    echo __('Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.');
                    echo ' ' . __('So recommended ids are a specific metadata, "Dublin Core:Identifier", "Internal collection id" and eventually "Dublin Core:Title".') . '<br />';
                    echo __('If this identifier does not exists, the Omeka internal collection id will be used.'); ?>
                </p>
            </div>
            <div id="collection-prefix" class="field">
                <?php echo $this->formLabel('archive_repertory_collection_prefix',
                    __('Prefix for Collection')); ?>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Choose a prefix, for example "collection:", "record:" or "doc:", to select the appropriate metadata when they are multiple.');
                        echo ' ' . __('Let empty to use simply the first one.'); ?>
                    </p>
                    <?php echo $this->formText('archive_repertory_collection_prefix', get_option('archive_repertory_collection_prefix'), null); ?>
                </div>
            </div>
            <div id="collection-ascii" class="field">
                <?php echo $this->formLabel('archive_repertory_collection_convert',
                    __('Convert collection names')); ?>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>
                    <?php echo $this->formRadio('archive_repertory_collection_convert',
                        get_option('archive_repertory_collection_convert'),
                        null,
                        array(
                            'keep' => __('Keep name as it')
                                . (isset($allow_unicode['ascii'])
                                    ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                                    : ''),
                            'spaces' => __('Convert spaces to underscores'),
                            'first letter' => __('Convert first letter only'),
                            'first and spaces' => __('Convert first letter and spaces'),
                            'full' => __('Full conversion to Ascii')
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
            <?php echo $this->formLabel('archive_repertory_item_folder',
                __('How do you want to name your item folder, if any?')); ?>
        </div>
        <div class="five columns omega">
            <div class="inputs">
                <?php
                $elementsItem = get_table_options('Element', null, array(
                    'record_types' => array('Item', 'All'),
                    'sort' => 'alphaBySet',
                ));
                // Remove the "Select Below" label.
                unset($elementsItem['']);
                $elementsItem = array(
                    '' => __('Don’t add folder'),
                    'id' => __('Internal item id'),
                ) + $elementsItem;
                echo $this->formSelect('archive_repertory_item_folder',
                    get_option('archive_repertory_item_folder'),
                    array(),
                    $elementsItem);
                ?>
                <p class="explanation">
                    <?php echo __('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/".');
                    echo ' ' . __('New files will be stored inside them. Old files will be moved when item will be updated.') . '<br />';
                    echo __('Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.');
                    echo ' ' . __('So recommended ids are a specifc metadata, "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title".') . '<br />';
                    echo __('If this identifier does not exists, the Omeka internal item id will be used.'); ?>
                </p>
            </div>
            <div id="item-prefix" class="field">
                <?php echo $this->formLabel('archive_repertory_item_prefix',
                    __('Prefix for Item')); ?>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.');
                        echo ' ' . __('Let empty to use simply the first one.'); ?>
                    </p>
                    <?php echo $this->formText('archive_repertory_item_prefix', get_option('archive_repertory_item_prefix'), null); ?>
                </div>
            </div>
            <div id="item-ascii" class="field">
                <?php echo $this->formLabel('archive_repertory_item_convert',
                    __('Convert folder names')); ?>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo __('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>
                    <?php echo $this->formRadio('archive_repertory_item_convert',
                        get_option('archive_repertory_item_convert'),
                        null,
                        array(
                            'keep' => __('Keep name as it')
                                . (isset($allow_unicode['ascii'])
                                    ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                                    : ''),
                            'spaces' => __('Convert spaces to underscores'),
                            'first letter' => __('Convert first letter only'),
                            'first and spaces' => __('Convert first letter and spaces'),
                            'full' => __('Full conversion to Ascii')
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
            <?php echo $this->formLabel('archive_repertory_file_convert',
                __('Convert filenames')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formRadio('archive_repertory_file_convert',
                get_option('archive_repertory_file_convert'),
                null,
                array(
                    'keep' => __('Keep name as it')
                        . (isset($allow_unicode['ascii'])
                            ? ' ' . __('(not recommended because your server is not fully compatible with Unicode)')
                            : ''),
                    'spaces' => __('Convert spaces to underscores'),
                    'first letter' => __('Convert first letter only'),
                    'first and spaces' => __('Convert first letter and spaces'),
                    'full' => __('Full conversion to Ascii')
                        . ((isset($allow_unicode['cli']) || isset($allow_unicode['fs']))
                            ? ' (' . __('recommended because your server is not fully compatible with Unicode') . ')'
                            : ''),
                    'hash' => __('Hash filename (default Omeka)'),
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
            <?php echo $this->formLabel('archive_repertory_file_base_original_name',
                __('Keep only base of original filenames')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formCheckbox('archive_repertory_file_base_original_name', true,
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
            <?php echo $this->formLabel('archive_repertory_derivative_folders',
                __('Other derivative folders')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formText('archive_repertory_derivative_folders', get_option('archive_repertory_derivative_folders'), null); ?>
            <p class="explanation">
                <?php echo __('By default, Omeka support three derivative folders: "fullsize", "thumbnails" and "square_thumbnails".');
                echo ' ' . __('You can add other ones if needed (comma-separated values, like "special_thumbnails, circles").');
                echo ' ' . __('Folder names should be relative to the files dir "%s".', $local_storage);
                echo '<br />';
                echo ' ' . __('If a plugin does not use a standard derivative extension (for example ".jpg" for images), you should specified it just after the folder name, separated with a pipe "|", for example "zoom_tiles|_zdata, circles".');
                echo '<br />';
                echo ' ' . __('When this option is used, you should not change collection or item identifier and, at the same time, use a feature of the plugin that create derivative files.');
                echo ' ' . __('In that case, divide your process and change collection or identifier, save item, then use your plugin.')
                ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-move-process"><legend><?php echo __('Process'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_repertory_move_process',
                __('Used process')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $this->formRadio('archive_repertory_move_process',
                get_option('archive_repertory_move_process'),
                null,
                array(
                    'internal' => __('Omeka internal'),
                    'direct' => __('Php directly'),
                )); ?>
            <p class="explanation">
                <?php echo __('By default, the process uses the default internal functions of Omeka to process files.'); ?>
                <?php echo __('If needed, the standard functions of PHP can be used.'); ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-max-download"><legend><?php echo __('Maximum downloads'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_repertory_download_max_free_download',
                __('Maximum size without captcha')); ?>
        </div>
        <div class='inputs five columns omega'>
            <?php echo $this->formText('archive_repertory_download_max_free_download', get_option('archive_repertory_download_max_free_download'), null); ?>
            <p class="explanation">
                <?php echo __('Above this size, a captcha will be added to avoid too many downloads from a user.'); ?>
                <?php echo ' ' . __('Set a very high size to allow all files to be downloaded.'); ?>
                <?php echo ' ' . __('Note that the ".htaccess" and eventually "routes.ini" files should be updated too.'); ?>
            </p>
        </div>
    </div>
    <div class='field'>
        <div class="two columns alpha">
            <?php echo $this->formLabel('archive_repertory_legal_text',
                __('Legal agreement')); ?>
        </div>
        <div class='inputs five columns omega'>
            <div class='input-block'>
                <?php echo $this->formTextarea(
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
                    <?php echo ' ' . __('Let empty if you don’t want to use a legal agreement.'); ?>
                </p>
            </div>
        </div>
    </div>
</fieldset>
<?php echo js_tag('vendor/tiny_mce/tiny_mce'); ?>
<script type="text/javascript">
    var dropCollection = document.getElementById("archive_repertory_collection_folder");
    var fieldCollectionPrefix = document.getElementById("collection-prefix");
    var fieldCollectionAscii = document.getElementById("collection-ascii");
    dropCollection.onclick = function() {
        if (dropCollection.value == "" || dropCollection.value == "id"){
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "none";
        } else {
            fieldCollectionPrefix.style.display = "block";
            fieldCollectionAscii.style.display = "block";
        }
    }

    var dropItem = document.getElementById("archive_repertory_item_folder");
    var fieldItemPrefix = document.getElementById("item-prefix");
    var fieldItemAscii = document.getElementById("item-ascii");
    dropItem.onclick = function() {
        if (dropItem.value == "" || dropItem.value == "id"){
            fieldItemPrefix.style.display = "none";
            fieldItemAscii.style.display = "none";
        } else {
            fieldItemPrefix.style.display = "block";
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
