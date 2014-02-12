<?php

$CustomerReviews		= unserialize(get_post_meta($post->ID, '_pros_CustomerReviews', true));

$iframe_width = get_option('prossociate_settings-iframe-width');
$iframe_height = get_option('prossociate_settings-iframe-height');


if ($CustomerReviews->HasReviews) {

	?>

	<div id='pros_reviews' class='pros_reviews'>

	<?php

	if ( !have_comments() ) {

		echo '<p class="add_review"><a href="#review_form" class="inline show_review_form button" rel="prettyPhoto" title="' . __( 'Add Your Review', 'woocommerce' ) . '">' . __( 'Add Review', 'woocommerce' ) . '</a></p>';

	}


	?>

        <?php // hardhack - replace the expiration 2014 to 2020 //
        $iframeUrl = str_replace('exp=2014-', 'exp=2020-', $CustomerReviews->IFrameURL);
        ?>
        <iframe src='<?php echo $iframeUrl; ?>' style='margin-top: 24px;' width='<?php echo $iframe_width; ?>' height='<?php echo $iframe_height; ?>'></iframe>
	</div>

	<script>
	jQuery("#pros_reviews").appendTo("#comments");
	jQuery('.noreviews').hide();
	</script>

	<?php
}

?>




