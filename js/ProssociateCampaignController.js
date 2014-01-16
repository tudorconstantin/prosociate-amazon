jQuery(document).ready(function($) {
    $("#tabs-general-settings-link").click(function(){
        $("#tabs-compliance-settings").hide();
        $("#tabs-general-settings").show();
        $("#tabs-general-settings-link").addClass("nav-tab-active");
        $("#tabs-compliance-settings-link").removeClass("nav-tab-active");
    });

    $("#tabs-compliance-settings-link").click(function(){
        $("#tabs-general-settings").hide();
        $("#tabs-compliance-settings").show();
        $("#tabs-compliance-settings-link").addClass("nav-tab-active");
        $("#tabs-general-settings-link").removeClass("nav-tab-active");
    });
    	// louis tabs addition 	
		$("#tabs-post").hide();
		$("#tabs-settings").hide();
		
		$("#tabs-search-link").click(function() {
			$("#tabs-post").hide();
			$("#tabs-settings").hide();
			$("#tabs-search").show();
			
			$('#tabs-search-link').addClass('nav-tab-active');
			$('#tabs-settings-link').removeClass('nav-tab-active');
			$("#tabs-post-link").removeClass('nav-tab-active');
			

		});	
		$("#tabs-post-link").click(function() {
			$("#tabs-search").hide();
			$("#tabs-settings").hide();
			$("#tabs-post").show();

			$('#tabs-post-link').addClass('nav-tab-active');
			$('#tabs-settings-link').removeClass('nav-tab-active');
			$("#tabs-search-link").removeClass('nav-tab-active');

		});	
		$("#tabs-settings-link").click(function() {
			$("#tabs-post").hide();
			$("#tabs-search").hide();
			$("#tabs-settings").show();

			$('#tabs-settings-link').addClass('nav-tab-active');
			$('#tabs-search-link').removeClass('nav-tab-active');
			$("#tabs-post-link").removeClass('nav-tab-active');

		});	
	// --- end louis tabs addition
    // Initialize the dialog
    $('#cattree_container').dialog({
        autoOpen: false,
        height: 600,
        position: { my: "left top", at: "left top+10", of: "#category" },
        open: function(event, ui) {
            if(document.getElementById('dmCatManual') != null ) {
                $('#dmCatManual').blur();
            }
        }
    });

    // Tooltips
    $(".tips, .help_tip").tipTip({
        'attribute' : 'data-tip',
        'fadeIn' : 50,
        'fadeOut' : 50,
        'delay' : 200
    });

    // Advance search area
    $('.dmShowAdvanceSearchFilter').click(function(){
        $('.dmShowAdvanceSearchFilter').toggle();
        $('#dmPros_advanceSearch').toggle();
    })

    // open the category tree on click of the textbox
    $('#category').click(function() {
        $("#cattree_container").dialog("open");
    });

    $('body').bind('click', function(e){
        if( !$(e.target).is('#category') && !$(e.target).closest('.ui-dialog').length ) {
            if( $("#cattree_container").dialog("isOpen") ) {
                $("#cattree_container").dialog("close");
            }
        }
    });


    // yuri - validate search options
    $('#pros_save_submit').click(function() {
        if (validate_search_options()) {
            $('#pros_submit_type').val('Save Campaign');
            $('#campaign_form').submit();
        }
    });
    $('.dm-save-campaign-button').click(function() {
        if (validate_search_options()) {
            $('#pros_submit_type').val('Save Campaign & Post Products');
            $('#campaign_form').submit();
        }
    });

    $('#dmAmazonOnly').change(function(){
        var dmAmazonOnly = this.checked;
        if(dmAmazonOnly) {
            $('#dmAmazonOnlyCheck').val('Amazon');
        } else {
            $('#dmAmazonOnlyCheck').val('');
        }
    });

    $('#dmAdditionalAttributes').change(function(){
        var dmAdditionalAttributes = this.checked;
        if(dmAdditionalAttributes) {
            $('#dmAdditionalAttributesCheck').val('true');
        } else {
            $('#dmAdditionalAttributesCheck').val('');
        }
    });

    // data picker
    $(".datepicker").datepicker({constrainInput: false});

    // search button
    $('#pros_search_button').click(function() {
        $('#dm-pros-new-campaign').hide();
        $('#dm-overlay-no-category').hide();
        var testHolder;
        // Get product results dom
        var testResults = $('.pros_single_product_container');
        //console.log(testResults);
        
        var searchindex = document.getElementById('searchindex').value;


            if (searchindex == '') {
                //Display the loading image
                $('#dm-overlay-no-category').show();
                return;
                //$('#dm-waiter-search-overlay-no-category').addClass('dm-overlay-on');
                //$('#dm-overlay-search').show();
            }
        
        //if( searchindex != '' ) {
            // Check if there are results
            if( testResults.length > 0 )
            {
                testHolder = 'notFirst';
                $('.dm-waiter').show();
                $('#dm-overlay').addClass('dm-overlay-on');
            }
            else
            {
                testHolder = 'firstTime';
                // Display the loading image
                $('#dm-waiter-search-overlay').show();
                $('#dm-waiter-search-overlay').addClass('dm-overlay-on');
                $('#dm-overlay-search').show();
            }
        //}
        
        
        
        // yuri - validate search options
        if (!validate_search_options()) {
            return;
        }


        prossociate_search(testHolder);

    });

    function prossociate_search( testHolder ) {
        
        if( testHolder === 'firstTime' ) {
        // Display the loading image
            $('#dm-waiter-search-overlay').show();
            $('#dm-waiter-search-overlay').addClass('dm-overlay-on');
            $('#dm-overlay-search').show();
        }

        // yuri - add browsenode, category, sortby parameter and advanced search options
        var data = {
            action: 'prossociate_search',
            category: $('#category').val(),
            searchindex: $('#searchindex').val(),
            browsenode: $('#browsenode').val(),
            keyword: $('#keyword').val(),
            sortby: $('#sortby').val(),
            page: 1,
            addAttributes: $('#dmAdditionalAttributesCheck').val()
        };

        $.post(ajaxurl, data, function(response) {
            if( testHolder === 'firstTime' )
            {
                $('#dm-waiter-search-overlay').hide();
                $('#dm-overlay-search').hide();
                $('#dm-waiter-search-overlay').removeClass('dm-overlay-on');
            }
            else if( testHolder === 'notFirst' )
            {
                $('.dm-waiter').hide();
                $('#dm-overlay').removeClass('dm-overlay-on');
            }
            
            jQuery('#pros_serps').html(response);
            prossociate();
            dmdisplaysubmitbutton();
        });  
    }
    
    function dmdisplaysubmitbutton() {
        $('.dm-hide-first').show();
    }

    // search pagination
    function prossociate() {

        $('.pros_page_link').click(function(e) {

            e.preventDefault();
            $('.dm-waiter').show();
            $('#dm-overlay').addClass('dm-overlay-on');
            // yuri - add browsenode, category, sortby parameter
            var data = {
                action: 'prossociate_search',
                category: $('#category').val(),
                searchindex: $('#searchindex').val(),
                browsenode: $('#browsenode').val(),
                keyword: $('#keyword').val(),
                sortby: $('#sortby').val(),
                page: $(this).attr('pros:page'),
                addAttributes: $('#dmAdditionalAttributesCheck').val()
            };

            $.post(ajaxurl, data, function(response) {
                // Display the loading image
                var dmAsins = jQuery('#ASINs').val();
                var dmProdCount;
                // Check if there are values
                if( dmAsins )
                {
                    dmProdCount = dmAsins.split(',').length;
                }
                else
                {
                    dmProdCount = 0;
                }
                //console.log( dmProdCount );
                $('.dm-waiter').hide();
                $('#dm-overlay').removeClass('dm-overlay-on');
                jQuery('#pros_serps').html(response);
                if( dmProdCount == 0 )
                {
                    // If no value selected value. Then get the initial number of results
                    // This is to fix when the search results is less than 100
                    dmProdCount = 'All <span class="dm-available-results">' + parseInt($('.dm-available-results').html()) + '</span> available results will be posted. <a id="dm-tooltip" class="help_tip" data-tip="Click checkboxes to only post selected results" href="#"><img src="../wp-content/plugins/woocommerce/assets/images/help.png" height="16px" width="16px"/></a>';
                }
                else if( dmProdCount == 1 )
                {
                    dmProdCount = '<span class="dm-available-results">1</span> selected result will be posted.<span class="dm-select-all"><a class="dm-select-all-link" href="#">Select all results.</a></span>';
                }
                else
                {
                    dmProdCount = '<span class="dm-available-results">' + dmProdCount + '</span> selected results will be posted.<span class="dm-select-all"><a class="dm-select-all-link" href="#">Select all results.</a></span>';
                }
                jQuery('#selected_products').html(dmProdCount); // detect the selected products count  - added by Darby Jin
                prossociate();
                
                dm_select_all_products();
                dm_tooltip();
            });

        });

    }

    // search advanced box
    $('#pros_adv_search_link').click(function(e) {

        e.preventDefault();
        $('#pros_adv_search').toggle('slow');

    });

    // jquery ui tabs
    $("#pros_camp_tabs").tabs();

    // tree
    var category_tree = $("#jstree_container").jstree({
        html_data: {
            ajax: {
                url: ajaxurl + '?action=prossociate_search_node',
                data: function(n) {
                    return {
                        id: (n.attr ? n.attr("id") : '-2000'),
                        nodes: (n.attr ? n.attr("nodes") : ''),
                        root: (n.attr ? n.attr("root") : '')
                    }; // yuri - add node tree path
                }
            }
        },
        "plugins": ["themes", "html_data"]

                // yuri - load category path for selected node
    }).bind("loaded.jstree", function(event, data) {
        var initCat = $("#category").attr( 'class' );
        if( initCat )
        {
            //console.log( initCat );
            //$( "#" + initCat + ' a').css( 'background-color', '#a3b9ff' );
            $( "#" + initCat).css( 'background-color', 'rgb(240, 232, 232)' );
        }
    }).bind("loaded.jstree", function(event, data) {
        if ($("#nodepath").val() != '') {
            $("#tmp_nodepath").val($("#nodepath").val());
        }
        open_browse_node_path();
    }).bind("after_open.jstree", function(event, data) {
        open_browse_node_path();
    });


    // yuri - open selected category tree path
    function open_browse_node_path() {
        var nodepath = $("#tmp_nodepath").val();
        if (nodepath != '') {
            var nodeids = nodepath.split(',');
            if (nodeids.length > 0) {
                var nodeid = nodeids[0];
                $("#" + nodeid + " > ins").click();
                if (nodeids.length == 1) {
                    nodepath = '';
                } else {
                    nodepath = nodepath.substring(nodeid.length + 1, nodepath.length);
                }
                $("#tmp_nodepath").val(nodepath);
            }
            
        }
    }

    // on-site cart checkmark on Post Options tab
    $("#post_options_post_type").change(function() {
        if ($(this).val() == 'product') {
            $("#proso_woocart_option").show('fast');
        } else {
            $("#proso_woocart_option").hide('fast');
        }
    });

    // Load edit campaign
    if ( $("#searchindex").val() != '') {
        prossociate_search( 'firstTime' );
    }

    $('#dm_select_category_post_options').click(function(){
        var dmischeck = $('#post_options-dm_select_category').is(':checked');
        if( dmischeck )
        {
            //$('#post_options-dm_select_category').prop("checked", true);
            $('#post_options-dm_select_category').removeAttr('checked');
            $('#poststuff').hide();
        } else {
            //$('#post_options-dm_select_category').prop("checked", false);
            $('#post_options-dm_select_category').attr('checked','checked');
            $('#poststuff').show();
        }
    });
    
    // For the category meta box
    $('#post_options-dm_select_category').change(function(e){
        var dmischeck = $('#post_options-dm_select_category').attr('checked');
        if( dmischeck === 'checked' )
        {
            $('#poststuff').show();
        }
        else {
            $('#poststuff').hide();
        }
        
    });
});

