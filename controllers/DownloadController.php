<?php
/**
 * The download controller class.
 *
 * Check if a file can be deliver in order to avoid bandwidth theft.
 *
 * @package ArchiveRepertory
 */
class ArchiveRepertory_DownloadController extends Omeka_Controller_AbstractActionController
{
    protected $_type;
    protected $_storage;
    protected $_filename;
    protected $_filepath;
    protected $_filesize;
    protected $_file;
    protected $_contentType;
    protected $_mode;
    protected $_theme;
    protected $_sourcePage;
    protected $_toConfirm;

    // Make compatible with plugin AdminImages
    protected $_hasPluginAdminImages;
    protected $_isAdminImage;

    /**
     * Initialize the controller.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace('DownloadFile');
        // The files of plugin AdminImages are available even when disabled.
        $this->_hasPluginAdminImages = file_exists(PLUGIN_DIR . '/AdminImages/AdminImagesPlugin.php');
    }

    /**
     * Forward to the 'files' action
     *
     * @see self::filesAction()
     */
    public function indexAction()
    {
        $this->forward('files');
    }

    /**
     * Check if a file can be delivered in order to avoid bandwidth theft.
     */
    public function filesAction()
    {
        $confirmBySession = (bool) get_option('archive_repertory_confirm_by_session');
        if ($confirmBySession) {
            $session = new Zend_Session_Namespace('ArchiveRepertoryDownload');
            $lastConfirm = isset($session->last_confirm)
                ? $session->last_confirm
                : 0;
            // Check if the session is less than 24h old.
            if (($lastConfirm + 86400) > time()) {
                $this->_sendFile();
                return;
            }
        }

        // Prepare session (allow only one confirmation).
        $this->session->setExpirationHops(2);

        // Save default redirection used in case of error or for the form.
        $this->session->sourcePage = $this->_getSourcePage();

        // Check post.
        if (!$this->_checkPost()) {
            $this->_helper->flashMessenger(__("This file doesn't exist."), 'error');
            return $this->_gotoSourcePage();
        }

        // File is good.

        // Plugin AdminImage uses the same folders than standards files, but
        // should not be checked.
        if ($this->_isAdminImage) {
            $this->_sendFile();
        }
        // Check if the user should confirm download.
        elseif ($this->_getToConfirm()) {
            // Filepath is not saved in session for security reason.
            $this->session->filename = $this->_filename;
            $this->session->type = $this->_type;
            $this->_helper->redirector->goto('confirm');
        } else {
            $this->_sendFile();
        }
    }

    /**
     * Prepare captcha.
     */
    public function confirmAction()
    {
        $this->session->setExpirationHops(2);

        if (!$this->_checkSession()) {
            $this->_helper->flashMessenger(__('Download error.'), 'error');
            return $this->_gotoSourcePage();
        }

        $form = new ArchiveRepertory_Form_Confirm();
        $this->view->form = $form;
        $this->view->filesize = $this->_formatFileSize($this->_getFilesize());
        $this->view->source_page = $this->session->sourcePage;

        if (!$this->getRequest()->isPost()) {
            return;
        }

        $post = $this->getRequest()->getPost();
        if (!$form->isValid($post)) {
            $this->_helper->flashMessenger(__('Invalid form input. Please see errors below and try again.'), 'error');
            return;
        }

        $confirmBySession = (bool) get_option('archive_repertory_confirm_by_session');
        if ($confirmBySession) {
            $session = new Zend_Session_Namespace('ArchiveRepertoryDownload');
            if (!isset($session->last_confirm)) {
                $session->setExpirationSeconds(86400);
            }
            $session->last_confirm = time();
        }

        // Reset filename and type in session, because they have been checked.
        $this->session->filename = $this->_filename;
        $this->session->type = $this->_type;
        $this->_helper->redirector->goto('send');
    }

    /**
     * Send file as attachment.
     */
    public function sendAction()
    {
        if (!$this->_checkSession()) {
            $this->_helper->flashMessenger(__('Download error: File already sent.'), 'error');
            return $this->_gotoSourcePage();
        }

        $this->view->sendUrl = WEB_ROOT . '/archive-repertory/download/send';
        $this->view->source_page = $this->session->sourcePage;

        if (!isset($this->session->checked)) {
            $this->session->checked = true;
            return;
        }

        // Second time this page is reloaded, so send file.
        $this->_sendFile();
    }

