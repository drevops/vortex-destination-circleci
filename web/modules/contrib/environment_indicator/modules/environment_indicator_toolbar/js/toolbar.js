(function ($, Drupal) {
  Drupal.behaviors.environmentIndicatorToolbar = {
    attach(context, settings) {
      if (settings.environmentIndicator !== undefined) {
        const $body = $('body');
        const borderWidth =
          getComputedStyle(document.body).getPropertyValue(
            '--environment-indicator-border-width',
          ) || '6px';
        // Set environment color if not using gin_toolbar.
        if (
          settings.environmentIndicator.toolbars.toolbar === 'toolbar' &&
          !$body.hasClass('gin--vertical-toolbar') &&
          !$body.hasClass('gin--horizontal-toolbar')
        ) {
          // Replaced $.css with direct style assignment using JavaScript.
          document.querySelector('#toolbar-bar').style.backgroundColor =
            settings.environmentIndicator.bgColor;
          document
            .querySelectorAll('#toolbar-bar .toolbar-tab a.toolbar-item')
            .forEach((el) => {
              el.style.borderBottom = '0px';
              el.style.color = settings.environmentIndicator.fgColor;
            });
          document
            .querySelectorAll(
              '.toolbar .toolbar-bar .toolbar-tab > .toolbar-item',
            )
            .forEach((el) => {
              el.style.backgroundColor = settings.environmentIndicator.bgColor;
            });
        }
        // Set environment color for gin_toolbar vertical toolbar.
        if ($body.hasClass('gin--vertical-toolbar')) {
          document.querySelector(
            '.toolbar-menu-administration',
          ).style.borderLeftColor = settings.environmentIndicator.bgColor;
          document.querySelector(
            '.toolbar-menu-administration',
          ).style.borderLeftWidth = borderWidth;
          document
            .querySelectorAll(
              '.toolbar-tray-horizontal .toolbar-menu li.menu-item',
            )
            .forEach((el) => {
              el.style.marginLeft =
                'calc(var(--environment-indicator-border-width) * -0.5)';
              return el;
            });
        }
        // Set environment color for gin_toolbar horizontal toolbar.
        if ($body.hasClass('gin--horizontal-toolbar')) {
          document.querySelector(
            '#toolbar-item-administration-tray',
          ).style.borderTopColor = settings.environmentIndicator.bgColor;
          document.querySelector(
            '#toolbar-item-administration-tray',
          ).style.borderTopWidth = borderWidth;
        }
        // Set environment color on the icon of the gin_toolbar
        if (
          $body.hasClass('gin--horizontal-toolbar') ||
          $body.hasClass('gin--vertical-toolbar')
        ) {
          $('head', context).append(
            `<style>.toolbar .toolbar-bar #toolbar-item-administration-tray a.toolbar-icon-admin-toolbar-tools-help.toolbar-icon-default::before{ background-color: ${settings.environmentIndicator.bgColor} }</style>`,
          );
        }
      }
    },
  };
})(jQuery, Drupal);