// The previous selected node
var pastNodeId;

// yuri - set browse node value into serach index box
function prossociate_select_browsenodes(nodeid, nodename, root) {
    jQuery("#category").val(nodename);
    jQuery("#browsenode").val(nodeid);
    jQuery("#nodepath").val(jQuery("#" + nodeid).attr('nodes'));
    var searchindex = jQuery("#searchindex").val();
    //if (searchindex == '' || (nodename == root && searchindex != root)) {
    //    jQuery("#searchindex").val(root);
    //    load_browsenode_sortvalues(root);
    //}
    jQuery("#searchindex").val(root);
    load_browsenode_sortvalues(root);
    
    // Initial category
    var initNode = jQuery("#category").attr('class');
    
    if( initNode == null || initNode == undefined || initNode == '' )
    {
        initNode = nodeid;
    }
    
    // To clean the init cat 
    if (initNode !== nodeid)
    {
        // Clean the previous selected node
        //jQuery("#" + initNode + ' a:first').css('background-color', '#ffffff');
        jQuery("#" + initNode).css('background-color', '#ffffff');
    }

    // Check if the selected node isn't the past selected node
    if (pastNodeId !== nodeid)
    {
        // Clean the previous selected node
        //jQuery("#" + pastNodeId + ' a:first').css('background-color', '#ffffff');
        jQuery("#" + pastNodeId).css('background-color', '#ffffff');
    }
    
    // Mark the new selected node
    clicknode(nodeid);

    // Set the pastNodeId as the current selected nodeid    
    pastNodeId = nodeid;
    
    // Cloe the dialog
    jQuery( '#cattree_container' ).dialog( "close" );
}

