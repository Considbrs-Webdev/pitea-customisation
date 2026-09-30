<?php

declare(strict_types=1);

namespace PiteaCustomisation\Module;

use Modularity\Module;
use PiteaCustomisation\Admin\Tabs\QuickExitTab;

/**
 * Modularity module: a button that sends the visitor to a neutral site immediately.
 */
class QuickExit extends Module
{
    public $slug = 'quick-exit';

    public $icon = 'background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEwIDE3bDUtNS01LTV2MTB6TTQgNGgxNnYyaC0xNnptMCAxMmg4di0ySDR6IiBmaWxsPSIjZThlYWVkIi8+PC9zdmc+);';

    public $supports = [];

    public $isBlockCompatible = true;

    public function init(): void
    {
        $this->nameSingular = __('Quick exit', 'pitea-customisation');
        $this->namePlural   = __('Quick exit', 'pitea-customisation');
        $this->description  = __('A red button that lets visitors leave the page immediately. Sticky or inline, with a configurable destination.', 'pitea-customisation');
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $fields  = $this->getFields();
        $display = (string) ($fields['quick_exit_display'] ?? 'sticky');
        if (!in_array($display, ['sticky', 'inline', 'both'], true)) {
            $display = 'sticky';
        }

        $label = trim((string) ($fields['quick_exit_label'] ?? ''));
        if ($label === '') {
            $label = QuickExitTab::getDefaultLabel();
        }

        $infoText = trim((string) ($fields['quick_exit_info_text'] ?? ''));
        if ($infoText === '') {
            $infoText = __('Read about Leave the page quickly', 'pitea-customisation');
        }

        $isPreview = is_admin();

        return [
            'url'            => $this->resolveUrl((string) ($fields['quick_exit_url'] ?? '')),
            'label'          => $label,
            'display'        => $isPreview ? 'inline' : $display,
            'isPreview'      => $isPreview,
            'escapeEnabled'  => array_key_exists('quick_exit_escape', $fields)
                ? !empty($fields['quick_exit_escape'])
                : true,
            'infoUrl'        => QuickExitTab::sanitizeUrl((string) ($fields['quick_exit_info_url'] ?? '')),
            'infoText'       => $infoText,
        ];
    }

    public function template(): string
    {
        return 'quick-exit.blade.php';
    }

    /**
     * Module URL, or the site-wide default when the module field is empty or invalid.
     */
    private function resolveUrl(string $candidate): string
    {
        $url = QuickExitTab::sanitizeUrl($candidate);

        return $url !== '' ? $url : QuickExitTab::getDefaultUrl();
    }
}
