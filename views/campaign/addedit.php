<div class="wrap">
    <h2>
        <?php
            if(isset($_REQUEST['campaign_id'])) {
                echo "Prosociate: Edit Campaign";
            } else {
                echo "Prosociate: New Campaign";
            }
        ?>
    </h2>

    <!-- Yuri -->
    <form id="campaign_form" name='campaign_form' method="post" action="<?php echo $this->url_to_here; ?>">

        <!-- louis nav tabs addition -->			
        <h2 class="nav-tab-wrapper">
            <a href="#" id='tabs-search-link' class="nav-tab nav-tab-active">General</a>
            <a href="#" id='tabs-post-link' class="nav-tab ">Post Options</a>
            <a href="#" id='tabs-settings-link' class="nav-tab ">Campaign Settings</a>
        </h2>	
        <!-- louis nav tabs addition -->			


        <div id="tabs-search">
            <?php //if(AWS_COUNTRY == 'com') {
                include "tabs-search-com.php";
            //} else {
                //include "tabs-search.php";
            //}
            ?>
        </div>

        <div id="tabs-post" style='display: none;'>
            <?php include "tabs-post.php"; ?>
        </div>

        <div id="tabs-settings" style='display: none;'>
            <?php include "tabs-settings.php"; ?>
        </div>

    </form>

</div>