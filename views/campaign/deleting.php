<?php
// This file is the view for the deleting process..

// Check if we are on single campaign delete or mass campaign delete
if(isset($_REQUEST['campaign_id'])) {
    $campaignId = (int)$_REQUEST['campaign_id'];
} else {
    $campaignId = 0;
}
// mass delete
if(isset($_REQUEST['mass_ids'])) {
    $massIds = $_REQUEST['mass_ids'];
} else {
    $massIds = '0';
}

// Check if we will delete associated posts
if(isset($_REQUEST['is_delete_posts']) && $_REQUEST['is_delete_posts'] === 'on') {
    $deleteAssocPosts = 'true';
} else {
    $deleteAssocPosts = 'false';
}
?>
<div id="dmDelCampWrap" class="wrap">
    <p>Deleting Campaign ID <?php echo $_REQUEST['campaign_id']; ?></p>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var data = {
            action: 'prosociate_campaignDelete',
            campId: '<?php echo $campaignId; ?>',
            massIds: '<?php echo $massIds; ?>',
            deletePosts: '<?php echo $deleteAssocPosts; ?>'
        };
        dmCampaignDelete(data);
    });
</script>