    /**
     * Helper to send file as stream or attachment.
     */
    protected function _sendFile()
    {
        // Disable layout and view.
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Everything has been checked.
        $filepath = $this->_getFilepath();
        $filesize = $this->_getFilesize();
        $file = $this->_getFile();
        $contentType = $this->_getContentType();
        $mode = $this->_getMode();

        // Save the stats if the plugin Stats is ready.
        if (plugin_is_active('Stats') && $this->_getTheme() == 'public') {
            $type = $this->_getType();
            $filename = $this->_getFilename();
            $this->view->stats()->new_hit(
                // The redirect to is not useful, so keep original url.
                '/files/' . $type . '/' . $filename,
                $file
            );
        }

        // Clears all active output buffers to avoid memory overflow.
        while (ob_get_level()) {
            ob_end_clean();
        }

        $response = $this->getResponse();
        $response->clearBody();
        $response->setHeader('Pragma', 'public');
        $response->setHeader('Expires', '0');
        $response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $response->setHeader('Cache-Control', 'private', false);
        $response->setHeader('Content-Type', $contentType);
        $response->setHeader('Content-Disposition', $mode . '; filename="' . pathinfo($filepath, PATHINFO_BASENAME) . '"', true);
        $response->setHeader('Content-Transfer-Encoding', 'binary');
        $response->setHeader('Content-Length', $filesize);
        $response->setHeader('Content-Description', 'File Transfer');
        // Send headers separately to handle large files.
        $response->sendHeaders();

        // Clears all active output buffers to avoid memory overflow.
        $response->setBody('');
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($filepath);

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        // TODO exit() is used for now.
        exit();
    }

    /**
     * Check if the post is good and save results.
     *
     * @return bool
     */
    protected function _checkPost()
    {
        if (!$this->_getStorage()) {
            return false;
        }

        if (!$this->_getFilename()) {
            return false;
        }

        if (!$this->_getFilepath()) {
            return false;
        }

        if (!$this->_getFilesize()) {
            return false;
        }

        if ($this->_hasPluginAdminImages && $this->isAdminImage()) {
            return true;
        }

        if (!$this->_getFile()) {
            return false;
        }

        if (!$this->_getContentType()) {
            return false;
        }

        if (!$this->_getMode()) {
            return false;
        };

        return true;
    }

    /**
     * Returns whether the session is valid.
     *
     * Recheck everything for security reason. This will be done only when this
     * is sent after confirmation, as attachment.
     *
     * @return bool
     */
    protected function _checkSession()
    {
        // Save default redirection used in case of error or in the form.
        if (isset($this->session->sourcePage) && !empty($this->session->sourcePage)) {
            $this->_sourcePage = $this->session->sourcePage;
        }

        $requiredKeys = array(
            'filename',
            'type',
            // 'sourcePage',
        );
        foreach ($requiredKeys as $key) {
            if (!isset($this->session->$key)) {
                return false;
            } else {
                $required = '_' . $key;
                $this->$required = $this->session->$key;
            }
        }

        if (!$this->_getStorage()) {
            return false;
        }

        if (!$this->_getFilename()) {
            return false;
        }

        if (!$this->_getFilepath()) {
            return false;
        }

        if (!$this->_getFilesize()) {
            return false;
        }

        if ($this->_hasPluginAdminImages && $this->isAdminImage()) {
            return true;
        }

        if (!$this->_getFile()) {
            return false;
        }

        if (!$this->_getContentType()) {
            return false;
        }

        // If we come back here, this is a confirmation and mode is attachment.
        $this->_mode = 'attachment';

        return true;
    }

    /**
     * Get and set type (generally original, sometimes fullsize).
     *
     * @internal The type is not checked, but if not authorized, storage will
     * return an error.
     *
     * @return string ("original" by default)
     */
    protected function _getType()
    {
        if (is_null($this->_type)) {
            $this->_type = $this->_request->getParam('type');

            // Default type.
            if (empty($this->_type)) {
                $this->_type = 'original';
            }
        }

        return $this->_type;
    }

