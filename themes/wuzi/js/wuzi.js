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

((Drupal, once) => {
  'use strict';

  Drupal.behaviors.productMoreToggle = {
    attach(context) {
      once('product-more-init', '.js-product-more-toggle', context).forEach(toggle => {
        const content = toggle.nextElementSibling;
        if (!content || !content.classList.contains('js-product-more-content')) return;

        // Устанавливаем начальное состояние
        content.style.maxHeight = '0';

        toggle.addEventListener('click', () => {
          const isOpen = content.classList.contains('is-open');

          if (isOpen) {
            // Сворачиваем
            content.style.maxHeight = '0';
            content.classList.remove('is-open');
            toggle.textContent = 'Подробнее';
          } else {
            // Раскрываем: измеряем реальную высоту
            content.style.maxHeight = 'none';
            const height = content.scrollHeight + 'px';
            content.style.maxHeight = '0'; // сброс для анимации
            // Принудительный reflow
            void content.offsetHeight;
            // Запуск анимации
            content.style.maxHeight = height;
            content.classList.add('is-open');
            toggle.textContent = 'Скрыть';
          }
        });
      });
    }
  };

})(Drupal, once);


((Drupal, once, $) => {
'use strict';

Drupal.behaviors.test = {
  attach(context, settings) {
    // Инициализация один раз
    once('wuzi-mobile-filter-init', 'body', context).forEach(() => {
      const lazyBlocks = document.querySelectorAll('.lazy-block');

     

      lazyBlocks.forEach(block => observer.observe(block));
    });
  }
};

})(Drupal, once, jQuery);

      