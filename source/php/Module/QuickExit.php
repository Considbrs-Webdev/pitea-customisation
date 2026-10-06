<?php

declare(strict_types=1);

namespace PiteaCustomisation\Module;

use Modularity\Module;
use PiteaCustomisation\Admin\Tabs\QuickExitTab;

/**
 * Modularity module: a panel with a button that sends the visitor to a neutral site immediately.
 *
 * Destination, texts and the keyboard shortcut are site-wide settings; the module chooses placement
 * and whether the read-more link is shown.
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
        $this->description  = __('A panel with a button that lets visitors leave the page immediately. Destination and texts are set under Settings → Piteå kommun → Quick exit.', 'pitea-customisation');
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

        $isPreview   = is_admin();
        $url         = QuickExitTab::getDefaultUrl();
        $readMoreUrl = QuickExitTab::getReadMoreUrl();
        $wantsLink   = array_key_exists('quick_exit_show_read_more', $fields)
            ? !empty($fields['quick_exit_show_read_more'])
            : true;
        $shortcut    = QuickExitTab::isShortcutEnabled();

        $text = QuickExitTab::getText($url);
        if ($shortcut) {
            $text .= ' ' . __('You can also press the Shift key three times.', 'pitea-customisation');
        }

        return [
            'url'            => $url,
            'label'          => QuickExitTab::getLabel(),
            'heading'        => QuickExitTab::getHeading(),
            'text'           => $text,
            'readMoreUrl'    => $wantsLink ? $readMoreUrl : '',
            'readMoreText'   => QuickExitTab::getReadMoreText(),
            'display'        => $isPreview ? 'inline' : $display,
            'isPreview'      => $isPreview,
            'settingsUrl'    => $isPreview ? QuickExitTab::settingsUrl() : '',
            'editorHelp'     => __('Button text, destination and the explanation apply to the whole site. This module only chooses where the button is shown.', 'pitea-customisation'),
            'editorLinkText' => __('Edit button text and destination', 'pitea-customisation'),
            'shortcut'     => $shortcut,
            'messages'     => [
                'pressTwo' => __('Shift, press 2 more times to leave the page.', 'pitea-customisation'),
                'pressOne' => __('Shift, press 1 more time to leave the page.', 'pitea-customisation'),
                'timedOut' => __('The leave-page shortcut has expired.', 'pitea-customisation'),
                'leaving'  => __('Leaving the page.', 'pitea-customisation'),
            ],
            'panelId'      => uniqid('quick-exit-'),
        ];
    }

    public function template(): string
    {
        return 'quick-exit.blade.php';
    }
}