// DM
// Highlight the selected node
function clicknode(nodeid) {
    //jQuery("#" + nodeid + ' a:first').css('background-color', '#a3b9ff');
    jQuery("#" + nodeid).css('background-color', 'rgb(240, 232, 232)');
}

// yuri - load sort values for a browse node
function load_browsenode_sortvalues(nodename, sortval) {
    jQuery("#sort").load(
            ajaxurl + '?action=prossociate_sort_values&searchindex=' + nodename,
            function() {
                jQuery("#sort").on(
                        'change',
                        function(event) {
                            var sortby = jQuery(this).val();
                            jQuery("#sort").children('option').each(function() {
                                if (this.value == sortby) {
                                    jQuery("#sortby").val(sortby);
                                    this.selected = true;
                                } else {
                                    this.selected = false;
                                }
                            });
                        }
                );

                if (sortval != null && sortval != '') {
                    jQuery("#sortby").val(sortval);
                    jQuery("#sort").val(sortval);
                }
            }
    );
}

// yuri - select product
function toggle_amazon_product(checkbox) {

    var ASINs = [];
    var ASINs_string = document.getElementById('ASINs').value;
    if (ASINs_string !== '') {
        ASINs = ASINs_string.split(',');
    }
    var ASIN = checkbox.id.replace('selected_', '');
    if (checkbox.checked == true) {
        ASINs.push(ASIN);
    } else {
        var index = ASINs.indexOf(ASIN);
        ASINs.splice(index, 1);
    }
    ASINs_string = ASINs.join(',');
    document.getElementById('ASINs').value = ASINs_string;

    if (checkbox.checked == true) {
        selected_cnt++;
    } else {
        if (selected_cnt > 0) {
            selected_cnt--;
        }
    }
    
    // Get total products
    var dmTotalProducts = jQuery('#dmTotalProdCount').val();

    // number of checked products
    var selectedProducts = jQuery('#ASINs').val().split(',').length;
    var selectedProductsVal = jQuery('#ASINs').val();

    var label = document.getElementById('selected_products');

    // Check if there's a selected product
    if (selectedProductsVal !== '')
    {
        if (selectedProducts === 0) {
            label.innerHTML = "All <span class='dm-available-results'>" + dmTotalProducts + "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='../wp-content/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
        } else if (selectedProducts === 1) {
            label.innerHTML = "<span class='dm-available-results'>1</span> selected result will be posted." + '<span class="dm-select-all"><a class="dm-select-all-link" href="#">Select all results.</a></span>';
        } else {
            label.innerHTML = "<span class='dm-available-results'>" + selectedProducts + '</span> selected results will be posted.' + '<span class="dm-select-all"><a class="dm-select-all-link" href="#">Select all results.</a></span>';
        }
    }
    else
    {
        label.innerHTML = "All <span class='dm-available-results'>" + dmTotalProducts + "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='../wp-content/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
    }
    
    dm_select_all_products();
    dm_tooltip();
}

