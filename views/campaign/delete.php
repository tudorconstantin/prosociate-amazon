<?php 
// Check if mass delete
if( $dmMassDelete )
{
    $delHeader = 'Delete Campaigns';
    $delMessage = 'Are you sure you want to delete these campaigns?';
}
else
{
    $delHeader = 'Delete Campaign';
    $delMessage = 'Are you sure you want to delete this campaign?';
}
?>
<div class="wrap">

    <h2><?php echo $delHeader; ?></h2>

    <form method="post">
        <p><?php echo $delMessage; ?></p>
        <p><input type="checkbox" id="is_delete_posts" name="is_delete_posts" value='on' /> <label for="is_delete_posts">Delete associated posts as well</label></p>
        <p class="submit">
            <?php wp_nonce_field('delete-import', '_wpnonce_delete-import') ?>
            <input type="hidden" name="is_confirmed" value="1" />
            <?php
                // Check if mass delete
                if( isset( $_POST['campaign'] ) )
                {
                    echo '<input type="hidden" name="mass_ids" value="' . implode( '-', $_POST['campaign'] ) . '" />';
                }
            ?>
            <input type="submit" class="button-primary" value="Delete" />
        </p>

    </form>

</div>
