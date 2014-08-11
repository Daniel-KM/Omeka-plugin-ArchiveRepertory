<?php
    $title = __('Download file from the library');
    echo head(array(
        'title' => html_escape($title),
        'bodyclass' => 'primary',
        'content_class' => 'horizontal-nav',
    ));
?>
<div id="primary">
<?php echo flash(); ?>
    <div id='confirm-download'>
        <?php echo flash(); ?>
        <h2><?php echo __('Confirm download'); ?></h2>
        <p><?php echo __('You are going to download a file of %s. Do you confirm?', $filesize); ?></p>
        <p><?php echo __('Go back to the %sprevious page%s.', '<a href="' . $source_page . '">', '</a>'); ?></p>
        <?php echo $this->form; ?>
    </div>
</div>
<?php
    echo foot();
?>
