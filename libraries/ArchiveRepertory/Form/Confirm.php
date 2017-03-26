<?php
/**
 * ArchiveRepertory_ConfirmForm class - represents the form to confirm send of a file.
 */
class ArchiveRepertory_Form_Confirm extends Omeka_Form
{
    /**
     * Initialize the form.
     */
    public function init()
    {
        parent::init();

        $this->setAction(WEB_ROOT . '/archive-repertory/download/confirm');
        $this->setAttrib('id', 'confirm-form');
        $user = current_user();

        // Assume registered users are trusted and don't make them play recaptcha.
        if (!$user && get_option('recaptcha_public_key') && get_option('recaptcha_private_key')) {
            $this->addElement('captcha', 'captcha',  array(
                'class' => 'hidden',
                'label' => __('Please verify you’re a human'),
                'captcha' => array(
                    'captcha' => 'ReCaptcha',
                    'pubkey' => get_option('recaptcha_public_key'),
                    'privkey' => get_option('recaptcha_private_key'),
                    'ssl' => true, //make the connection secure so IE8 doesn't complain. if works, should branch around http: vs https:
                ),
                'decorators' => array(),
            ));
        }

        // The legal agreement is checked by default for logged users.
        if (get_option('archive_repertory_legal_text')) {
            $this->addElement('checkbox', 'archive_repertory_legal_text', array(
                'label' => get_option('archive_repertory_legal_text'),
                'value' => (boolean) $user,
                'required' => true,
                'uncheckedValue' => '',
                'checkedValue' => 'checked',
                'validators' => array(
                    array('notEmpty', true, array(
                        'messages' => array(
                            'isEmpty' => __('You must agree to the terms and conditions.'),
                        ),
                    )),
                ),
                'decorators' => array('ViewHelper', 'Errors', array('label', array('escape' => false))),
            ));
        }

        // The legal agreement is checked by default for logged users.
        if (get_option('archive_repertory_legal_text')) {
            $this->addElement('checkbox', 'archive_repertory_legal_text', array(
                'label' => get_option('archive_repertory_legal_text'),
                'value' => (boolean) $user,
                'required' => true,
                'uncheckedValue' => '',
                'checkedValue' => 'checked',
                'validators' => array(
                    array('notEmpty', true, array(
                        'messages' => array(
                            'isEmpty' => __('You must agree to the terms and conditions.'),
                        ),
                    )),
                ),
                'decorators' => array('ViewHelper', 'Errors', array('label', array('escape' => false))),
            ));
        }

        $this->addElement('submit', 'submit', array(
            'label' => __('Confirm'),
        ));
    }
}