    /**
     * Get, check and set type of storage.
     *
     * @return string Path to the storage of the selected type of file.
     */
    protected function _getStorage()
    {
        if (is_null($this->_storage)) {
            $type = $this->_getType();

            // This is used to get list of storage path. Is there a better way?
            // getPathByType() is not secure.
            $file = new File;
            try {
                $storagePath = $file->getStoragePath($type);
            } catch (RuntimeException $e) {
                $this->_storage = false;
                return false;
            }
            $this->_storage = ($type == 'original')
                ? substr($storagePath, 0, strlen($storagePath) - 1)
                : substr($storagePath, 0, strlen($storagePath) - strlen(File::DERIVATIVE_EXT) - 2);
        }

        return $this->_storage;
    }

    /**
     * Get and set filename.
     *
     * @internal The filename is not checked, but if not existing, filepath will
     * return an error.
     *
     * @return string Filename.
     */
    protected function _getFilename()
    {
        if (is_null($this->_filename)) {
            $this->_filename = $this->_request->getParam('filename');
        }

        return $this->_filename;
    }

    /**
     * Get and set filepath.
     *
     * @return string Path to the file.
     */
    protected function _getFilepath()
    {
        if (is_null($this->_filepath)) {
            $filename = $this->_getFilename();
            $storage = $this->_getStorage();
            $storagePath = FILES_DIR . DIRECTORY_SEPARATOR . $storage . DIRECTORY_SEPARATOR;
            $filepath = realpath($storagePath . $filename);
            if (strpos($filepath, $storagePath) !== 0) {
                return false;
            }
            $this->_filepath = $filepath;
        }

        return $this->_filepath;
    }

    /**
     * Get and set file size. This allows to check if file really exists.
     *
     * @return int Length of the file.
     */
    protected function _getFilesize()
    {
        if (is_null($this->_filesize)) {
            $filepath = $this->_getFilepath();
            $this->_filesize = @filesize($filepath);
        }

        return $this->_filesize;
    }

    /**
     * Set and get file object from the filename. Rights access are checked.
     *
     * @return File|null
     */
    protected function _getFile()
    {
        if (is_null($this->_file)) {
            $filename = $this->_getFilename();
            $storage = $this->_getStorage();
            if ($storage == 'original') {
                $this->_file = get_db()->getTable('File')->findBySql('filename = ?', array($filename), true);
            }
            // Get a derivative: this is functional only because filenames are
            // hashed.
            else {
                $originalFilename = substr($filename, 0, strlen($filename) - strlen(File::DERIVATIVE_EXT) - 1);
                $this->_file = get_db()->getTable('File')->findBySql('filename LIKE ?', array($originalFilename . '%'), true);
            }

            // Check rights: if the file belongs to a public item.
            if (empty($this->_file)) {
                $this->_file = false;
            }
            // Check public item only if item id exists (plugin AdminImages).
            elseif ($this->_file->item_id) {
                $item = $this->_file->getItem();
                if (empty($item)) {
                    $this->_file = false;
                }
            }
        }

        return $this->_file;
    }

    /**
     * Set and get file object from the filename. Rights access are checked.
     *
     * @return File|null
     */
    protected function _getContentType()
    {
        if (is_null($this->_contentType)) {
            $type = $this->_getType();
            if ($type == 'original') {
                $file = $this->_getFile();
                $this->_contentType = $file->mime_type;
            } else {
                $this->_contentType = 'image/jpeg';
            }
        }

        return $this->_contentType;
    }

    /**
     * Get and set rights to direct download.
     *
     * @return bool False if confirmation is not needed, else true.
     */
    protected function _getToConfirm()
    {
        if (is_null($this->_toConfirm)) {
            if (current_user()) {
                $this->_toConfirm = false;
            }

            // Check for captcha;
            else {
                $filesize = $this->_getFilesize();
                $this->_toConfirm = ($filesize > (int) get_option('archive_repertory_download_max_free_download'));
            }
        }

        return $this->_toConfirm;
    }

