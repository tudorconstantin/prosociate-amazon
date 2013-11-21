<?php
$helpIconUrl = WP_CONTENT_URL;
$noresultsavailable = false;

// Try to use the results pure instead
// Logic for what to display in termss of product count
/*
if( ($search->totalresults < 100) && ($search->totalresults >= 1) )
{
    $dmAvailable = "All <span class='dm-available-results'>" . $search->totalresults . "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='". $helpIconUrl ."/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
    $dmTotalProdCount = $search->totalresults;
}
elseif( $search->totalresults >= 100 )
{
    $dmAvailable = "All <span class='dm-available-results'>100</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='". $helpIconUrl ."/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
    $dmTotalProdCount = 100;
}
else
{
    $dmAvailable = "<span style='color: #A00; font-weight: bold;'>Amazon didn't return any results for these search terms.</span>";
    $noresultsavailable = true;
    $dmTotalProdCount = 0;
}
*/

// Handles limit for 'All' search index
if($search->searchindex == 'All') {
    $maxPossibleProducts = 50;
    $maxPossiblePage = 5;
} else {
    $maxPossibleProducts = 100;
    $maxPossiblePage = 10;
}

if( ($search->totalresults < $maxPossibleProducts) && ($search->totalresults >= 1) )
{
    $dmAvailable = "All <span class='dm-available-results'>" . $search->totalresults . "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='". $helpIconUrl ."/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
    $dmTotalProdCount = $search->totalresults;
}
elseif( $search->totalresults >= $maxPossibleProducts )
{
    $dmAvailable = "All <span class='dm-available-results'>" . $maxPossibleProducts . "</span> available results will be posted. <a id='dm-tooltip' class='help_tip' data-tip='Click checkboxes to only post selected results' href='#'><img src='". $helpIconUrl ."/plugins/woocommerce/assets/images/help.png' height='16px' width='16px'/></a>";
    $dmTotalProdCount = $maxPossibleProducts;
}
else
{
    $dmAvailable = "<span style='color: #A00; font-weight: bold;'>Amazon didn't return any results for these search terms.</span>";
    $noresultsavailable = true;
    $dmTotalProdCount = 0;
}

// We will only display a maximum of 10 pages
if ($search->totalpages > $maxPossiblePage) {
    $search->totalpages = $maxPossiblePage;
}

function add_currency_symbols($price) {
	switch(AWS_COUNTRY) {
		case 'com':
			$newPrice = '$' . $price;
			break;
		case 'co.uk':
			$newPrice = '&#163;' . $price;
			break;
		case 'co.jp':
			$newPrice = '&#165;' . $price;
			break;
		case 'de':
			$newPrice = 'EUR ' . $price;
			break;
		case 'fr':
			$newPrice = 'EUR ' . $price;
			break;
		case 'ca':
			$newPrice = 'CDN$ ' . $price;
			break;
		case 'es':
			$newPrice = 'EUR ' . $price;
			break;
		case 'it':
			$newPrice = 'EUR ' . $price;
			break;
		case 'cn':
			$newPrice = '&#165;' . $price;
			break;
        case 'in':
            $newPrice = '&#8377;' . $price;
            break;
		default:
			$newPrice = '$' . $price;
			break;
	}
	return $newPrice;
}

function remove_currency_symbols($x) {
	$x = preg_replace('/[^0-9-.,]/', '', $x);
	
	$x = str_replace(" ", "", $x);
	return $x;
}

function reformat_prices($price) {
	switch( AWS_COUNTRY ) {
		case 'de':
			$formatPrice = reformat_price_de($price);
			break;
		case 'fr':
			$formatPrice = reformat_price_de($price);
			break;
		case 'es':
			$formatPrice = reformat_price_de($price);
			break;
		case 'it':
			$formatPrice = reformat_price_de($price);
			break;
		default:
			$formatPrice = str_replace(',', '', $price);
			break;
	}
	return $formatPrice;
}

function reformat_price_de($price) {
	$priceArray = str_split($price);
	
	foreach ($priceArray as $k => $v) {
		if ($v == '.') {
			$priceArray[$k] = '';
		} elseif ($v == ',') {
			$priceArray[$k] = '.';
		}
	}
	$formatPrice = implode('', $priceArray);
	
	return $formatPrice;
}

?>
<input type="hidden" name="dmTotalProdCount" id="dmTotalProdCount" value="<?php echo $dmTotalProdCount; ?>"/>
<div class="dm-pros-result-block">
    <label id="selected_products"><?php echo $dmAvailable; ?></label>
    <?php
    // Don't show button if Save campaign button if no results
    if( !$noresultsavailable )
    { ?>
        <div class="dm-campaign-post-button-search">
            <input class="wp-core-ui button-primary dm-save-campaign-button" type='button' name='pros_save_submit_post' value='Save Campaign & Post Products' /><br />
            <!-- Yuri -->
        </div>
    <?php } ?>
    <div style="clear: both;"></div>
