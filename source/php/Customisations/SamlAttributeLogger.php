<?php

namespace PiteaCustomisation\Customisations;

class SamlAttributeLogger
{
    private const LOG_DIRECTORY = 'pitea-saml-logs';
    private const LOG_FILENAME  = 'saml-attributes.log';

    public function __construct()
    {
        add_action('mo_saml_user_attributes', [$this, 'logAttributes'], 10, 1);
    }

    public function logAttributes($attrs): void
    {
        $logFile = $this->getLogFilePath();
        if (!$logFile || !is_writable(dirname($logFile))) {
            return;
        }

        $entry = [
            'timestamp'  => current_time('mysql'),
            'attributes' => $attrs,
        ];

        $encoded = wp_json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$encoded) {
            $encoded = print_r($entry, true);
        }

        @file_put_contents($logFile, $encoded . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function getLogFilePath(): ?string
    {
        $uploadDir = wp_upload_dir(null, false);
        if (!empty($uploadDir['error']) || empty($uploadDir['basedir'])) {
            return null;
        }

        $logDir = trailingslashit($uploadDir['basedir']) . self::LOG_DIRECTORY;
        if (!$this->ensureDirectory($logDir)) {
            return null;
        }

        $logFile = apply_filters(
            'pitea_customisation_saml_attributes_log_file',
            trailingslashit($logDir) . self::LOG_FILENAME
        );

        return is_string($logFile) && $logFile !== '' ? $logFile : null;
    }

    private function ensureDirectory(string $logDir): bool
    {
        if (!is_dir($logDir) && !wp_mkdir_p($logDir)) {
            return false;
        }

        if (!is_writable($logDir)) {
            return false;
        }

        $this->maybeWriteProtectionFiles($logDir);

        return true;
    }

    private function maybeWriteProtectionFiles(string $logDir): void
    {
        $htaccess = trailingslashit($logDir) . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }

        $index = trailingslashit($logDir) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }
}