function dm_select_all_products() {
    jQuery('.dm-select-all-link').click(function(){
        // Get total products
       var dmTotalProducts = jQuery('#dmTotalProdCount').val();
       jQuery('#ASINs').removeAttr('value');
       jQuery('#pros_serps').find('input[type=checkbox]:checked').removeAttr('checked');
       jQuery('#selected_products').html("All <span class='dm-available-results'>" + dmTotalProducts + "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='../wp-content/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>");
       dm_tooltip();
    });
}

// DM - when checkbox was selected
function selected_label_change(checkBox) {



}

// yuri - check selected products
function check_selected_products() {
    var ASINs_string = document.getElementById('ASINs').value;
    var ASINs = ASINs_string.split(',');
    for (var i = 0; i < ASINs.length; i++) {
        var ASIN = ASINs[i];
        var checkbox = document.getElementById('selected_' + ASIN);
        if (checkbox != null && checkbox != 'undefined') {
            checkbox.checked = true;
        }
    }
}

// DM - initial selected_product label
function get_init_select_product_label() {
    var selected_label;
    var initASINsVal = jQuery("#ASINs").val();//.split(',').length;

    // Check if there are initial selected products
    if (initASINsVal !== '')
    {
        var initSelected = initASINsVal.split(',').length;
        if (initSelected === 1)
        {
            selected_label = "<span class='dm-available-results'>" + initSelected + '</span> selected result will be posted.' + "  <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='../wp-content/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
        }
        else
        {
            selected_label = "<span class='dm-available-results'>" + initSelected + '</span> selected results will be posted.' + "  <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='../wp-content/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
        }

        jQuery("#selected_products").html(selected_label);
    }
}

