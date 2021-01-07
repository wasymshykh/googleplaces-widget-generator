window.onload = function() {
    if (window.jQuery) { main(); }
    else {
        // jQuery is not loaded
        console.log("jQuery has not loaded!");
        var script_tag = document.createElement('script');
        script_tag.setAttribute("type","text/javascript");
        script_tag.setAttribute("src","https://code.jquery.com/jquery-3.5.1.min.js");
        script_tag.setAttribute("integrity","sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=");
        script_tag.setAttribute("crossorigin","anonymous");
        script_tag.onload = main; // Run main() once jQuery has loaded
        script_tag.onreadystatechange = function ()
        { // Same thing but for IE
            if (this.readyState == 'complete' || this.readyState == 'loaded')
            {
                main();
            }
        }
        document.getElementsByTagName("head")[0].appendChild(script_tag);
    }
}

function main() {
    jQuery.noConflict();
    jQuery(document).ready(function($) { 
    // .... use jQuery here
        $('.gmb-rating').each((i, e) => {
            let w_u = $(e).attr('data-uuid');
            let w_t = $(e).attr('data-template');
            $.ajax({type: 'GET', url: 'http://localhost/widget/api/get_widget.php', data: {'uuid': w_u, 'template': w_t}, success: function (result) { $(e).html(result.message); }, error: function (result) { $(e).text('WIDGET_ERROR: '+result.responseJSON.message); }}); 
        })
    });
    // We can still use Prototype here
}
