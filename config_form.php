<?php
    echo '<p>' . __('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '<br />';
    echo __('When all options are set, files will be saved in "files / original / my_collection / item_identifier / original_filename.ext" instead of "files / original / hashed_filename.ext".') . '</p>';
    echo '<p><strong>' . __('Warning') . '</strong>:</br>';
    echo '<ul>';
    echo '<li>' . __('Currently, changes in these settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '</li>';
    echo '<li>' . __('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '</li>';
    echo '<li>' . __('Currently, no check is done on the name of files, so if two files have the same name and are in the same folder, the second will overwrite the first.') . '</li>';
    echo '<li>' . __('Currently, no check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '</li>';
    echo '<li>' . __('Currently, two lines should be added in Omeka core (2.0) in order to allow a good functioning: see README.') . '</li>';
    echo '<li>' . __('Omeka works fine with filenames with Unicode characters ("é", "ñ", "Å"...) when the character encoding of the server is the same than the web environment.') . '</li>';
    echo '<li>' . __('If this is not the case, filenames should use only Ascii characters.') . '</li>';
    echo '</ul>';
    echo '</p>';
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
                echo ' ' . __('Names of these subfolders will be sanitized. New files will be stored inside them. Old files will be moved when item will be updated.') . '<br />' . PHP_EOL;
                echo __("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
                echo ' ' . __('So recommended ids are "Dublin Core identifier" and "Internal item id".') . '<br />';
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
</fieldset>
<fieldset id="fieldset-files"><legend><?php echo __('Files'); ?></legend>
    <div class="field">
        <label for="archive_repertory_keep_original_filename">
            <?php echo __('Keep original name of attached files'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_keep_original_filename', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_keep_original_filename'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep original filenames.') . '<br />';
                echo '<strong>' . __('Warning') . '</strong>:</br>';
                echo __('This option implies that all filenames are unique, in particular if this option is not combined with "Add collection folder" and "Add item folder" options.') . '<br />';
                ?>
            </p>
        </div>
    </div>
    <div class="field">
        <label for="archive_repertory_base_original_filename">
            <?php echo __('Keep only base of original name of attached files'); ?>
        </label>
        <div class="inputs">
        <?php echo get_view()->formCheckbox('archive_repertory_base_original_filename', TRUE,
            array('checked' => (boolean) get_option('archive_repertory_base_original_filename'))); ?>
            <p class="explanation">
                <?php echo __('If checked, Omeka will keep only base of original filenames, not their path. This option depends on the previous one.') . '<br />';
                echo '<strong>' . __('Warning') . '</strong>:</br>';
                echo __('This option implies that all filenames are unique, in particular if this option is not combined with "Add collection folder" and "Add item folder" options.') . '<br />';
                ?>
            </p>
        </div>
    </div>
</fieldset>
