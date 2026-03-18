<?php

namespace PiteaCustomisation\Customisations;

class Dashboard
{
    private const PDF_ASSETS_DIR = 'assets/pdf';

    public function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'addPanel']);
        add_action('admin_head-index.php', [$this, 'injectPanelStyles']);
    }

    public function addPanel(): void
    {
        wp_add_dashboard_widget(
            'pitea_custom_panel',
            __('Materials for editors', 'pitea-customisation'),
            [$this, 'renderPanel']
        );
    }

    public function injectPanelStyles(): void
    {
        if (!function_exists('get_current_screen') || get_current_screen()?->id !== 'dashboard') {
            return;
        }
?>
        <style>
            #pitea_custom_panel .pitea-editor-materials {
                display: grid;
                gap: 12px;
                margin: 0;
            }

            #pitea_custom_panel .pitea-editor-material {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                background: #f6f7f7;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                text-decoration: none;
                color: #1d2327;
                transition: background 0.15s ease, border-color 0.15s ease;
            }

            #pitea_custom_panel .pitea-editor-material:hover {
                background: #f0f0f1;
                border-color: #c3c4c7;
                color: #1d2327;
            }

            #pitea_custom_panel .pitea-editor-material-icon {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                background: #d63638;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
            }

            #pitea_custom_panel .pitea-editor-material-icon::before {
                content: "\f497";
                font: 20px dashicons;
            }

            #pitea_custom_panel .pitea-editor-material-title {
                font-weight: 500;
                flex: 1;
            }

            #pitea_custom_panel .pitea-editor-material-arrow::after {
                content: "\f345";
                font: 18px dashicons;
                color: #787c82;
            }
        </style>
<?php
    }

    public function renderPanel(): void
    {
        $basePath = PITEA_CUSTOMISATION_PATH . self::PDF_ASSETS_DIR;
        $baseUrl  = PITEA_CUSTOMISATION_URL . self::PDF_ASSETS_DIR . '/';

        if (!is_dir($basePath)) {
            echo '<p>' . esc_html__('Materials folder not found.', 'pitea-customisation') . '</p>';
            return;
        }

        $files = array_filter(
            scandir($basePath) ?: [],
            static function ($name) use ($basePath) {
                return pathinfo($name, PATHINFO_EXTENSION) === 'pdf' && is_file($basePath . '/' . $name);
            }
        );

        if (empty($files)) {
            echo '<p>' . esc_html__('No PDF materials available yet.', 'pitea-customisation') . '</p>';
            return;
        }

        // Human-readable labels (keyed by filename)
        $labels = [
            'mediabiblioteket-och-bilder.pdf' => __('Media library & images guide', 'pitea-customisation'),
            'redaktorens-checklista.pdf' => __('Before you publish: Editor checklist', 'pitea-customisation'),
            'modulnamn-kontra-utseende.pdf' => __('Module names vs appearance', 'pitea-customisation'),
        ];

        echo '<p class="description" style="margin-bottom: 14px;">';
        echo esc_html__('Guides and checklists to help you add and manage content.', 'pitea-customisation');
        echo '</p>';
        echo '<ul class="pitea-editor-materials">';

        foreach ($files as $file) {
            $url   = $baseUrl . rawurlencode($file);
            $title = $labels[$file] ?? $this->filenameToTitle($file);
            echo '<li>';
            echo '<a class="pitea-editor-material" href="' . esc_url($url) . '" target="_blank" rel="noopener">';
            echo '<span class="pitea-editor-material-icon" aria-hidden="true"></span>';
            echo '<span class="pitea-editor-material-title">' . esc_html($title) . '</span>';
            echo '<span class="pitea-editor-material-arrow" aria-hidden="true"></span>';
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }

    private function filenameToTitle(string $filename): string
    {
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['_', '-'], ' ', $title);
        return trim($title);
    }
}
