<?php
// Check if "Select categories was checked"
$dmDisplay = 'none';
if (isset($campaign->post_options['dm_select_category'])) {
    if ($campaign->post_options['dm_select_category'] == 'yes') {
        $dmDisplay = 'inline';
        $checkDMCategory = 'checked="checked"';
    } else {
        $checkDMCategory = '';
    }
} else {
    $checkDMCategory = '';
}

if(isset($campaign->post_options['download_images'])) {
    if($campaign->post_options['download_images'] == 'on') {
        $postDownloadImage = 'checked="checked"';
    } else {
        $postDownloadImage = '';
    }
} else {
    $postDownloadImage = '';
}

if(isset($campaign->post_options['manual_gallery'])) {
    if($campaign->post_options['manual_gallery'] == 'on') {
        $postManualGallery = 'checked="checked"';
    } else {
        $postManualGallery = '';
    }
} else {
    $postManualGallery = '';
}

if(isset($campaign->post_options['comment_status'])) {
    if($campaign->post_options['comment_status'] == 'open') {
        $checkCommentStatus = 'checked="checked"';
    } else {
        $checkCommentStatus = '';
    }
} else {
    $checkCommentStatus = '';
}

if(isset($campaign->post_options['ping_status'])) {
    if($campaign->post_options['ping_status'] == 'open') {
        $checkPingStatus = 'checked="checked"';
    } else {
        $checkPingStatus = '';
    }
} else {
    $checkPingStatus = '';
}

if(isset($campaign->post_options['excerpt'])) {
    if($campaign->post_options['excerpt'] =='on' ) {
        $checkPostExcerpt = 'checked="checked"';
    } else {
        $checkPostExcerpt = '';
    }
} else {
    $checkPostExcerpt = '';
}

if(isset($campaign->post_options["excerpt_template"])) {
    $checkExcerptTemplate = $campaign->post_options["excerpt_template"];
} else {
    $checkExcerptTemplate = '';
}
?>

<div style='display: none;'>

    <b>Create posts as...</b><br />

    <select name='post_options[post_type]' id='post_options_post_type'>

        <?php
        $posttypes = get_post_types();

        foreach ($posttypes as $posttype) {

            if ($posttype == 'attachment' || $posttype == 'revision' || $posttype == 'nav_menu_item') {
                continue;
            }

            echo "<option value='" . $posttype . "' " . ($campaign->post_options['post_type'] == $posttype ? 'selected' : '') . ">";

            if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                if ($posttype == 'product') {
                    echo 'WooCommerce Products';
                } else {
                    echo $posttype;
                }
            } else {
                echo $posttype;
            }

            echo "</option>";
        }
        ?>
    </select>

    <?php
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        if ($campaign->post_options['post_type'] == 'product') {
            $proso_woocart_option_display = '';
        } else {
            $proso_woocart_option_display = 'display: none;';
        }
        ?>

        <div id='proso_woocart_option' style='<?php echo $proso_woocart_option_display; ?>'>
            <input type='checkbox' name='post_options[woocart]' id='post_options[woocart]' value='on' <?php echo ($campaign->post_options['woocart'] == 'on' ? 'checked="checked"' : ''); ?> /> <label for='post_options[woocart]'>WooCommerce On-Site Shopping Cart Integration <small><br />(customers can manage their shopping cart on your site, and will be taken to Amazon to complete their order upon checking out)</small></label>
        </div>

        <?php
    }
    ?>



    <br />

    <br />

</div>



<div style='display: none;'>
    <br /><br />
    <b>Images</b><br />
    <input type='checkbox' name='post_options[download_images]' id='post_options[download_images]' value='on' <?php echo $postDownloadImage; ?> /> <label for='post_options[download_images]'>Download Product Images And Add Them To The Media Gallery/Featured Image</label>
    <br />
    <input type='checkbox' name='post_options[manual_gallery]' id='post_options[manual_gallery]' value='on' <?php echo $postManualGallery; ?> /> <label for='post_options[manual_gallery]'>Insert Manual Image Gallery Into Posts (Only use if your theme does not automatically display an image gallery)</label>
</div>


<h3>Categories</h3>

<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Choose Categories</th>
        <td class="forminp">

            <input type='checkbox' name='post_options[dm_select_category]' id='post_options-dm_select_category' value='yes' <?php echo $checkDMCategory; ?> /> <span id="dm_select_category_post_options"><label for='post_options[dm_select_category]'>Assign Products to the Selected Categories</label></span><br />
            <div id="poststuff" class="metabox-holder" style="display: <?php echo $dmDisplay; ?>;">
                <?php $meta_boxes = do_meta_boxes('prosociate_page_prossociate_addedit_campaign', 'side', $test_object = ''); ?>
            </div>

        </td>
    </tr>

</table>

<h3>Advanced Options</h3>

<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Attributes/Additional Information</th>
        <td class="forminp">
            <input type="checkbox" name="dmAdditionalAttributes" id="dmAdditionalAttributes" value="true" checked="checked"/> <label for="dmAdditionalAttributes">Do not add attributes that aren’t present for variations.</label>
            <input type="hidden" id="dmAdditionalAttributesCheck" name="dmAdditionalAttributesCheck" value="true"/>
            <p class="description">Use this to stop the Additional Information tab from filling up with lots of unnecessary product attributes. Uncheck this and all attributes will be added.</p>
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Product As</th>
        <td class="forminp">
            <select name="post_options[externalaffilate]" id="post_options[externalaffilate]">
                <option value="affiliate">External/Affiliate</option>
            </select>
        </td>
    </tr>

    <?php /*
    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Status</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[draft]' id='post_options[draft]' value='draft' <?php echo $checkPostStatus; ?> /> <label for='post_options[draft]'>Post As Draft</label><br />
        </td>
    </tr>
    */ ?>


    <tr valign="top" class="">
        <th scope="row" class="titledesc">Discussion</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[comment_status]' id='post_options[comment_status]' value='open' <?php echo $checkCommentStatus; ?> /> <label for='post_options[comment_status]'>Allow Comments</label><br />
            <input type='checkbox' name='post_options[ping_status]' id='post_options[ping_status]' value='open' <?php echo $checkPingStatus; ?> /> <label for='post_options[ping_status]'>Allow Trackbacks/Pingbacks</label><br />
        </td>
    </tr>


    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Author</th>
        <td class="forminp">
            <?php
                if(isset($post['author'])) {
                    wp_dropdown_users(array('name' => 'post_options[author]', 'selected' => $post['author']));
                } else {
                    wp_dropdown_users(array('name' => 'post_options[author]'));
                }
            ?>
            <?php ; ?>
        </td>
    </tr>

</table>

<div style='display: none;'>
    <input type='checkbox' name='post_options[excerpt]' id='post_options[excerpt]' value='on' <?php echo $checkPostExcerpt; ?> /> 
    <label for='post_options[excerpt]'>Post Excerpt</label> <input type='text' name='post_options[excerpt_template]' value='<?php echo $checkExcerptTemplate; ?>' /><br />
</div>


<div class="dm-campaign-post-button dm-hide-first" style="display:none;">
    <input class="wp-core-ui button-primary dm-save-campaign-button" type='button' name='pros_save_submit_post' value='Save Campaign & Post Products' /><br />
    <!-- Yuri -->
</div>

