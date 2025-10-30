(function ($, Drupal, once) {
  Drupal.behaviors.environmentIndicatorTinycon = {
    attach(context, settings) {
      $(once('env-ind-tinycon', 'html', context)).each(function () {
        if (
          settings.environmentIndicator !== undefined &&
          settings.environmentIndicator.addFavicon !== undefined &&
          settings.environmentIndicator.addFavicon
        ) {
          // Ensure Tinycon is defined before using it.
          if (typeof Tinycon !== 'undefined') {
            Tinycon.setBubble(
              settings.environmentIndicator.name.slice(0, 1).trim(),
            );
            Tinycon.setOptions({
              background: settings.environmentIndicator.bgColor,
              colour: settings.environmentIndicator.fgColor,
            });
          } else {
            console.warn('Tinycon is not available.');
          }
        }
      });
    },
  };
})(jQuery, Drupal, once);