    /**
     * Get and set sending mode.
     *
     * @return string Disposition 'inline' (default) or 'attachment'.
     */
    protected function _getMode()
    {
        if (is_null($this->_mode)) {
            if ($this->_getToConfirm()) {
                $this->_mode = 'attachment';
                return $this->_mode;
            }

            // Prepare headers.
            $mode = $this->_request->getParam('mode', 'inline');
            switch ($mode) {
                case 'inline':
                    $this->_mode = 'inline';
                    break;

                case 'attachment':
                    $this->_mode = 'attachment';
                    break;

                case 'size':
                    $filesize = $this->_getFilesize();
                    $this->_mode = ($filesize > (int) get_option('archive_repertory_download_max_free_download'))
                        ? 'attachment'
                        : 'inline';
                    break;

                case 'image':
                    $contentType = $this->_getContentType();
                    $this->_mode = (strpos($contentType, 'image') === false)
                        ? 'attachment'
                        : 'inline';
                    break;

                case 'image-size':
                    $filesize = $this->_getFilesize();
                    $contentType = $this->_getContentType();
                    $this->_mode = (strpos($contentType, 'image') === false
                            || $filesize > (int) get_option('archive_repertory_download_max_free_download'))
                        ? 'attachment'
                        : 'inline';
                    break;

                default:
                    $this->_mode = 'inline';
            }
        }

        return $this->_mode;
    }

    /**
     * Get and set theme via referrer (public if unknow or unidentified user).
     *
     * @return string "public" or "admin".
     */
    protected function _getTheme()
    {
        if (is_null($this->_theme)) {
            // Default is set to public.
            $this->_theme = 'public';
            // This allows quick control if referrer is not set.
            if (current_user()) {
                $referrer = (string) $this->getRequest()->getServer('HTTP_REFERER');
                if (strpos($referrer, WEB_ROOT . '/admin/') === 0) {
                    $this->_theme = 'admin';
                }
            }
        }

        return $this->_theme;
    }

    /**
     * Get and set redirect via referrer to use in case of error or in the form.
     *
     * @return string
     */
    protected function _getSourcePage()
    {
        if (is_null($this->_sourcePage)) {
            $this->_sourcePage = $this->_request->getServer('HTTP_REFERER');
            if (empty($this->_sourcePage)) {
                $this->_sourcePage = WEB_ROOT;
            }
        }
        return $this->_sourcePage;
    }

    /**
     * Redirect to previous page.
     */
    protected function _gotoSourcePage()
    {
        if ($this->_sourcePage) {
            $this->redirect($this->_sourcePage);
        } elseif ($this->session->sourcePage) {
            $this->redirect($this->session->sourcePage);
        } else {
            $this->redirect(WEB_ROOT);
        }
    }

    /**
     * Return a file size with the appropriate format of unit.
     *
     * @return string
     *   String of the file size.
     */
    protected function _formatFileSize($size)
    {
        // Space is a no-break space.
        if ($size < 1024) {
            return $size . ' ' . __('bytes');
        }

        foreach (array(__('KB'), __('MB'), __('GB'), __('TB')) as $unit) {
            $size /= 1024.0;
            if ($size < 10) {
                return sprintf("%.1f" . ' ' . $unit, $size);
            }
            if ($size < 1024) {
                return (int) $size . ' ' . $unit;
            }
        }
    }

    /**
     * Check if the file is an admin image.
     *
     * @return bool
     */
    protected function isAdminImage()
    {
        // The plugin is not checked: even disabled, the image should load.
        if (!is_null($this->_isAdminImage)) {
            return $this->_isAdminImage;
        }

        $filename = $this->_getFilename();
        $isOriginal = $this->_getType() === 'original';
        if ($isOriginal) {
            $bind = array($filename);
            $equalOrLike = '=';
        } else {
            $originalFilename = substr($filename, 0, strlen($filename) - strlen(File::DERIVATIVE_EXT) - 1);
            $bind = array($originalFilename . '%');
            $equalOrLike = 'LIKE';
        }

        $db = get_db();
        $sql = <<<SQL
SELECT files.id, files.mime_type
FROM $db->File AS files
WHERE files.item_id = 0
AND files.filename $equalOrLike ?
LIMIT 1
SQL;
        $result = $db->fetchPairs($sql, $bind);

        if ($result) {
            if ($isOriginal) {
                $this->_contentType = reset($result);
            } else {
                $this->_contentType = 'image/jpeg';
                reset($result);
            }
            $this->_file = array(
                'record_type' => 'File',
                'record_id' => key($result),
            );
            $this->_mode = 'inline';
            $this->_toConfirm = false;
            $this->_isAdminImage = true;
        } else {
            $this->_isAdminImage = false;
        }

        return $this->_isAdminImage;
    }
}
