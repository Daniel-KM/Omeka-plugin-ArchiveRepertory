<?php
/**
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * @copyright Copyright Daniel Berthereau, 2012-2017
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 * @package ArchiveRepertory
 */

require_once dirname(__FILE__)
    . DIRECTORY_SEPARATOR . 'helpers'
    . DIRECTORY_SEPARATOR . 'ArchiveRepertoryFunctions.php';

/**
 * The Archive Repertory plugin.
 * @package Omeka\Plugins\ArchiveRepertory
 */
class ArchiveRepertoryPlugin extends Omeka_Plugin_AbstractPlugin
{
    /**
     * @var array This plugin's hooks.
     */
    protected $_hooks = array(
        'install',
        'upgrade',
        'uninstall',
        'config_form',
        'config',
        'define_routes',
        'after_save_collection',
        'after_save_item',
        'after_save_file',
        'after_delete_file',
    );

    /**
     * @var array This plugin's options.
     */
    protected $_options = array(
        // Collections options.
        'archive_repertory_collection_folder' => 'id',
        'archive_repertory_collection_prefix' => '',
        'archive_repertory_collection_names' => 'a:0:{}',
        'archive_repertory_collection_convert' => 'full',
        // Items options.
        'archive_repertory_item_folder' => 'id',
        'archive_repertory_item_prefix' => '',
        'archive_repertory_item_convert' => 'full',
        // Files options.
        'archive_repertory_file_convert' => 'full',
        'archive_repertory_file_base_original_name' => false,
        // Other derivative folders.
        'archive_repertory_derivative_folders' => '',
        'archive_repertory_move_process' => 'internal',
        // Max download without captcha (default to 30 MB).
        'archive_repertory_download_max_free_download' => 30000000,
        'archive_repertory_legal_text' => 'I agree with terms of use.',
    );

    /**
     * Default folder paths for each default type of files/derivatives.
     *
     * @see application/models/File::_pathsByType()
     * @var array
     */
    static private $_pathsByType = array(
        'original' => 'original',
        'fullsize' => 'fullsize',
        'thumbnail' => 'thumbnails',
        'square_thumbnail' => 'square_thumbnails',
    );

    /**
     * Derivative extension for each type of files/derivatives, used when a
     * plugin doesn't use the Omeka standard ones. These lasts are used by
     * default. The dot before the extension should be specified if needed.
     *
     * This setting is used only when files are moved or deleted without use of
     * the original plugin, because the original plugin knows to create and
     * to delete them at the right place, of course.
     *
     * @var array
     */
    private $_derivativeExtensionsByType = array();

    /**
     * Installs the plugin.
     */
    public function hookInstall()
    {
        $this->_installOptions();

        // Set default names of collection folders. Folders are created by config.
        $this->_setCollectionFolderNames();
    }

    /**
     * Upgrades the plugin.
     */
    public function hookUpgrade($args)
    {
        $oldVersion = $args['old_version'];
        $newVersion = $args['new_version'];

        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'upgrade.php';
    }