</div>
<!-- For loading -->
<div class="dm-overlay-class dm-waiter" style="display: none;">
    <div class="dm-waiter-img">
        <div class="dm-waiter-text">Loading results from Amazon...</div>
    </div>
</div>
<div id="dm-overlay">
<div class="dm-pros-search-wrap">
<?php 
// Only display results if they exists
if( !is_null( $search->results_pure ) )
{
?>
<div class="dm-pros-result-pagination">
    <ul>
        <li>
            <?php
            // Check if on the first page
            if( $search->page == 1 )
            {
                echo '<span class="dm-strong">First</span>';
            }
            else
            {
                echo '<a href="#" pros:page="1" class="pros_page_link">First</a>';
            }
            ?>
        </li>
        <?php for( $pageCounter = 1; $pageCounter <= $search->totalpages; $pageCounter++ )
        {
            // Display the pages
            echo '<li class="dm-page-sep">|</li>';
            echo '<li>';
            // Do not link the current page
            if( $search->page == $pageCounter )
            {
                echo '<span class="dm-strong">' . $pageCounter . '</span>';
            }
            else
            {
                echo '<a href="#"pros:page="' . $pageCounter . '" class="pros_page_link">' . $pageCounter . '</a>';
            }
            echo '</li>';
        }
        ?>
        <li class="dm-page-sep">|</li>
        <li>
            <?php
            // Check if on the last page
            if( $search->page == $search->totalpages )
            {
                echo '<span class="dm-strong">Last</span>';
            }
            else
            {
                echo '<a href="#" pros:page="' . $search->totalpages . '" class="pros_page_link">Last</a>';
            }
            ?>
        </li>
    </ul>
</div>
<?php
    // count results
    $resultsCount = count($search->results_pure);
    $resultsCounter = 0;
    // Note kindle prices won't display
    // https://forums.aws.amazon.com/ann.jspa?annID=854
    foreach ($search->results_pure as $result) {
        if(isset($result->Offers->TotalOffers) && $result->Offers->TotalOffers <= 0) {
            if(isset($result->Variations->TotalVariations) && $result->Variations->TotalVariations <= 0)
                continue;
            elseif(!isset($result->Variations->TotalVariations))
                continue;
        }

        // Prioritize amazon price
        $useAmazonPrice = false;
        if(isset($result->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $useAmazonPrice = true;
        }

        //var_dump($result);
        $resultsCounter++;
		
		$dmPrice = '';
		
		// Checker for sale price
        $firstPrice = false;
		$secondPrice = false;
		
		if (isset($result->ItemAttributes->ListPrice->FormattedPrice)) {
			$dmPrice = $result->ItemAttributes->ListPrice->FormattedPrice;
			$firstPrice = $result->ItemAttributes->ListPrice->FormattedPrice;
		}
		
		if (isset($result->Offers->Offer->OfferListing->Price->FormattedPrice) ) {
			$dmPrice = $result->Offers->Offer->OfferListing->Price->FormattedPrice;
			$secondPrice = $result->Offers->Offer->OfferListing->Price->FormattedPrice;
		}
		
		if (isset($result->Offers->Offer->OfferListing->SalePrice->FormattedPrice)) {
			$dmPrice = $result->Offers->Offer->OfferListing->SalePrice->FormattedPrice;
		}
		
		if( isset($result->OfferSummary->LowestNewPrice->FormattedPrice) ) {
			$dmPrice = $result->OfferSummary->LowestNewPrice->FormattedPrice;
		}

        if($dmPrice == 'Too low to display') {
            if(isset($result->OfferSummary->LowestUsedPrice->FormattedPrice)) {
                $dmPrice = $result->OfferSummary->LowestUsedPrice->FormattedPrice;
            }
        }
		
		if($firstPrice && $secondPrice) {
			$secondPrice = (int)reformat_prices(remove_currency_symbols($secondPrice));
			$firstPrice = (int)reformat_prices(remove_currency_symbols($firstPrice));
			
			if ($secondPrice < $firstPrice) {
				$dmPrice = $result->Offers->Offer->OfferListing->Price->FormattedPrice;
			}
			
			// Check if we problems with the price
			if(!$dmPrice) {
				// Convert the price to proper integer
				$regPrice = (int)str_replace(',', '', $firstPrice);
				$salePrice = (int)str_replace(',', '', $secondPrice);
				
				if($salePrice == $regPrice || $salePrice > $regPrice ) {
					$dmPrice = $regPrice;
				}
				
				if($salePrice < $regPrice) {
					$dmPrice = $salePrice;
				}
			}
		}
		
		// If no prices are available on the product itself. Look for the variation price
        if(empty($dmPrice)) {
			if(isset($result->VariationSummary->LowestSalePrice->FormattedPrice)) {
				$dmPrice = $result->VariationSummary->LowestSalePrice->FormattedPrice;
			} elseif($result->VariationSummary->HighestSalePrice->FormattedPrice) {
				$dmPrice = $result->VariationSummary->HighestSalePrice->FormattedPrice;
			} elseif(isset($result->VariationSummary->LowestPrice->FormattedPrice)) {
				$dmPrice = $result->VariationSummary->LowestPrice->FormattedPrice;
			} elseif(isset($result->VariationSummary->HighestPrice->FormattedPrice)) {
				$dmPrice = $result->VariationSummary->LowestPrice->FormattedPrice;
			}
		}

        // Prioritize amazon prices
        if($useAmazonPrice)
            $dmPrice = $result->Offers->Offer->OfferListing->Price->FormattedPrice;
		
		$dmPrice = remove_currency_symbols($dmPrice);
		$dmPrice = reformat_prices($dmPrice);
		$dmPrice = add_currency_symbols($dmPrice);
        
        // Trim long titles
        if( strlen($result->ItemAttributes->Title) >= 60 )
        {
            $dmTitle = substr( $result->ItemAttributes->Title, 0, 57 ) . '...';
        }
        else
        {
            $dmTitle = $result->ItemAttributes->Title;
        }
        
        // Find an image for the product
        if(isset($result->ImageSets->ImageSet)) {
            if(is_array($result->ImageSets->ImageSet))
                $dmImage = $result->ImageSets->ImageSet[0]->SmallImage->URL;
            else
                $dmImage = $result->ImageSets->ImageSet->SmallImage->URL;
        } elseif(isset($result->SmallImage->URL)) {
            $dmImage = $result->SmallImage->URL;
        } elseif(isset($result->Variations->Item)) {
            if(is_array($result->Variations->Item))
                $dmImage = $result->Variations->Item[0]->SmallImage->URL;
            else
                $dmImage = $result->Variations->Item->SmallImage->URL;
        }
        ?>
        <div class='pros_single_product_container'>
            <div class='pros_single_product'>
                <div class="pros_single_product_image">
                    <img src="<?php echo  $dmImage; ?>"/>
                </div>
                
                <div class="pros_single_product_desc">
                    <div class="pros_single_product_title">
                        <a target="_blank" href="<?php echo $result->DetailPageURL; ?>"><?php echo $dmTitle; ?></a>
                    </div>
                    <div class="pros_single_product_below">
                        <div class="pros_single_product_below_left">
                            <div class="pros_single_product_price">
                                <?php echo $dmPrice; ?>
                            </div>
                            <div class="pros_single_product_asin">
                                ASIN: <?php echo $result->ASIN; ?>
                            </div>
                        </div>
                        <div class="pros_single_product_below_right">
                            <div class="pros_single_product_checkbox">
                                <input type='checkbox' id="selected_<?php echo $result->ASIN; ?>" name="selected[]" value="<?php echo $result->ASIN; ?>" onchange="toggle_amazon_product(this)" />
                            </div>
                        </div>
                        <div style="clear: both;"></div>
                    </div>
                </div>
            </div>
            <div style="clear: both;"></div>
        </div>
        <?php
        // if we're at the last product don't show separator
        if( $resultsCounter < $resultsCount )
        { ?>
        <div class="pros_single_product_sep">
            <div class="pros_product_sep"></div>
        </div>
        <div style="clear: both;"></div>
        <?php
        } // end result count
    } 
    ?>
        <br clear='all'>
        <div class="dm-pros-result-pagination">
            <ul>
                <li>
                <?php
                // Check if on the first page
                if( $search->page == 1 )
                {
                    echo '<span class="dm-strong">First</span>';
                }
                else
                {
                    echo '<a href="#" pros:page="1" class="pros_page_link">First</a>';
                }
                ?>
                </li>
                <?php for( $pageCounter = 1; $pageCounter <= $search->totalpages; $pageCounter++ )
                {
                    // Display the pages
                    echo '<li class="dm-page-sep">|</li>';
                    echo '<li>';
	  // Do not link the current page
	  if( $search->page == $pageCounter )
	  {
                        echo '<span class="dm-strong">' . $pageCounter . '</span>';
	  }
	  else
	  {
                        echo '<a href="#"pros:page="' . $pageCounter . '" class="pros_page_link">' . $pageCounter . '</a>';
	  }
	  echo '</li>';
                }
                ?>
                <li class="dm-page-sep">|</li>
                <li>
                <?php
                // Check if on the last page
                if( $search->page == $search->totalpages )
                {
                    echo '<span class="dm-strong">Last</span>';
                }
                else
                {
                    echo '<a href="#" pros:page="' . $search->totalpages . '" class="pros_page_link">Last</a>';
                }
                ?>
                </li>
        </ul>
    </div>
<?php
}
?>
</div>
</div>

<!-- Yuri -->
<script type="text/javascript">
    check_selected_products();
    get_init_select_product_label();
    dm_submit_bypass();
    dm_tooltip();
</script>