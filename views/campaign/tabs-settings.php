<h3>Campaign Friendly Name</h3>


<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Friendly Name</th>
        <td class="forminp">
            <fieldset>
                <input type='text' name='campaign_name' id='campaign_name' style='width: 250px;' value='<?php echo $campaign->name; ?>'><br />
                <input type='hidden' id='campaign_id' name='campaign_id' value='<?php echo $campaign->id; ?>' />
                <p class="description">Shown on the Manage Campaigns screen.</p>
            </fieldset>
        </td>
    </tr>

</table>

<h3>Update Settings</h3>

<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Product Availability</th>
        <td class="forminp">
            <fieldset>
                <select name='campaign_settings[availability]'>
                    <option value='remove' <?php echo ($campaign->campaign_settings['availability'] == 'remove' ? 'selected' : ''); ?>>Remove unavailable product</option>
                    <option value='change' <?php echo ($campaign->campaign_settings['availability'] == 'change' ? 'selected' : ''); ?>>Change product stock status to "out of stock" for unavailable products.</option>
                </select>
                <p class="description">When a product is no longer "available" on Amazon.</p>
            </fieldset>
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">New Search Results</th>
        <td class="forminp">
            <fieldset>
                <select name='campaign_settings[reperform]'>
                    <option value='create' <?php echo ($campaign->campaign_settings['reperform'] == 'create' ? 'selected' : ''); ?>>Create a new post for each product that hasn't already been posted.</option>
                    <option value='existing' <?php echo ($campaign->campaign_settings['reperform'] == 'existing' ? 'selected' : ''); ?>>Ignore new products, only update existing posts.</option>
                </select>
                <p class="description">When new products appear in the search results.</p>
            </fieldset>
        </td>
    </tr>

    <?php /*
    <tr valign="top" class="">
        <th scope="row" class="titledesc">Data Caching</th>
        <td class="forminp">
            <fieldset>

                <input type='checkbox' name='campaign_settings[refresh]' id='campaign_settings[refresh]' <?php echo ($campaign->campaign_settings['refresh'] == 'refresh' ? 'checked="checked"' : ''); ?> value='refresh' /> <label for='campaign_settings[refresh]'>Update existing products with most current data every</label> <input type='text' name='campaign_settings[refresh_time]' value='<?php echo $campaign->campaign_settings["refresh_time"]; ?>' /> hours.

                <p class="description">If this setting is not checked, data will never be updated.</p>
            </fieldset>
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Campaign Cron URL</th>
        <td class="forminp">
            <fieldset>

                <?php
                if (!$campaign->id) {
                    ?>
                    <i>Please save your campaign to generate a cron URL.</i>
                    <?php
                } else {
                    ?>
                    <input type='text' style='width: 500px; font-size: 1.0em;' readonly value='<?php echo get_bloginfo('url'); ?>/?proso-cron-key=1234&campaign_id=<?php echo $campaign->id; ?>' />

                    <br /><br />
                    You can set up a cron job in your web hosting control panel. Contact your web hosting provider for more information.
                    <?php
                }
                ?>

                <p class="description">You must set this cron URL to run every five minutes for product data to be automatically updated.</p>
            </fieldset>
        </td>
    </tr>
     * 
     */?>

</table>

<div class="dm-campaign-post-button dm-hide-first" style="display: none;">
    <input class="wp-core-ui button-primary dm-save-campaign-button" type='button' name='pros_save_submit_post' value='Save Campaign & Post Products' /><br />
    <!-- Yuri -->
    <input type="hidden" id="pros_submit_type" name="pros_submit_type" value="" />
</div>
