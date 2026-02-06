<?php

namespace PiteaCustomisation\Customisations;

class Headers
{
    /**
     * Initialize configuration and setup
     */
    public function __construct()
    {
        add_filter('Website/HTML/output', [$this, 'maybeModifyCspHeader'], 20, 0);
    }

    public function maybeModifyCspHeader()
    {
        $headers = headers_list();
        $cspHeaderNames = ['Content-Security-Policy', 'Content-Security-Policy-Report-Only'];
        $found = [];
        foreach ($headers as $hdr) {
            foreach ($cspHeaderNames as $name) {
                if (stripos($hdr, $name . ':') === 0) {
                    $found[$name] = trim(substr($hdr, strlen($name) + 1));
                }
            }
        }

        foreach ($found as $name => $cspValue) {
            $directives = preg_split('/\s*;\s*/', $cspValue);
            $hasWorkerSrc = false;
            $added = false;
            foreach ($directives as &$directive) {
                $trim = trim($directive);
                if (stripos($trim, 'script-src') === 0) {
                    if (stripos($trim, 'blob:') === false) {
                        $directive .= ' blob:';
                        $added = true;
                    }
                }
                if (stripos($trim, 'worker-src') === 0) {
                    $hasWorkerSrc = true;
                    if (stripos($trim, 'blob:') === false) {
                        $directive .= ' blob:';
                        $added = true;
                    }
                }
            }
            unset($directive);

            if (! $hasWorkerSrc) {
                $directives[] = 'worker-src blob:';
                $added = true;
            }

            if (! $added) {
                continue;
            }

            $newCsp = implode('; ', array_filter($directives, 'strlen'));
            header($name . ': ' . $newCsp, true);
        }
    }
}
