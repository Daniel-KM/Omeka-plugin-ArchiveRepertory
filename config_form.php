<?php
    echo '<p>' . __('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '<br />';
    echo __('If all options are chosen, files will be saved in "archive / files / my_collection / item_identifier / original_filename.ext" instead of "archive / files / hashed_filename.ext".') . '</p>';
    echo '<p><strong>' . __('Warning') . '</strong>:</br>';
    echo '-' . __('Changes in this settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '<br />';
    echo '-' . __('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '<br />';
    echo '-' . __('No check is done on the name of files, so if two files have the same name, the second will overwrite the first.') . '<br />';
    echo '-' . __('No check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '<br />';
    echo '-' . __('Currently, two lines need to be modified on Omeka (1.5.3) in order to allow a good functioning (for names with a dot "." and for use of subfolders): see README.') . '<br />';
    echo '</p>';
?>
<div class="field">
    <label for="archive_repertory_add_collection_folder">
        <?php echo __('Add a folder for each collection');?>
    </label>
    <div class="inputs">
    <?php echo __v()->formCheckbox('archive_repertory_add_collection_folder', TRUE,
    array('checked' => (boolean) get_option('archive_repertory_add_collection_folder')));?>
    <p class="explanation">
        <?php echo __('If checked, Omeka will add a subfolder in the "archive" folder for each collection, for example "archive/files/my_collection/". These subfolders will be named with the sanitized names below. New files will be stored inside them.');?>
    </p>
    </div>
</div>
<?php foreach ($collections as $collection) {
    $id = 'archive_repertory_collection_folder_' . $collection->id;
?>
    <div class="field">
        <label for="<?php echo $id;?>">
            <?php echo __('Folder name for') . ' "' . $collection->name . '"';?>
        </label>
        <div class="inputs">
            <?php echo __v()->formText($id, $collection_names[$collection->id], NULL);?>
        </div>
    </div>
<?php }?>
<div class="field">
    <label for="archive_repertory_add_item_folder">
        <?php echo __('Add a folder for each item');?>
    </label>
    <div class="inputs">
    <?php echo __v()->formCheckbox('archive_repertory_add_item_folder', TRUE,
    array('checked' => (boolean) get_option('archive_repertory_add_item_folder')));?>
    <p class="explanation">
        <?php echo __('If checked, Omeka will add subfolders for each item in the "archive" folder, for example "archive/files/unique_identifier/". Names of these subfolders will be sanitized. New files will be stored inside them.') . '<br />';?>
    </p>
    </div>
</div>
<div class="field">
    <label for="archive_repertory_item_identifier_prefix">
        <?php echo __('Prefix of item identifiers to use');?>
    </label>
    <div class="inputs">
        <?php echo __v()->formText('archive_repertory_item_identifier_prefix', get_option('archive_repertory_item_identifier_prefix'), null);?>
        <p class="explanation">
            <?php echo __('The name of folder of each new item will be the sanitized Dublin Core identifier with the selected prefix, for example "item:", "record:" or "doc:". Let empty to use simply the first item identifier.') . '<br />';
            echo __('If this identifier does not exists, the Omeka item id will be used.') . '<br />';?>
        </p>
    </div>
</div>
<div class="field">
    <label for="archive_repertory_keep_original_filename">
        <?php echo __('Keep original name of attached files');?>
    </label>
    <div class="inputs">
    <?php echo __v()->formCheckbox('archive_repertory_keep_original_filename', TRUE,
    array('checked' => (boolean) get_option('archive_repertory_keep_original_filename')));?>
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
        <?php echo __('Keep only base of original name of attached files');?>
    </label>
    <div class="inputs">
    <?php echo __v()->formCheckbox('archive_repertory_base_original_filename', TRUE,
    array('checked' => (boolean) get_option('archive_repertory_base_original_filename')));?>
    <p class="explanation">
        <?php echo __('If checked, Omeka will keep only base of original filenames, not their path. This option depends on the previous one.') . '<br />';
            echo '<strong>' . __('Warning') . '</strong>:</br>';
            echo __('This option implies that all filenames are unique, in particular if this option is not combined with "Add collection folder" and "Add item folder" options.') . '<br />';
        ?>
    </p>
    </div>
</div>