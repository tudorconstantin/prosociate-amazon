<?php
if(isset($campaign->options['minprice']))
    $minPrice = $campaign->options['minprice'];
else
    $minPrice = '';

if(isset($campaign->options['maxprice']))
    $maxPrice = $campaign->options['maxprice'];
else
    $maxPrice = '';

if(isset($campaign->options['dmasinlists']))
    $dmAsinLists = $campaign->options['dmasinlists'];
else
    $dmAsinLists = '';
?>
<div style='' class="dm-tab-search">

    Keywords <input type='text' name='keyword' id='keyword' style='width: 300px;' value='<?php echo $campaign->options["keywords"]; ?>' />

    Category <input type='text' class="<?php echo $campaign->options['browsenode']; ?>" name='category' id='category' readonly='readonly' value='<?php echo $campaign->options["category"]; ?>' />

    Sort by <select name='sort' id='sort'><option selected="selected">Default</option></select>
    <input type='hidden' id='sortby' name='sortby' value='<?php echo $campaign->options["sortby"]; ?>' />

    <input class="button button-primary" type='button' id='pros_search_button' value='Search' />

    <br />
    <a href="#" class="dmShowAdvanceSearchFilter">+ Advance Search Filter</a>
    <a href="#" class="dmShowAdvanceSearchFilter" style="display: none;">- Advance Search Filter</a>

    <div id="dmPros_advanceSearch" style="display: none;">
        <h3>Advance Search Filters</h3>
        Min. Price: <input id="dmminprice" type="text" name="dmminprice" value="<?php echo $minPrice; ?>"/>
        Max Price: <input id="dmmaxprice" type="text" name="dmmaxprice" value="<?php echo $maxPrice; ?>" />
        <br />
        ASIN Lists: <input id="dmasinlists" type="text" name="dmasinlists" value="<?php echo $dmAsinLists; ?>"/> <small>(Separate with commas)</small>
    </div>

    <br /><br />

    <input type='hidden' id='searchindex' name='searchindex' value='<?php echo $campaign->options["searchindex"]; ?>' />
    <input type='hidden' id='browsenode' name='browsenode' value='<?php echo $campaign->options["browsenode"]; ?>' />
    <input type='hidden' id='nodepath' name='nodepath' value='<?php echo $campaign->options["nodepath"]; ?>' />
    <input type='hidden' id='ASINs' name="ASINs" value='<?php echo $campaign->options["ASINs"]; ?>' />
    <input type='hidden' id='tmp_nodepath' value='' />

    <div id='pros_adv_search' style='display: none;'>

        <u><b>Advanced Search</b></u>
        <br />
        <br />

        <label for="availability_chk">Availabile Product Only</label>
        <input type="checkbox" id="availability_chk" name="availability_chk" value="Available" <?php if (!empty($campaign->options["availability"])) echo 'checked="checked"'; ?> onchange="check_availability_options(this)" />
        <input type="hidden" id="availability" name="availability" value="<?php echo $campaign->options["availability"]; ?>" />
        <br />

        <label for="merchantid_chk">Amazon Product Only</label>
        <input type="checkbox" id="merchantid_chk" name="merchantid_chk" value="Amazon" <?php if (!empty($campaign->options["merchantid"])) echo 'checked="checked"'; ?> onchange="check_availability_options(this)" />
        <input type="hidden" id="merchantid" name="merchantid" value="<?php echo $campaign->options["merchantid"]; ?>" />
        <br />

        <label for="condition">Condition</label>
        <select id="condition" name="condition">
            <option value="New" selected="selected">New</option>
            <option value="Used">Used</option>
            <option value="Collectible">Collectible</option>
            <option value="Refurbished">Refurbished</option>
            <option value="All">All</option>
        </select>
        <br />

        <label for="manufacturer">Manufacturer</label>
        <input type="text" id="manufacturer" name="manufacturer" value="<?php echo $campaign->options["manufacturer"]; ?>" />
        <br />

        <label for="brand">Brand</label>
        <input type="text" id="brand" name="brand" value="<?php echo $campaign->options["brand"]; ?>" />
        <br />

        <label for="minpercentageoff">Min Percentage Off</label>
        <input type="text" id="minpercentageoff" name="minpercentageoff" value="<?php echo $campaign->options["minpercentageoff"]; ?>" />

    </div>
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

    <div id='jstree_upper_container'>
        <div id='jstree_container'></div>
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