<?php
    echo '<p>' . __('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '<br />' . PHP_EOL;

    if (!$compatible) {
        echo '<p>';
        echo '<strong>' . __('WARNING') . '</strong>' . '<br />' . PHP_EOL;
        echo __('You use an incompatible version of Omeka.') . '<br />' . PHP_EOL;
        echo __('Currently, two lines should be added in Omeka core (2.0) in order to allow a good functioning: see README.') . '<br />' . PHP_EOL;
        echo '</p>';
        echo '<br />';
        return;
   }

    echo __('When all options are set, files will be saved in "files / original / my_collection / item_identifier / original_filename.ext" instead of "files / original / hashed_filename.ext".') . '</p>' . PHP_EOL;
    echo '<p><strong>' . __('Warning') . '</strong>:<br />' . PHP_EOL;
    echo '<ul>' . PHP_EOL;
    echo '<li>' . __('Currently, changes in these settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Currently, no check is done on the name of files, so if two files have the same name and are in the same folder, the second will overwrite the first.') . '</li>' . PHP_EOL;
    echo '<li>' . __('Currently, no check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '</li>' . PHP_EOL;
    echo '</ul>' . PHP_EOL;
    echo '</p>' . PHP_EOL;
?>
<fieldset id="fieldset-collections"><legend><?php echo __('Collections'); ?></legend>
    <div class="field">
        <label for="archive_repertory_add_collection_folder">
            <?php echo __('Add a folder for each collection'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_add_collection_folder', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_add_collection_folder'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will add a subfolder in the "files" folder for each collection, for example "files/original/my_collection/". These subfolders will be named with the sanitized names below. New files will be stored inside them.'); ?>
            </p>
        </div>
    </div>
    <?php foreach (loop('collections') as $collection) {
        $id = 'archive_repertory_collection_folder_' . $collection->id; ?>
        <div class="field">
            <label for="<?php echo $id; ?>">
                <?php echo __('Folder name for "%s" (#%d)',
                    strip_formatting(metadata('collection', array('Dublin Core', 'Title'))),
                    $collection->id); ?>
            </label>
            <div class="inputs">
                <?php echo get_view()->formText($id, $collection_names[$collection->id], NULL); ?>
            </div>
        </div>
    <?php }?>
</fieldset>
<fieldset id="fieldset-items"><legend><?php echo __('Items'); ?></legend>
    <div class="field">
        <div id="archive_repertory_item_folder-label">
            <label for="archive_repertory_item_folder">
                <?php echo __('How do you want to name your item folder, if any?'); ?>
            </label>
            <p class="explanation">
                <?php echo __('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/".');
                echo ' ' . __('New files will be stored inside them. Old files will be moved when item will be updated.') . '<br />' . PHP_EOL;
                echo __("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
                echo ' ' . __('So recommended ids are "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title .') . '<br />' . PHP_EOL;
                echo __('If this identifier does not exists, the Omeka internal item id will be used.'); ?>
            </p>
        </div>
        <div>
            <select name="archive_repertory_item_folder" id="archive_repertory_item_folder">
                <option value="None"<?php if ($item_folder == 'None') { echo ' selected="selected"';} ?>><?php echo __("Don't add folder"); ?></option>
                <option value="id"<?php if ($item_folder == 'id') { echo ' selected="selected"';} ?>><?php echo __('Internal item id'); ?></option>
                <?php foreach ($listElements as $key => $value) {
                    echo '<option value="' . $key . '"';
                    if ($item_folder == $key) { echo ' selected="selected"';}
                    echo '>' . 'Dublin Core' . ' : ' . $value . '</option>' . PHP_EOL;
                } ?>
            </select>
        </div>
    </div>
    <div class="field">
        <label for="archive_repertory_item_identifier_prefix">
            <?php echo __('Prefix of item Dublin Core identifier to use'); ?>
        </label>
        <div class="inputs">
            <?php echo get_view()->formText('archive_repertory_item_identifier_prefix', get_option('archive_repertory_item_identifier_prefix'), null); ?>
            <p class="explanation">
                <?php echo __('If you choose to use the Dublin Core id, the name of folder of each new item will be the sanitized Dublin Core identifier with the selected prefix, for example "item:", "record:" or "doc:". Let empty to use simply the first item identifier.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <label for="archive_repertory_convert_folder_to_ascii">
            <?php echo __('Convert folder names to Ascii'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_convert_folder_to_ascii', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_convert_folder_to_ascii'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will convert every folder name to its Ascii equivalent. This option depends on the first one.') . '<br />' . PHP_EOL;
                echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                ?>
            </p>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-files"><legend><?php echo __('Files'); ?></legend>
    <div class="field">
        <label for="archive_repertory_keep_original_filename">
            <?php echo __('Keep original filenames'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_keep_original_filename', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_keep_original_filename'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep original filenames of uploaded files and will not hash it.') . '<br />' . PHP_EOL;
                echo '<strong>' . __('Warning') . '</strong>:</br>' . PHP_EOL;
                echo __('This option implies that all filenames are unique, in particular if this option is not combined with "Add collection folder" and "Add item folder" options.');
                ?>
            </p>
        </div>
    </div>
    <div class="field">
        <label for="archive_repertory_convert_filename_to_ascii">
            <?php echo __('Convert Unicode filenames to Ascii'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_convert_filename_to_ascii', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_convert_filename_to_ascii'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will convert every filename to its Ascii equivalent. This option depends on the first one.') . '<br />' . PHP_EOL;
                echo __('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.') . '<br />' . PHP_EOL;
                echo __('Omeka works fine with filenames with Unicode characters ("é", "ñ", "Å"...) when the character encoding of the server is the same than the web environment.');
                echo ' ' . __('If this is not the case, filenames should use only Ascii characters.') . '<br />' . PHP_EOL;
                if (empty($allowUnicode)) {
                    echo __('This server is compatible with Unicode.');
                }
                else {
                    echo '<strong>' . __('Warning') . '</strong>:</br>' . PHP_EOL;
                    echo __('This server is not fully compatible with Unicode:') . '<br />' . PHP_EOL;
                    if (isset($allowUnicode['cli'])) {
                        echo $allowUnicode['cli'] . '<br />' . PHP_EOL;
                        echo __('Usually, this is not a problem with this plugin and common plugins.');
                        echo ' ' . __('But if you use a plugin that calls a program via the command line of the server, filenames issues can occur.') . '<br />' . PHP_EOL;
                    }
                    if (isset($allowUnicode['fs'])) {
                        echo $allowUnicode['fs'] . '<br />' . PHP_EOL;
                        echo __('It is strongly recommanded to convert your filename to ascii.') . '<br />' . PHP_EOL;
                    }
                } ?>
            </p>
        </div>
    </div>
    <div class="field">
        <label for="archive_repertory_base_original_filename">
            <?php echo __('Keep only base of original filenames'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_base_original_filename', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_base_original_filename'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep only base of original filenames in metadata, not their path or url. This option is independant from previous ones.') . '<br />'; ?>
            </p>
        </div>
    </div>
</fieldset>
