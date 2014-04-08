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
    protected $_filename;
    protected $_type;
    protected $_redirect;
    // Internal only.
    protected $_mode;
    protected $_storage;
    protected $_filepath;
    protected $_file;
    protected $_confirm;

    /**
     * Initialize the controller.
     */
    public function init()
    {
        $this->session = new Zend_Session_Namespace('DownloadFile');
    }

    /**
     * Check if a file can be deliver in order to avoid bandwidth theft.
     */
    public function fileAction()
    {
        // No view for this action.
        $this->_helper->viewRenderer->setNoRender();

        // Prepare session (allow only one confirmation).
        $this->session->setExpirationHops(2);

        // Check post.
        if (!$this->_checkPost()) {
            $this->_helper->flashMessenger(__("This file doesn't exist."), 'error');
            return $this->_gotoPreviousPage();
        }

        // File is good.
        if ($this->_confirm) {
            // Filepath is not saved in session for security reason.
            $this->session->filename = $this->_filename;
            $this->session->type = $this->_type;
            $this->session->redirect = $this->_redirect;
            $this->_helper->redirector->goto('confirm');
        }
        else {
            $this->_sendFile();
        }
    }

    /**
     * Prepare captcha.
     */
    function confirmAction()
    {
        if (!$this->_checkSession()) {
            $this->_helper->flashMessenger(__('Download error.'), 'error');
            return $this->_gotoPreviousPage();
        }

        $this->session->setExpirationHops(2);

        $form = $this->_getConfirmForm();
        $this->view->form = $form;
        $this->view->filesize = $this->_formatFileSize($this->_file->size);
        $this->view->redirect = $this->session->redirect;

        if (!$this->getRequest()->isPost()) {
            return;
        }

        $post = $this->getRequest()->getPost();
        if (!$form->isValid($post)) {
            $this->_helper->flashMessenger(__('Invalid form input. Please see errors below and try again.'), 'error');
            return;
        }

        // When there is a confirmation, sending is always as attachment.
        $this->session->filename = $this->_filename;
        $this->session->type = $this->_type;
        $this->session->redirect = $this->_redirect;
        $this->_helper->redirector->goto('send');
    }

    /**
     * Send file as attachment.
     */
    function sendAction()
    {
        if (!$this->_checkSession()) {
            $this->_helper->flashMessenger(__('Download error.'), 'error');
            return $this->_gotoPreviousPage();
        }

        $this->view->sendUrl = WEB_ROOT . '/archive-repertory/download/send';
        $this->view->redirect = $this->session->redirect;

        if (!isset($this->session->checked)) {
            $this->view->redirect = $this->session->redirect;
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
        // Everything has been checked.
        $mode = &$this->_mode;
        $filepath = &$this->_filepath;
        $file = &$this->_file;

        $this->getResponse()->clearBody();
        $this->getResponse()->setHeader('Content-Disposition', $mode . '; filename="' . pathinfo($filepath, PATHINFO_BASENAME) . '"', true);
        $this->getResponse()->setHeader('Content-Type', $file->mime_type);
        $this->getResponse()->setHeader('Content-Length', $file->size);
        // Cache for 30 days.
        $this->getResponse()->setHeader('Cache-Control', 'private, max-age=2592000, post-check=2592000, pre-check=2592000', true);
        $this->getResponse()->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT', true);
        $file = file_get_contents($filepath);
        $this->getResponse()->setBody($file);
    }

    /**
     * Check if the post is good and save results.
     *
     * @return boolean
     */
    protected function _checkPost()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();

        // Optional redirection used in case of error.
        $this->_redirect = $this->session->redirect = $request->getParam('redirect');

        // Check storage type.
        $type = $request->getParam('type');
        $storage = $this->_checkStorageType($type);
        if (empty($storage)) {
            return false;
        }

        // Check filename and secure filepath.
        $filename = $request->getParam('filename');
        $filepath = $this->_checkFilename($filename);
        if (empty($filepath)) {
            return false;
        }

        // Check if the file exists in the base.
        // In all case, we redo search from base to avoid session change.
        $file = $this->_file = $this->_getFileFromFilename($filename);
        if (empty($file)) {
            return false;
        }

        // Check if the file belongs to a public item.
        $item = get_record_by_id('Item', $file->item_id);
        if (empty($item)) {
            return false;
        }

        // Check if a confirmation is needed.
        $confirm = $this->_checkConfirmation();

        // Check mode of disposition.
        if ($confirm) {
            $mode = $this->_mode = 'attachment';
        }
        else {
            $mode = $request->getParam('mode');
            $mode = $this->_checkDisposition($mode);
            if (empty($mode)) {
                return false;
            }
        }

        // Update session if needed.
        if (empty($this->session->redirect)) {
            // TODO Redirect to files/show page? Add an option.
            $this->_redirect = $this->session->redirect = record_url($item, null, true);
        }

        return true;
    }

    /**
     * Returns whether the session is valid.
     *
     * Recheck everything for security reason. This will be done only when this
     * is sent after confirmation, as attachment.
     *
     * @return boolean
     */
    protected function _checkSession()
    {
        $requiredKeys = array(
            'filename',
            'type',
            // 'redirect',
        );
        foreach ($requiredKeys as $key) {
            if (!isset($this->session->$key)) {
                return false;
            }
            else {
                $required = '_' . $key;
                $this->$required = $this->session->$key;
            }
        }

        // Check values.
        // Optional redirection used in case of error.
        if (isset($this->session->redirect)) {
            $this->_redirect = $this->session->redirect;
        }

        // Check storage type.
        // In case of a confirmation, there are some params.
        $storage = $this->_checkStorageType($this->_type);
        if (empty($storage)) {
            return false;
        }

        // Check filename and secure filepath.
        $filename = $this->_filename;
        $filepath = $this->_checkFilename($filename);
        if (empty($filepath)) {
                return false;
        }

        // Check if the file exists in the base.
        // In all case, we redo search from base to avoid session change.
        $file = $this->_file = $this->_getFileFromFilename($filename);
        if (empty($file)) {
                return false;
        }

        // Check if the file belongs to a public item.
        $item = get_record_by_id('Item', $file->item_id);
        if (empty($item)) {
                return false;
        }

        // Check mode of disposition.
        // If we come back here, this is a confirmation and mode is attachment.
        $mode = $this->_mode = 'attachment';

        return true;
    }

    /**
     * Check type of storage.
     *
     * @return string Path to the storage of the selected type of file. Empty
     * if incorrect.
     */
    protected function _checkStorageType($type = 'original')
    {
        if (empty($type)) {
            $type = 'original';
        }
        $this->_type = $type;

        // This is used to get list of storage path. Is there a better way?
        // getPathByType() is not secure.

        // For hacked core (before Omeka 2.2).
        $file = new File;
        if (method_exists($file, 'getStoragePathsByType')) {
            $storagePaths = $file->getStoragePathsByType();
            if (!in_array($type, $storagePaths)) {
                return false;
            }
            $this->_storage = $storagePaths[$type];
        }
        // Before Omeka 2.2.
        else {
            $storagePath = $file->getStoragePath($type);
            if ($type == 'original') {
                $this->_storage = substr($storagePath, 0, strlen($storagePath) - 1);
            }
            else {
                $this->_storage = substr($storagePath, 0, strlen($storagePath) - strlen(File::DERIVATIVE_EXT) - 2);
            }
        }
        return $this->_storage;
    }

    /**
     * Check filepath.
     *
     * @return string Path to the file. Empty if error.
     */
    protected function _checkFilename($filename)
    {
        $storagePath = FILES_DIR . DIRECTORY_SEPARATOR . $this->_storage . DIRECTORY_SEPARATOR;
        $filepath = realpath($storagePath . $filename);
        if (strpos($filepath, $storagePath) !== 0) {
            return false;
        }
        $this->_filename = $filename;
        $this->_filepath = $filepath;
        return $this->_filepath;
    }

    /**
     * Check rights to direct download.
     *
     * @return boolean False if confirmation is not needed, else true.
     */
    protected function _checkConfirmation()
    {
        if (is_admin_theme()) {
            $this->_confirm =  false;
        }

        // Check for captcha;
        elseIf ($this->_file->size > (integer) get_option('archive_repertory_warning_max_size_download')) {
            $this->_confirm =  true;
        }

        else {
            $this->_confirm =  false;
        }

        return $this->_confirm;
    }

    /**
     * Check sending mode.
     *
     * @return string Disposition 'inline' (default) or 'attachment'.
     */
    protected function _checkDisposition($mode)
    {
        $filepath = &$this->_filepath;
        $file = &$this->_file;
        $disposition = &$this->_mode;

        // Prepare headers.
        switch ($mode) {
            case 'stream':
            case 'inline':
                $disposition = 'inline';
                break;

            case 'download':
            case 'attachment':
                $disposition = 'attachment';
                break;

            case 'size':
                $disposition = ($file->size > (integer) get_option('archive_repertory_warning_max_size_download'))
                    ? 'attachment'
                    : 'inline';
                break;

            case 'image':
                $disposition = (strpos($file->mime_type, 'image') === false)
                    ? 'attachment'
                    : 'inline';
                break;

            case 'image-size':
                $disposition = (strpos($file->mime_type, 'image') === false
                        || $file->size > (integer) get_option('archive_repertory_warning_max_size_download'))
                    ? 'attachment'
                    : 'inline';
                break;

            default:
                $disposition = 'inline';
        }

        return $disposition;
    }

    /**
     * Get the captcha form.
     *
     * @return ArchiveRepertory_ConfirmForm
     */
    protected function _getConfirmForm()
    {
        require_once PLUGIN_DIR . '/ArchiveRepertory/forms/ConfirmForm.php';
        return new ArchiveRepertory_ConfirmForm();
    }

    /**
     * Redirect to previous page.
     */
    protected function _gotoPreviousPage()
    {
        if ($this->session->redirect) {
            $this->redirect($this->session->redirect);
        }
        else {
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
     * Allow to get file from a filename.
     */
    protected function _getFileFromFilename($filename)
    {
        return get_db()->getTable('File')->findBySql('filename = ?', array($filename), true);
     }
}
