<?php
/**
 * The display for manage campaign page (wp-admin/admin.php?page=prossociate_manage_campaigns)
 * 
 * ProssociateCampaignController::manage_campaigns()
 * 
 * $currentPage - (int) Current page number
 * $numberOfCampaigns - (int) Number of all the campaigns
 * $numberOfPages - (int) Number of pages
 * $campaigns - (object) Contains the campaigns to be displayed
 * 
 */
// define the columns to display, the syntax is 'internal name' => 'display name'
$columns = array(
            'id' => 'ID',
            'name' => 'Name',
            'keywords' => 'Keywords',
            'last_run_time' => 'Last Run Date',
            );

// The url of the maanage campaign page. (current url)
$manageUrl = "admin.php?page=prossociate_manage_campaigns";

// Conver the $currentPage to int to perform math operations
$currentPage = (int)$currentPage;

// The first page link
$firstPagiLink = admin_url( $manageUrl );

// The last page link
$lastPagiLink = admin_url( $manageUrl . '&pagi=' . $numberOfPages );

// Get the prev page number
$prevPageNumber = $currentPage - 1; 

// The url for the prev page
$prevPagiLink = admin_url( $manageUrl . '&pagi=' . $prevPageNumber );

// Check if we need to disable the prev page and first page link.
// Make the $prevPagi link as the same as the $firstPagiLink
$prevPagiClass = '';
if( $currentPage === 1)
{
    $prevPagiClass = ' disabled';
    $prevPagiLink = $firstPagiLink;
}

// Get the next page number
$nextPageNumber = $currentPage + 1; 

// The url for the prev page
$nextPagiLink = admin_url( $manageUrl . '&pagi=' . $nextPageNumber );

// Check if we need to disable the next page and last page link
// Make the $nextPagiLink link as the same as the $lastPagiLink
$nextPagiClass = '';
if( $currentPage == $numberOfPages )
{
    $nextPagiClass = ' disabled';
    $nextPagiLink = $lastPagiLink;
}


?>

<div class="wrap">

<h2>
	Prosociate: Manage Campaigns<a href="admin.php?page=prossociate_addedit_campaign" class="add-new-h2">Add New</a></h2>
</h2>

<?php // We'll be using the built-in wordpress pagination display so we won't need to add styles for the pagination ?>
<div class="tablenav top">
    <div class="tablenav-pages">
        <span class="displaying-num"><?php echo $numberOfCampaigns . ' Campaigns'; ?></span>
        <?php 
        if( $numberOfCampaigns > 10 )
        { ?>
        <span class="pagination_links">
            <a class="first-page<?php echo $prevPagiClass; ?>" title="Go to the first page" href="<?php echo $firstPagiLink; ?>">«</a>
            <a class="prev-page<?php echo $prevPagiClass; ?>" title="Go to the previous page" href="<?php echo $prevPagiLink; ?>">‹</a>
            
            <span class="paging-input">
                <input class="current-page" title="Current page" type="text" name="pagi" value="<?php echo $currentPage; ?>" size="1">
                of
                <span class="total-pages"><?php echo $numberOfPages; ?></span>
            </span>
            
            <a class="next-page<?php echo $nextPagiClass; ?>" title="Go to the next page" href="<?php echo $nextPagiLink; ?>">›</a>
            <a class="last-page<?php echo $nextPagiClass; ?>" title="Go to the last page" href="<?php echo $lastPagiLink; ?>">»</a>
        </span>
        <?php
        } // End if 
        ?>
    </div>
</div>

<?php
/*
    if( $msg ) 
    {
        // TODO not currently working
    	echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
    }
 * 
 */
