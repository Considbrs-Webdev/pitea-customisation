<?php

namespace PiteaCustomisation\Customisations;

class Matomo
{
    private const CONTAINER_STAGING    = 'container_RoYkVhrI.js';
    private const CONTAINER_PRODUCTION = 'container_6sfc3f00.js';
    private const MATOMO_BASE_URL      = 'https://beta.pitea.se/js/';

    public function __construct()
    {
        add_action('wp_head', [$this, 'outputTagManager'], PHP_INT_MIN);
    }

    public function outputTagManager(): void
    {
        $container = wp_get_environment_type() === 'production'
            ? self::CONTAINER_PRODUCTION
            : self::CONTAINER_STAGING;

        $src = esc_url(self::MATOMO_BASE_URL . $container);
        ?>
<!-- Matomo Tag Manager -->
<script>
  var _mtm = window._mtm = window._mtm || [];
  _mtm.push({'mtm.startTime': (new Date().getTime()), 'event': 'mtm.Start'});
  (function() {
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src='<?php echo $src; ?>'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Tag Manager -->
        <?php
    }
}
