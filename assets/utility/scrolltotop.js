/*!
 * Scroll-to-Top functions from SB Admin 2 template (https://startbootstrap.com/)
 * Copyright 2013-2021 Start Bootstrap LLC
 * Code released under the MIT license.
 */
import {$,jQuery} from 'jquery';

(($) => {
    "use strict"; // Start of use strict

    // Scroll to top button appear
    $(document).on('scroll', function() {
        var scrollDistance = $(this).scrollTop();
        if (scrollDistance > 100) {
            $('.scroll-to-top').fadeIn();
        } else {
            $('.scroll-to-top').fadeOut();
        }
    });

    // Smooth scrolling using jQuery easing
    $(document).on('click', 'a.scroll-to-top', function(e) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: ($($anchor.attr('href')).offset().top)
        }, 1000, 'swing');
        e.preventDefault();
    });

})(jQuery); // End of use strict