// yuri - check availability
function check_availability_options(checkbox) {
    if (checkbox.id == 'availability_chk') {
        document.getElementById('availability').value = checkbox.value;
        var merchantid = document.getElementById('merchantid_chk');
        if (checkbox.checked == true) {
            merchantid.checked = true;
            merchantid.disabled = true;
            document.getElementById('merchantid').value = merchantid.value;

            var condition = document.getElementById('condition');
            if (condition.value == 'New') {
                condition.value = 'All';
            }
        } else {
            document.getElementById('availability').value = '';
            merchantid.disabled = false;
        }
    } else if (checkbox.id == 'merchantid_chk') {
        if (checkbox.checked == true) {
            document.getElementById('merchantid').value = checkbox.value;
        } else {
            document.getElementById('merchantid').value = '';
        }
    }
}

// yuri - validate search options
function validate_search_options() {
    return true;
}

// yuri - custom integer checker
function is_int(value, check_positive) {
    if ((parseFloat(value) == parseInt(value)) && !isNaN(value)) {
        if (check_positive == true && parseInt(value) < 0) {
            return false;
        } else {
            return true;
        }
    } else {
        return false;
    }
}

function dm_submit_bypass() {
    jQuery('.dm-save-campaign-button').click(function() {
        if(validate_search_options()) {
            jQuery('#pros_submit_type').val('Save Campaign & Post Products');
            jQuery('#campaign_form').submit();
        }
    });
}

function dm_tooltip() {
   jQuery(".tips, .help_tip").tipTip({attribute:"data-tip",fadeIn:50,fadeOut:50,delay:200})
};

function dm_alert_no_cats() {
    alert( 'There are no children categories on the selected node' );
}