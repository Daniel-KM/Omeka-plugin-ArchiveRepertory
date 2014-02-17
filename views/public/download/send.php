<?php
    $title = __('File is downloading');
    // head() cannot be call directly, because we need to add a meta tag.
    ob_start();
    echo head(array(
        'title' => $title,
        'bodyclass' => 'primary horiz',
    ));
    $header = ob_get_contents();
    ob_end_clean();
    $header = substr_replace($header, '<meta http-equiv="refresh" content="5;url=' . $sendUrl . '">', strpos($header, '<meta '), 0);
    echo $header;
?>
<div id="primary">
<?php echo flash(); ?>
    <div id='send-download'>
        <?php echo flash(); ?>
        <h2><?php echo __('Downloading file'); ?></h2>
        <p><?php echo __('Your download should start automatically in five seconds. If not, click %shere%s.', '<a href="' . $sendUrl . '">', '</a>'); ?>
        <p><?php echo __('Go back to the %sprevious page%s.', '<a href="' . $redirect . '">', '</a>'); ?></p>
    </div>
</div>
<?php
    echo foot();
?>