?>
<form method="post" id="import-list" action="<?php echo remove_query_arg('pmxi_nt') ?>">

	<div class="clear"></div>

	<table class="widefat pmxi-admin-imports">

		<thead>
		<tr>
			<th scope="col" id="cb" class="manage-column column-cb check-column" style="">
                                                        <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                                                        <input id="cb-select-all-1" type="checkbox">
                                                      </th>
                                                    
			<?php
			$col_html = '';
			foreach($columns as $column_id => $column_display_name) 
			{
                $column_link = "<a href='";
				$order2 = 'ASC';
                
                // TODO Question: where is $order_by declared?
				if( $orderBy == $column_id )
                {
                    $order2 = ($order == 'DESC') ? 'ASC' : 'DESC';
                }
                
                // TODO Question: where is $this->baseUrl declared?
				$column_link .= esc_url( add_query_arg( array( 'order' => $order2, 'order_by' => $column_id ) ) );
				$column_link .= "'>{$column_display_name}</a>";
				$col_html .= '<th scope="col" class="column-' . $column_id . ' ' . ($orderBy == $column_id ? $order : '') . '">' . $column_link . '</th>';
			}
			echo $col_html;
			?>
		</tr>
		</thead>

		<tbody id="the-pmxi-admin-import-list" class="list:pmxi-admin-imports">
		<?php 
		// Check if there's no campaign.
		if( count( $campaigns ) < 1 ): ?>
			<tr>
				<td colspan="<?php echo count($columns) + 1 ?>">&nbsp;</td>
			</tr>
		<?php 
		else: // There are existing campaigns
		
            $class = ''; // Class container
            
            // Loop through all the campaign
            foreach ($campaigns as $campaign):
                // Check if we're on even campaign. For the display variation 
                if( $class == 'alternate' )
                {
                    $class = '';
                }
                else 
                {
                    $class = 'alternate';    
                }
                // TODO delete line below
                //$class = ('alternate' == $class) ? '' : 'alternate';
                ?>
                
				<tr class="<?php echo $class; ?>" valign="middle">
					<th scope="row" class="check-column">
					<label class="screen-reader-text" for="cb-select-4">Select <?php echo $campaign->name; ?></label>
                                                                                                <input id="cb-select-4" type="checkbox" name="campaign[]" value="<?php echo $campaign->id; ?>">
					</th>
					<?php foreach( $columns as $column_id => $column_display_name ): ?>
						<?php
						switch( $column_id ):
							case 'id':
								?>
								<th valign="top" scope="row">
									<?php echo $campaign->id; ?>
								</th>
								<?php
								break;
							case 'name':
								?>
								<td class='post-title page-title column-title'>
									<strong>
										<a class="row-title" href="admin.php?page=prossociate_addedit_campaign&campaign_id=<?php echo $campaign->id; ?>" title="Edit this campaign"><?php echo $campaign->name; ?></a>
									</strong>

									<div class="row-actions">
										<span class='edit'><a href="admin.php?page=prossociate_addedit_campaign&campaign_id=<?php echo $campaign->id; ?>" title="Edit this campaign">Edit</a> | <span class='trash'><a class='submitdelete' title='Delete Campaign' href='admin.php?page=prossociate_manage_campaigns&action=delete&campaign_id=<?php echo $campaign->id; ?>'>Delete</a>
									</div>
								</td>
								<?php
								break;
							case 'keywords':
								?>
								<td>
									<?php
										$options = unserialize($campaign->options);
										echo stripslashes($options['keywords']);
									?>
								</td>
								<?php
								break;
							case 'last_run_time':
								?>
								<td>
									<?php
										if ($campaign->last_run_time) {
											echo date("M d Y H:i:s", $campaign->last_run_time);
										} else {
											echo "<i>never</i>";
										}
									?>
								</td>
								<?php
								break;
							default:
								?>
								<td>
									<?php echo "error - ".$campaign->$column_id ?>
								</td>
								<?php
								break;
						endswitch;
						?>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		<?php endif ?>
		</tbody>
	</table>

	<div class="clear"></div>
        <!-- Massive delete button -->
        <div style="margin-top: 8px;" class="dm-pros-massive-delete">
            <input class="button action" type="submit" value="Delete Selected Campaigns" name="dm-pros-mass-del"/></a>
        </div>
        <!-- End massive delete button -->
</form>



</div>