((Drupal, once, $) => {
  'use strict';

  Drupal.behaviors.wuziMobileFilter = {
    attach(context, settings) {
      // Инициализация один раз
      once('wuzi-mobile-filter-init', 'body', context).forEach(() => {

        const $wrap = $('.mobile-bottom-filter-wrap');
        const $filter = $('.mobile-bottom-filter');

        // Открытие
        once('mobile-filter-open', '.js-mobile-filter-button', context).forEach(button => {
          button.addEventListener('click', (e) => {
            e.preventDefault();
            $wrap.addClass('is-open');
          });
        });

        // Закрытие по крестику
        once('mobile-filter-close-icon', '.js-mobile-bottom-filter-icon', context).forEach(icon => {
          icon.addEventListener('click', () => {
            $wrap.removeClass('is-open');
          });
        });

        // Закрытие по клику на подложку
        once('mobile-filter-close-wrap', '.js-mobile-bottom-filter-wrap', context).forEach(wrap => {
          wrap.addEventListener('click', (e) => {
            if (e.target === wrap) {
              $wrap.removeClass('is-open');
            }
          });
        });

        // Опционально: скрывать при нажатии Esc
        once('mobile-filter-esc', 'body', context).forEach(() => {
          document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && $wrap.hasClass('is-open')) {
              $wrap.removeClass('is-open');
            }
          });
        });
      });
    }
  };

})(Drupal, once, jQuery);