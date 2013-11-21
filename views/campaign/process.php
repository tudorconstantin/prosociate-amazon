<div class="wrap">
    <div class="dm-process-wrap">
        <div class='pros_loader'>
            <img src='<?php echo PROSSOCIATE_ROOT_URL; ?>/images/ajax-loader.gif' style='vertical-align: middle;'>
            <h2 style='display: inline;'>Processing...</h2>
        </div>

        <div style='font-style: italic;'>
            <?php
            // Yuri - filter selected products
            $ASINs_string = $this->campaign->options["ASINs"];

            $ASINs = explode(',', $ASINs_string);

            if ($ASINs_string != '' && count($ASINs) > 0) {  //if $ASINs_string == '' then count($ASINs) returns 1
                $filtered_count = count($ASINs);
            } else {
                $filtered_count = $this->campaign->search->totalresults;
            }

            // Handles the limit of 'All' search index
            if($this->campaign->options['searchindex'] == 'All')
                $maxPossibleProducts = 50;
            else
                $maxPossibleProducts = 100;

            if ($filtered_count > $maxPossibleProducts) {
                //echo "Processing 100 products. Do not close your browser.";
                update_option('dm_temp_number', $maxPossibleProducts);
                $filtered_count = $maxPossibleProducts;
            } else {
                //echo "Processing " . $filtered_count . " products. Do not close your browser.";
                update_option('dm_temp_number', (int) $filtered_count);
            }
            ?>
        </div>
        <div id="dm-process">
            <div id="dm-progress">
                <div id="dm-progressbar-label"></div>
                <div id="progressbar"><div class="progress-label"><span id="dm-progressLabel">Initializing the process</span></div></div>
                <div id="dm-progress-meter">
                    <?php echo "Processing  <span id='dm-progress-count'>1</span>/" . $filtered_count . " products. Do not close your browser."; ?>
                </div>
            </div>
            <a id="dm-show-logs" href="#">+ Show Logs</a>
        </div>
        <div id='pros_logspot' style="display: none;">

        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                    var progressbar = $( "#progressbar" ),
                      progressLabel = $( ".progress-label" );

                    progressbar.progressbar({
                      value: false,
                      //change: function() {
                        //progressLabel.text( progressbar.progressbar( "value" ) + "%" );
                      //}
                      complete: function() {
                        progressLabel.text( "100%" );
                      }
                    });
                trigger_process(<?php echo $filtered_count; ?>, <?php echo $this->campaign->id; ?> );

            });
        </script>
    </div>
</div>