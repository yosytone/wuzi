/**
 * @file
 * wuzi behaviors.
 */
(function (Drupal) {

  'use strict';

  Drupal.behaviors.wuzi = {
    attach (context, settings) {
      //alert('It works!');
      console.log('It works!');

    }
  };

} (Drupal));

// bottom mobile filter
((Drupal, once, $) => {
  'use strict';

  Drupal.behaviors.wuzi = {
    attach(context, settings) {
      once('wuzi', 'body', context).forEach(el => {

        function closeMobileFilter() {

            $(".mobile-bottom-filter-wrap").animate({
                'background-color': 'rgba(0, 0, 0, 1)'
            }, 300);
            $(".mobile-bottom-filter").animate({
                'top': '100%'
            }, 300, function() {
                $(".mobile-bottom-filter-wrap").hide();
            });
        }
        function updateFloatMobileFilter() {
            if ($(".product-wrap").length == 0) {
                return false;
            }

            var topFilterWrapPos = $(".product-wrap").offset().top;

            currentScrollPos = $(this).scrollTop();

            if (currentScrollPos > topFilterWrapPos) {

                $(".js-mobile-bottom-sort_filter-wrap").addClass('mobile-filter-button-float');

            } else {
                $(".js-mobile-bottom-sort_filter-wrap").removeClass('mobile-filter-button-float');
            }

        }
        
        $('.js-mobile-filter-button').click(function() {
            $(".mobile-bottom-filter-wrap").show();
            $(".mobile-bottom-filter-wrap").animate({
                'background-color': 'rgba(0, 0, 0, 1)'
            }, 300);
            $(".mobile-bottom-filter").animate({
                'top': '20%'
            }, 300);

            const $wrap = $(".mobile-bottom-filter-wrap");
            const $filter = $(".mobile-bottom-filter");
            $wrap.show().addClass('is-open');
        });

        $('.js-mobile-bottom-filter-icon').click(closeMobileFilter);

        $('.js-mobile-bottom-filter-wrap').click(function(e) {
            if ($(e.target).hasClass('mobile-bottom-filter-wrap')) {
                closeMobileFilter();
            }
        });

      });
    }
  };

})(Drupal, once, jQuery);