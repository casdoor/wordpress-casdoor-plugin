(function ($) {
    $(window).on('load', function() {
        $("#accordion").accordion({
            heightStyle: "content"
        });

        var hash = window.location.hash;
        var anchor = $('#' + hash);
        if (anchor.length > 0) {
            anchor.click();
        }
    });
})(jQuery);
