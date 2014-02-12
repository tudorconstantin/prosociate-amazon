function dmCampaignDelete(data) {
    jQuery.post(ajaxurl, data, function(response, status){
        var campDelObj = JSON.parse(response);
        jQuery("#dmDelCampWrap").append(campDelObj.message + '<br />');

        if(campDelObj.complete === 'false') {
            dmCampaignDelete(campDelObj);
        }
    });
}