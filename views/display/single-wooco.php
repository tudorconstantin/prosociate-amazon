<?php
$ItemAttributes = unserialize(get_post_meta($post->ID, '_pros_ItemAttributes', true));
$Offers = unserialize(get_post_meta($post->ID, '_pros_Offers', true));
$OfferSummary = unserialize(get_post_meta($post->ID, '_pros_OfferSummary', true));
$EditorialReviews = unserialize(get_post_meta($post->ID, '_pros_EditorialReviews', true));

$DetailPageURL = get_post_meta($post->ID, '_pros_DetailPageURL', true);
$ASIN = get_post_meta($post->ID, '_pros_ASIN', true);


$CustomerReviews = unserialize(get_post_meta($post->ID, '_pros_CustomerReviews', true));

// Get alternative description
$altDesc = get_post_meta($post->ID, '_pros_alt_prod_desc', true);
?>
<?php
if($altDesc == false || empty($altDesc)) {
    if ($EditorialReviews->EditorialReview) {

        if (count($EditorialReviews->EditorialReview) == 1) {
            echo "<p class='pros_product_description'>";
            if ($EditorialReviews->EditorialReview->Source != "Product Description") {
                echo "<b>" . $EditorialReviews->EditorialReview->Source . "</b><br />";
            }

            echo $EditorialReviews->EditorialReview->Content;
            echo "</p>";
        } else {
            foreach ($EditorialReviews->EditorialReview as $er) {
                echo "<p class='pros_product_description'>";
                echo "<b>" . $er->Source . "</b><br />";
                echo $er->Content;
                echo "</p>";
            }
        }
    }
    if (is_array($ItemAttributes->Feature)) {
        ?>

        <p class='pros_product_description'>
            <b>Features</b>
        <ul>
        <?php
        foreach ($ItemAttributes->Feature as $feature) {
            echo "<li>" . $feature;
        }
        ?>
        </ul>

        </p>

        <?php
    } else if (count($ItemAttributes->Feature) == 1) {
        ?>

        <p class='pros_product_description'>
        <ul>
            <?php
            echo "<li>" . $ItemAttributes->Feature;
            ?>
        </ul>
        </p>
    <?php
    }
} else {
    echo "<p class='pros_product_description'>";
    echo html_entity_decode($altDesc);
    echo "</p>";
}