    /**
     * Uninstalls the plugin.
     */
    public function hookUninstall()
    {
        $this->_uninstallOptions();
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/archive-repertory-config-form.php',
            array(
                'allow_unicode' => checkUnicodeInstallation(),
                'local_storage' => $this->_getLocalStoragePath(),
        ));
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
        }

        // Unlike items, collections are few and stable, so they are kept as an
        // option.
        $this->_setCollectionFolderNames();
    }

    /**
     * Defines route for direct download count.
     */
    public function hookDefineRoutes($args)
    {
        // ".htaccess" always redirects direct downloads to a public url.
        if (is_admin_theme()) {
            return;
        }

        $args['router']->addConfig(new Zend_Config_Ini(dirname(__FILE__) . '/routes.ini', 'routes'));
    }

    /**
     * Create a collection folder when a collection is created.
     *
     * @todo Add a job to process the move of items when the name of a
     * collection changes or when a collection is removed.
     */
    public function hookAfterSaveCollection($args)
    {
        $post = $args['post'];
        $collection = $args['record'];

        // Create or update the collection folder name.
        $this->_setCollectionFolderName($collection);
    }

    /**
     * Manages folders for attached files of items.
     */
    public function hookAfterSaveItem($args)
    {
        $item = $args['record'];

        // Check if files are already attached and if they are at the right place.
        $files = $item->getFiles();
        foreach ($files as $file) {
            // Move file only if it is not in the right place.
            $storageId = $this->getStorageId($file);
            if ($storageId == $file->filename) {
                continue;
            }

            // Check if the original file exists, else this is an undetected
            // error during the convert process.
            $path = $this->getFullArchivePath('original');
            if (!file_exists($this->concatWithSeparator($path, $file->filename))) {
                $msg = __('File "%s" [%s] is not present in the original directory.', $file->filename, $file->original_filename);
                $msg .= ' ' . __('There was an undetected error before storage, probably during the convert process.');
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
            }

            $result = $this->moveFilesInArchiveFolders(
                $file->filename,
                $storageId,
                $this->_getDerivativeExtension($file));
            if (!$result) {
                $msg = __('Cannot move file "%s" inside archive directory.',
                    pathinfo($file->original_filename, PATHINFO_BASENAME));
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
            }

            // Update file in Omeka database immediately for each file.
            $file->filename = $storageId;
            // As it's not a file hook, the file is not automatically saved.
            $file->save();
        }
    }

    /**
     * Moves/renames an attached file just at the end of the save process.
     *
     * Original file name can be cleaned too (user can choose to keep base name
     * only).
     *
     * If the name is a duplicate one, a suffix is added.
     */
    public function hookAfterSaveFile($args)
    {
        // Avoid multiple renames of a file.
        static $processedFiles = array();

        $post = $args['post'];
        $file = $args['record'];

        // Files can't be moved during insert, because has_derivative is set
        // just after it. Of course, this can be bypassed, but we don't.
        if (!$args['insert']) {
            // Check stored file status.
            if ($file->stored == 0) {
                return;
            }

            // Check if file is processed.
            if (isset($processedFiles[$file->id])) {
                return;
            }

            // Check if file is a previous inserted file (check a value that
            // does not exist in an already saved file).
            if (!isset($file->_storage)) {
                return;
            }

            // Check if main file is already in the archive folder.
            if (!is_file($this->concatWithSeparator($this->getFullArchivePath('original'), $file->filename))) {
                return;
            }

            // Memorize current filenames.
            $file_filename = $file->filename;
            $file_original_filename = $file->original_filename;

            // Keep only basename of original filename in metadata if wanted.
            if (get_option('archive_repertory_file_base_original_name')) {
                $file->original_filename = basename_special($file->original_filename);
            }

            // Rename file only if wanted and needed.
            $storageId = $this->getStorageId($file);
            if ($storageId != $file->filename) {
                $result = $this->moveFilesInArchiveFolders(
                    $file->filename,
                    $storageId,
                    $this->_getDerivativeExtension($file));
                if (!$result) {
                    $msg = __('Cannot move file "%s" inside archive directory.',
                        pathinfo($file->original_filename, PATHINFO_BASENAME));
                    throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
                }

                // Update filename.
                $file->filename = $storageId;
            }

            $processedFiles[$file->id] = true;

            // Update file only if needed. It uses normal hook, so this hook
            // will be call one more time, but filenames will be already updated
            // so there is no risk of infinite loop.
            if ($file_filename != $file->filename
                    || $file_original_filename != $file->original_filename
                ) {
                $file->save();
            }
        }
    }

    /**
     * Manages deletion of the folder of a file when this file is removed.
     */
    public function hookAfterDeleteFile($args)
    {
        $file = $args['record'];
        $item = $file->getItem();
        $archiveFolder = $this->_getArchiveFolderName($item);
        $result = $this->removeArchiveFolders($archiveFolder);
        return true;
    }

    /**
     * Get the full storage id of a file according to current settings.
     *
     * Note: The directory separator is always "/" to simplify management of
     * files and checks.
     * Note: Unlike Omeka S, the storage id includes the extension.
     *
     * @param File $file
     * @return string
     */
    protected function getStorageId(File $file)
    {
        $item = $file->getItem();
        $folderName = $this->_getArchiveFolderName($item);

        $extension = pathinfo($file->original_filename, PATHINFO_EXTENSION);

        $mediaConvert = get_option('archive_repertory_file_convert');
        if ($mediaConvert == 'hash') {
            $storageName = $this->hashStorageName($file);
        } else {
            $storageName = pathinfo_special($file->original_filename, PATHINFO_BASENAME);
            $storageName = $this->sanitizeName($storageName);
            $storageName = pathinfo_special($storageName, PATHINFO_FILENAME);
            $storageName = $this->convertFilenameTo($storageName, $mediaConvert);
        }

        if ($extension) {
            $storageName = $storageName . '.' . $extension;
        }

        // Process the check of the storage name to get the storage id.
        $storageName = $this->concatWithSeparator($folderName, $storageName);
        $storageName = $this->getSingleFilename($storageName, $file->filename);

        if (strlen($storageName) > 190) {
            $msg = __('Cannot move file "%s" inside archive directory: filename too long.',
                pathinfo($file->original_filename, PATHINFO_BASENAME));
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        return $storageName;
    }

    /**
     * Get the archive folder from a type.
     *
     * @example "original" returns "/var/www/omeka/files/original".
     *
     * @param string $type
     * @return string Full archive path, or empty if none.
     */
    protected function getFullArchivePath($type)
    {
        $archivePaths = $this->getFullArchivePaths();
        return isset($archivePaths[$type]) ? $archivePaths[$type] : '';
    }

    /**
     * Moves/renames a file and its derivatives inside archive/files subfolders.
     *
     * New folders are created if needed. Old folders are removed if empty.
     * No update of the database is done.
     *
     * @param string $currentArchiveFilename Name of the current archive file to
     * move.
     * @param string $newArchiveFilename Name of the new archive file, with
     * archive folder if any (usually "collection/dc:identifier/").
     * @param optional string $derivativeExtension Extension of the derivative
     * files to move, because it can be different from the new archive filename
     * and it can't be determined here.
     * @return bool True if files are moved
     * @throws Omeka_Storage_Exception
     */
    protected function moveFilesInArchiveFolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = __('Cannot move file inside archive directory: no filename.');
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        $newArchiveFilename = str_replace('//', '/', $newArchiveFilename);
        if ($currentArchiveFilename == $newArchiveFilename) {
            return true;
        }

        $currentArchiveFolder = dirname($currentArchiveFilename);
        $newArchiveFolder = dirname($newArchiveFilename);

        // Move the original file.
        $path = $this->getFullArchivePath('original');
        $result = $this->createArchiveFolders($newArchiveFolder, $path);
        $this->moveFile($currentArchiveFilename, $newArchiveFilename, $path);

        // If any, move derivative files using Omeka API.
        if ($derivativeExtension != '') {
            $derivatives = $this->getFullArchivePaths();
            // Original is managed above.
            unset($derivatives['original']);
            foreach ($derivatives as $type => $path) {
                // We create a folder in any case, even if there isn't any file
                // inside, in order to be fully compatible with any plugin that
                // manages base filename only.
                $result = $this->createArchiveFolders($newArchiveFolder, $path);

                // Determine the current and new derivative filename, standard
                // or not.
                $currentDerivativeFilename = $this->_getDerivativeFilename($currentArchiveFilename, $derivativeExtension, $type);
                $newDerivativeFilename = $this->_getDerivativeFilename($newArchiveFilename, $derivativeExtension, $type);

                // Check if the derivative file exists or not to avoid some
                // errors when moving.
                if (file_exists($this->concatWithSeparator($path, $currentDerivativeFilename))) {
                    $this->moveFile($currentDerivativeFilename, $newDerivativeFilename, $path);
                }
            }
        }

        // Remove all old empty folders.
        if ($currentArchiveFolder != $newArchiveFolder) {
            $this->removeArchiveFolders($currentArchiveFolder);
        }

        return true;
    }

    /**
     * Get the derivative filename from a filename and an extension. A check can
     * be done on the derivative type to allow use of a non standard extension,
     * for example with a plugin that doesn't follow standard naming.
     *
     * @param string $filename
     * @param string $defaultExtension
     * @param string $derivativeType The derivative type allows to use a non
     * standard extension.
     * @return string Filename with the new extension.
     */
    protected function _getDerivativeFilename($filename, $defaultExtension, $derivativeType = null)
    {
        $base = pathinfo($filename, PATHINFO_EXTENSION) ? substr($filename, 0, strrpos($filename, '.')) : $filename;
        $fullExtension = !is_null($derivativeType) && isset($this->_derivativeExtensionsByType[$derivativeType])
            ? $this->_derivativeExtensionsByType[$derivativeType]
            : '.' . $defaultExtension;
        return $base . $fullExtension;
    }

    /**
     * Get the derivative filename from a filename and an extension.
     *
     * @param object $file
     * @return string Extension used for derivative files (usually "jpg" for
     * images).
     */
    protected function _getDerivativeExtension($file)
    {
        return $file->has_derivative_image ? pathinfo($file->getDerivativeFilename(), PATHINFO_EXTENSION) : '';
    }

    /**
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without files dir.
     */
    protected function removeArchiveFolders($archiveFolder)
    {
        if (in_array($archiveFolder, array('.', '..', '/', '\\', ''))) {
            return;
        }

        foreach ($this->getFullArchivePaths() as $path) {
            $folderPath = $this->concatWithSeparator($path, $archiveFolder);
            // Of course, the main storage dir is not removed (in the case there
            // is no item folder).
            if (realpath($path) != realpath($folderPath)) {
                // Check if there is an empty directory and remove it only in
                // that case. The directory may be not empty in multiple cases,
                // for example when the config changes or when there is a
                // duplicate name.
                $this->removeDir($folderPath, false);
            }
        }
    }

    protected function concatWithSeparator($firstDir, $secondDir)
    {
        if (empty($firstDir)) {
            return $secondDir;
        }
        if (empty($secondDir)) {
            return $firstDir;
        }
        $firstDir = rtrim($firstDir, DIRECTORY_SEPARATOR);
        $secondDir = ltrim($secondDir, DIRECTORY_SEPARATOR);
        return $firstDir . DIRECTORY_SEPARATOR . $secondDir;
    }

    /**
     * Get all archive folders with full paths, eventually with other derivative
     * folders. This function updates the derivative extensions too.
     *
     * @return array of folders.
     */
    protected function getFullArchivePaths()
    {
        static $archivePaths = array();

        if (empty($archivePaths)) {
            $storagePath = $this->_getLocalStoragePath();
            foreach (self::$_pathsByType as $name => $path) {
                $archivePaths[$name] = $this->concatWithSeparator($storagePath, $path);
            }

            $derivatives = explode(',', get_option('archive_repertory_derivative_folders'));
            foreach ($derivatives as $key => $value) {
                if (strpos($value, '|') === false) {
                    $name = trim($value);
                } else {
                    list($name, $extension) = explode('|', $value);
                    $name = trim($name);
                    $extension = trim($extension);
                    if ($extension != '') {
                        $this->_derivativeExtensionsByType[$name] = $extension;
                    }
                }
                $path = realpath($this->concatWithSeparator($storagePath, $name));
                if (!empty($name) && !empty($path) && $path != '/') {
                    $archivePaths[$name] = $path;
                } else {
                    unset($derivatives[$key]);
                    set_option('archive_repertory_derivative_folders', implode(', ', $derivatives));
                }
            }
        }

        return $archivePaths;
    }

    /**
     * Check if a file is a duplicate and returns it with a suffix if needed.
     *
     * Note: The check is done on the basename, without extension, to avoid
     * issues with derivatives and because the table uses the basename too.
     * No check via database, because the file can be unsaved yet.
     *
     * @param string $filename
     * @param string $currentFilename It avoids to change when it is single.
     * @return string The unique filename, that can be the same as input name.
     */
    protected function getSingleFilename($filename, $currentFilename)
    {
        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $fullOriginalPath = $this->getFullArchivePath('original');
        $filepath = $this->concatWithSeparator($fullOriginalPath, $filename);
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $currentFilepath = $this->concatWithSeparator($fullOriginalPath, $currentFilename);

        // Check the name.
        $checkName = $name;
        $existingFilepaths = glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE);

        // Check if the filename exists.
        if (empty($existingFilepaths)) {
            // Nothing to do.
        }
        // There are filenames, so check if the current one is inside.
        elseif (in_array($currentFilepath, $existingFilepaths)) {
            // Keep the existing one if there are many filepaths, but use the
            // default one if it is unique.
            if (count($existingFilepaths) > 1) {
                $checkName = pathinfo($currentFilename, PATHINFO_FILENAME);
            }
        }
        // Check folder for file with any extension or without any extension.
        else {
            $i = 0;
            while (glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE)) {
                $checkName = $name . '.' . ++$i;
            }
        }

        $result = ($dirname && $dirname !== '.' ? $dirname . DIRECTORY_SEPARATOR : '')
            . $checkName
            . ($extension ? '.' . $extension : '');
        return $result;
    }

    /**
     * Gets record folder name from a record and create folder if needed.
     *
     * @param Record $record
     * @return string Unique sanitized name of the record.
     */
    protected function getRecordFolderName(Omeka_Record_AbstractRecord $record = null)
    {
        // This check allows to make Archive Repertory compatible with Admin Images.
        if (is_null($record)) {
            return '';
        }

        $recordType = get_class($record);
        switch ($recordType) {
            case 'Collection':
                $folder = get_option('archive_repertory_collection_folder');
                $prefix = get_option('archive_repertory_collection_prefix');
                $convert = get_option('archive_repertory_collection_convert');
                break;
            case 'Item':
                $folder = get_option('archive_repertory_item_folder');
                $prefix = get_option('archive_repertory_item_prefix');
                $convert = get_option('archive_repertory_item_convert');
                break;
            default:
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . sprintf('Unallowed record type "%s".', $recordType));
        }

        if (empty($folder)) {
            return '';
        }

        switch ($folder) {
            case '':
                return '';
            case 'id':
                return (string) $record->id;
            default:
                $identifier = $this->getRecordIdentifier($record, $folder, $prefix);
                $name = $this->sanitizeName($identifier);
                return empty($name)
                    ? (string) $record->id
                    : $this->convertFilenameTo($name, $convert) ;
        }
    }

    /**
     * Gets archive folder name of an item, that depends on activation of options.
     *
     * @param object $item
     * @return string Unique and sanitized name folder name of the item.
     */
    protected function _getArchiveFolderName($item)
    {
        $collectionFolder = $this->_getCollectionFolderName($item);
        $itemFolder = $this->getRecordFolderName($item);
        return $this->concatWithSeparator($collectionFolder, $itemFolder);
    }

    /**
     * Gets collection folder name from an item.
     *
     * @param object $item
     * @return string Unique sanitized name of the collection.
     */
    protected function _getCollectionFolderName($item)
    {
        if (empty($item->collection_id)) {
            return '';
        }

        $folder = get_option('archive_repertory_collection_folder');
        if (empty($folder)) {
            return '';
        }

        // Collection folders are presaved.
        $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
        $name = isset($collectionNames[$item->collection_id])
            ? $collectionNames[$item->collection_id]
            : $item->collection_id;
        return $name;
    }

    /**
     * Prepare collection folder names.
     */
    protected function _setCollectionFolderNames()
    {
        $collections = get_records('Collection', array(), 0);
        foreach ($collections as $collection) {
            $this->_setCollectionFolderName($collection);
        }
    }

    /**
     * Creates the default name for a collection folder.
     *
     * @param object $collection
     */
    protected function _setCollectionFolderName($collection)
    {
        $folder = get_option('archive_repertory_collection_folder');
        if (empty($folder)) {
            return;
        }

        $name = $this->getRecordFolderName($collection);
        $name = $this->convertFilenameTo(
            $name,
            get_option('archive_repertory_collection_convert'));

        $collectionNames = unserialize(get_option('archive_repertory_collection_names'));
        $collectionNames[$collection->id] = $name;
        set_option('archive_repertory_collection_names', serialize($collectionNames));
    }

    /**
     * Gets first identifier of a record.
     *
     * @param Record $record A collection or an item.
     * @param string $elementId
     * @param string $prefix
     * @return string
     */
    protected function getRecordIdentifier(Omeka_Record_AbstractRecord $record, $elementId, $prefix)
    {
        // Use a direct query in order to improve speed.
        $db = $this->_db;
        $select = $db->select()
            ->from($db->ElementText, array('text'))
            ->where('element_id = ?', $elementId)
            ->where('record_type = ?', get_class($record))
            ->where('record_id = ?', $record->id)
            ->order('id')
            ->limit(1);
        if ($prefix) {
            $select->where('text LIKE ?', $prefix . '%');
        }
        $identifier = $db->fetchOne($select);
        if ($prefix) {
            $identifier = trim(substr($identifier, strlen($prefix)));
        }
        return $identifier;
    }

    /**
     * Hash a stable single storage name for a specific file.
     *
     * Note: A random name is not used to avoid possible issues when the option
     * changes.
     * @see Omeka_Filter_Filename::renameFile()
     *
     * @param File $file
     * @return string
     */
    protected function hashStorageName(File $file)
    {
        $storageName = md5($file->id . '/' . $file->original_filename);
        return $storageName;
    }

    /**
     * Returns a sanitized string for folder or file path.
     *
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function sanitizeName($string)
    {
        $string = strip_tags($string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"\'`&; ');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file name.
     *
     * Note: The string should be already sanitized.
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @see ArchiveRepertoryPlugin::sanitizeName()
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     * @return string The sanitized string.
     */
    protected function convertFilenameTo($string, $format)
    {
        switch ($format) {
            case 'keep':
                return $string;
            case 'first letter':
                return $this->convertFirstLetterToAscii($string);
            case 'spaces':
                return $this->convertSpacesToUnderscore($string);
            case 'first and spaces':
                $string = $this->convertFilenameTo($string, 'first letter');
                return $this->convertSpacesToUnderscore($string);
            case 'full':
            default:
                return $this->convertNameToAscii($string);
        }
    }

    /**
     * Returns an unaccentued string for folder or file name.
     *
     * Note: The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to convert to ascii.
     * @return string The converted string to use as a folder or a file name.
     */
    protected function convertNameToAscii($string)
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file path (first letter only).
     *
     * Note: The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertFirstLetterToAscii($string)
    {
        $first = $this->convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->substr_unicode($string, 1);
    }

    /**
     * Returns a formatted string for folder or file path (spaces only).
     *
     * Note: The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertSpacesToUnderscore($string)
    {
        return preg_replace('/\s+/', '_', $string);
    }

    /**
     * Get a sub string from a string when mb_substr is not available.
     *
     * @see http://www.php.net/manual/en/function.mb-substr.php#107698
     *
     * @param string $string
     * @param int $start
     * @param int $length (optional)
     * @return string
     */
    protected function substr_unicode($string, $start, $length = null)
    {
        return join(
            '',
            array_slice(
                preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY),
                $start,
                $length
            )
        );
    }

    /**
     * Get the local storage path (by default FILES_DIR).
     *
     * @return string
     */
    protected function _getLocalStoragePath()
    {
        $adapterOptions = Zend_Registry::get('storage')->getAdapter()->getOptions();
        return $adapterOptions['localDir'];
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder
     *   Name of folder to create inside archive dir.
     * @param string $pathFolder
     *   (Optional) Name of folder where to create archive folder. If not set,
     *   the archive folder will be created in all derivative paths.
     * @return bool True if each path is created, Exception if an error occurs.
     */
    protected function createArchiveFolders($archiveFolder, $pathFolder = '')
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->getFullArchivePaths()
                : array($pathFolder);
            foreach ($folders as $path) {
                $fullpath = $this->concatWithSeparator($path, $archiveFolder);
                $result = $this->createFolder($fullpath);
            }
        }
        return true;
    }

    /**
     * Checks and creates a folder.
     *
     * @note Currently, Omeka API doesn't provide a function to create a folder.
     *
     * @param string $path Full path of the folder to create.
     * @return bool True if the path is created.
     * @throws Omeka_Storage_Exception
     */
    protected function createFolder($path)
    {
        if ($path == '') {
            return true;
        }

        if (file_exists($path)) {
            if (is_dir($path)) {
                @chmod($path, 0755);
                if (is_writable($path)) {
                    return true;
                }
                $msg = __('Error directory non writable: "%s".', $path);
                throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
            }
            $msg = __('Failed to create folder "%s": a file with the same name exists...', $path);
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        if (!@mkdir($path, 0755, true)) {
            $msg = __('Error making directory: "%s".', $path);
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }
        @chmod($path, 0755);

        return true;
    }

    /**
     * Process the move operation according to admin choice.
     *
     * @param string $source
     * @param string $destination
     * @param string $path
     * @return bool
     * @throws Omeka_Storage_Exception
     */
    protected function moveFile($source, $destination, $path = '')
    {
        $realSource = $this->concatWithSeparator($path, $source);
        if (!file_exists($realSource)) {
            $msg = __('Error during move of a file from "%s" to "%s" (local dir: "%s"): source does not exist.',
                $source, $destination, $path);
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        $result = false;
        try {
            switch (get_option('archive_repertory_move_process')) {
                // Move file directly.
                case 'direct':
                    $realDestination = $this->concatWithSeparator($path, $destination);
                    $result = rename($realSource, $realDestination);
                    break;

                    // Move the main original file using Omeka API.
                case 'internal':
                default:
                    $operation = new Omeka_Storage_Adapter_Filesystem(array(
                    'localDir' => $path,
                    ));
                    $operation->move($source, $destination);
                    $result = true;
                    break;
            }
        } catch (Omeka_Storage_Exception $e) {
            $msg = __('Error during move of a file from "%s" to "%s" (local dir: "%s").',
                $source, $destination, $path);
            throw new Omeka_Storage_Exception($e->getMessage() . "\n" . '[ArchiveRepertory] ' . $msg);
        }

        return $result;
    }

    /**
     * Checks and removes a folder recursively.
     *
     * @param string $path Full path of the folder to remove.
     * @param bool $evenNonEmpty Remove non empty folder. This parameter can be
     * used with non standard folders.
     * @return bool
     */
    protected function removeDir($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (strlen($path)
                && $path != DIRECTORY_SEPARATOR
                && file_exists($path)
                && is_dir($path)
                && is_readable($path)
                && is_writable($path)
                && ($evenNonEmpty || count(array_diff(@scandir($path), array('.', '..'))) == 0)
            ) {
            return $this->recursiveRemoveDir($path);
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     * @return bool
     */
    protected function recursiveRemoveDir($dirPath)
    {
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
