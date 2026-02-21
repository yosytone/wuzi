(function ($) {
  $(document).ready(function () {
    $("[data-fancybox]").fancybox({
      beforeLoad: function (instance, current) {
        if (current.opts && current.opts.$orig) {
          var $triggerElement = current.opts.$orig;
          var actorId = $triggerElement.data("actor-id");
          var filmId = $triggerElement.data("film-id");
          var offset = 0;
          var loading = false;
          var hasMore = true;

          if (actorId && filmId) {
            function loadPhotos() {
              if (loading || !hasMore) return;
              loading = true;

              $.ajax({
                url: `/load-photos/${actorId}/${filmId}/${offset}`,
                type: "GET",
                dataType: "json",
                success: function (data) {
                  if (data && data.length > 0) {
                    data.forEach(function (photo) {
                      instance.addContent({
                        src: photo.url,
                        opts: {
                          caption: photo.alt,
                          thumb: photo.url,
                        },
                      });
                    });

                    offset += data.length;
                  } else {
                    hasMore = false;
                  }
                },
                error: function () {
                  console.error("Ошибка при загрузке фотографий.");
                },
                complete: function () {
                  loading = false;
                },
              });
            }

            instance.$refs.container.on("afterShow.fb", function (e, fancybox, slide) {
              if (slide.index === instance.group.length - 1 && hasMore) {
                loadPhotos();
              }
            });

            loadPhotos();
          }
        }
      },

      afterClose: function (instance, current) {
        instance.group = [];
      },
    });
  });
})(jQuery);
