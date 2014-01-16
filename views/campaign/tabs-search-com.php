<?php
global $woocommerce;
?>
<div style='' class="dm-tab-search" xmlns="http://www.w3.org/1999/html">

    Category <input type='text' class="<?php echo $campaign->options['browsenode']; ?>" name='category' id='category' readonly='readonly' value='<?php echo $campaign->options["category"]; ?>' />

    Sort by <select name='sort' id='sort'><option selected="selected">Default</option></select>
    <input type='hidden' id='sortby' name='sortby' value='<?php echo $campaign->options["sortby"]; ?>' />

    Keywords <small>(optional)</small> <input type='text' name='keyword' id='keyword' style='width: 300px;' value='<?php echo $campaign->options["keywords"]; ?>' />

    <input class="button button-primary" type='button' id='pros_search_button' value='Search' />

    <br /><br />

    <input type='hidden' id='searchindex' name='searchindex' value='<?php echo $campaign->options["searchindex"]; ?>' />
    <input type='hidden' id='browsenode' name='browsenode' value='<?php echo $campaign->options["browsenode"]; ?>' />
    <input type='hidden' id='nodepath' name='nodepath' value='<?php echo $campaign->options["nodepath"]; ?>' />
    <input type='hidden' id='ASINs' name="ASINs" value='<?php echo $campaign->options["ASINs"]; ?>' />
    <input type='hidden' id='tmp_nodepath' value='' />

    <div id="pros_serps_wrapper">
        <!-- For loading amazon -->
        <div id="dm-overlay-search" class="dm-overlay-class dm-waiter-search" style="display: none;">
            <div class="dm-waiter-img">
                <div class="dm-waiter-text">Loading results from Amazon...</div>
            </div>
        </div>
        <!-- For no category -->
        <div id="dm-overlay-no-category" class="dm-overlay-class dm-waiter-search" style="display: none;">
            <div class="dm-waiter-img-category">
                <div class="dm-waiter-text">Please select a category...</div>
            </div>
        </div>
        <div id="dm-waiter-search-overlay" style="display: none;">

        </div>
        <div id='pros_serps'>

        </div>
    </div>
</div>

<?php if( !isset($_REQUEST['campaign_id']) ) { ?>
    <div id="dm-pros-new-campaign" class="about-text">
        Find Amazon products to add by entering search keywords and a category
    </div>
<?php } ?>

<div title='Choose a category' style='display: none;' id='cattree_container' style='width: 400px;'>
    <div class="dmBrowseNodeChoice">
        Choose a BrowseNode
        <div id='jstree_upper_container'>
            <div id='jstree_container'></div>
        </div>
    </div>
</div>

<br clear='all' />

<!-- Yuri -->
<script type="text/javascript">
    var selected_cnt = 0;
    <?php
    if (isset($campaign->options)) {
        ?>
    jQuery(document).ready(function() {
        // load search parameters
        load_browsenode_sortvalues('<?php echo $campaign->options["searchindex"]; ?>', '<?php echo $campaign->options["sortby"]; ?>');
        jQuery("#condition").val('<?php echo $campaign->options["condition"]; ?>');
    });
    <?php
}
?>
</script>