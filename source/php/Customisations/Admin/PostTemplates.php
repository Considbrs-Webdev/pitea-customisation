<?php

namespace PiteaCustomisation\Customisations\Admin;

class PostTemplates
{
    /**
     * Replaced at page-creation time with the media-library ID for
     * static/images/dummies/dummy-image.jpg (imported from the plugin if missing).
     */
    private const PLACEHOLDER_DUMMY_IMAGE_ID = '__PITEA_DUMMY_IMAGE_ID__';

    /**
     * Replaced with wp_get_attachment_url() for the standard dummy attachment.
     */
    private const PLACEHOLDER_DUMMY_IMAGE_URL = '__PITEA_DUMMY_IMAGE_URL__';

    /**
     * Replaced at page-creation time with the media-library ID for dummy-image-inverted.jpg.
     */
    private const PLACEHOLDER_DUMMY_INVERTED_ID = '__PITEA_DUMMY_INVERTED_ID__';

    /**
     * Replaced with wp_get_attachment_url() for the inverted dummy attachment.
     */
    private const PLACEHOLDER_DUMMY_INVERTED_URL = '__PITEA_DUMMY_INVERTED_URL__';

    private const PAGE                 = 'pitea-create-navigation-page';
    private const PAGE_THEME             = 'pitea-create-theme-page';
    private const PAGE_NAV_SECOND_LEVEL  = 'pitea-create-navigation-second-level-page';
    private const PAGE_CONTENT_PAGE      = 'pitea-create-content-page';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenuItems']);
    }

    /**
     * Register submenu items under Pages.
     * Attaches the page-creation handler to load-{hook}, which fires before
     * any output so wp_redirect() still works — the same pattern WordPress
     * itself uses for built-in "Add New" links.
     *
     * @return void
     */
    public function registerMenuItems(): void
    {
        $hook = add_submenu_page(
            'edit.php?post_type=page',
            __('New navigation page', 'pitea-customisation'),
            __('New navigation page', 'pitea-customisation'),
            'edit_pages',
            self::PAGE,
            '__return_false'
        );

        add_action('load-' . $hook, [$this, 'handleCreateNavigationPage']);

        $hookTheme = add_submenu_page(
            'edit.php?post_type=page',
            __('New theme page', 'pitea-customisation'),
            __('New theme page', 'pitea-customisation'),
            'edit_pages',
            self::PAGE_THEME,
            '__return_false'
        );

        add_action('load-' . $hookTheme, [$this, 'handleCreateThemePage']);

        $hookNavSecondLevel = add_submenu_page(
            'edit.php?post_type=page',
            __('New navigation page (second level)', 'pitea-customisation'),
            __('New navigation page (second level)', 'pitea-customisation'),
            'edit_pages',
            self::PAGE_NAV_SECOND_LEVEL,
            '__return_false'
        );

        add_action('load-' . $hookNavSecondLevel, [$this, 'handleCreateNavSecondLevelPage']);

        $hookContentPage = add_submenu_page(
            'edit.php?post_type=page',
            __('New content page', 'pitea-customisation'),
            __('New content page', 'pitea-customisation'),
            'edit_pages',
            self::PAGE_CONTENT_PAGE,
            '__return_false'
        );

        add_action('load-' . $hookContentPage, [$this, 'handleCreateContentPage']);
    }

    /**
     * Create the navigation page and redirect the user to the block editor.
     * Fires on load-{hook} before any output is sent.
     *
     * @return void
     */
    public function handleCreateNavigationPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pitea-customisation'));
        }

        $post_id = $this->insertNavigationPage();

        if (is_wp_error($post_id)) {
            wp_die(esc_html($post_id->get_error_message()));
        }

        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Create the navigation page with starter block content and ACF metadata.
     *
     * @return int|\WP_Error
     */
    private function insertNavigationPage(): int|\WP_Error
    {
        $post_content = <<<'EOT'
<!-- wp:acf/container {"name":"acf/container","data":{"amount":"0","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Hero undersida","patternName":"core/block/1820"}} -->
<!-- wp:acf/hero {"name":"acf/hero","data":{"custom_block_title":"","_custom_block_title":"field_block_title","mod_hero_display_as":"default","_mod_hero_display_as":"field_63ca5ed1394e1","mod_hero_byline":"","_mod_hero_byline":"field_614b3f1e6ed4a","mod_hero_meta":"","_mod_hero_meta":"field_63d78c4897632","mod_hero_body":"","_mod_hero_body":"field_614b3f5a6ed4b","mod_hero_background_type":"image","_mod_hero_background_type":"field_62c3f89f983b1","mod_hero_background_image":{"id":__PITEA_DUMMY_IMAGE_ID__,"top":"50","left":"50"},"_mod_hero_background_image":"field_614b3f786ed4c","mod_hero_size":"normal","_mod_hero_size":"field_614b43a186da4","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"edit"} /-->
<!-- /wp:acf/container -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lorem ipsum</h2>
<!-- /wp:heading -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns {"metadata":{"name":"Modul: Rekommenderat"}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"80%"} -->
<div class="wp-block-column" style="flex-basis:80%"><!-- wp:acf/recommend {"name":"acf/recommend","data":{"custom_block_title":"","_custom_block_title":"field_block_title","recommend_link_list_0_recommend_link_label":"Intern länk","_recommend_link_list_0_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_0_recommend_link_target":"","_recommend_link_list_0_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_0_recommend_link_is_external":"0","_recommend_link_list_0_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_0_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_0_recommend_link_icon":"field_696e48c055752","recommend_link_list_0_recommend_link_style":"filled","_recommend_link_list_0_recommend_link_style":"field_696e496bfea18","recommend_link_list_0_recommend_link_color":"primary","_recommend_link_list_0_recommend_link_color":"field_696e49b2fea19","recommend_link_list_1_recommend_link_label":"Extern länk","_recommend_link_list_1_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_1_recommend_link_target_external":"#","_recommend_link_list_1_recommend_link_target_external":"field_673b052aa3c4d","recommend_link_list_1_recommend_link_is_external":"1","_recommend_link_list_1_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_1_recommend_link_icon":"fa-regular fa-up-right-from-square","_recommend_link_list_1_recommend_link_icon":"field_696e48c055752","recommend_link_list_1_recommend_link_style":"outlined","_recommend_link_list_1_recommend_link_style":"field_696e496bfea18","recommend_link_list_1_recommend_link_color":"primary","_recommend_link_list_1_recommend_link_color":"field_696e49b2fea19","recommend_link_list":2,"_recommend_link_list":"field_61ea7ae22b203","template":"button","_template":"field_628b9314f8dee","recommend_link_icon_position":"before","_recommend_link_icon_position":"field_696f8c415f804","rekai_userootpath":"0","_rekai_userootpath":"field_628ca57c99f4b","rekai_subtree":"","_rekai_subtree":"field_628c963a693aa","rekai_excludetree":"","_rekai_excludetree":"field_628c96c84e43c","rekai_advanced_options":"","_rekai_advanced_options":"field_628b4e43953e8","rekai":"","_rekai":"field_628c958c693a9","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"20%"} -->
<div class="wp-block-column" style="flex-basis:20%"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"0","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"rgb(246,239,229)","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Snabblänkar på bakgrundsyta","patternName":"core/block/1841"}} -->
<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"6","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/quick-links {"name":"acf/quick-links","data":{"custom_block_title":"","_custom_block_title":"field_block_title","max_items_per_row":"4","_max_items_per_row":"field_69445f256a00a","use_icons":"0","_use_icons":"field_69445fc16a00b","use_short_description":"1","_use_short_description":"field_694460036a00c","link_gap":"2","_link_gap":"field_6957c3d4da403","links_0_title":"Lorem ipsum","_links_0_title":"field_694460786a00f","links_0_link":{"title":"","url":"#","target":""},"_links_0_link":"field_694460cc6a012","links_0_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_0_description":"field_694460b46a011","links_1_title":"Lorem ipsum","_links_1_title":"field_694460786a00f","links_1_link":{"title":"","url":"#","target":""},"_links_1_link":"field_694460cc6a012","links_1_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_1_description":"field_694460b46a011","links_2_title":"Lorem ipsum","_links_2_title":"field_694460786a00f","links_2_link":{"title":"","url":"#","target":""},"_links_2_link":"field_694460cc6a012","links_2_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_2_description":"field_694460b46a011","links_3_title":"Lorem ipsum","_links_3_title":"field_694460786a00f","links_3_link":{"title":"","url":"#","target":""},"_links_3_link":"field_694460cc6a012","links_3_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_3_description":"field_694460b46a011","links_4_title":"Lorem ipsum","_links_4_title":"field_694460786a00f","links_4_link":{"title":"","url":"#","target":""},"_links_4_link":"field_694460cc6a012","links_4_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_4_description":"field_694460b46a011","links_5_title":"Lorem ipsum","_links_5_title":"field_694460786a00f","links_5_link":{"title":"","url":"#","target":""},"_links_5_link":"field_694460cc6a012","links_5_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_5_description":"field_694460b46a011","links_6_title":"Lorem ipsum","_links_6_title":"field_694460786a00f","links_6_link":{"title":"","url":"#","target":""},"_links_6_link":"field_694460cc6a012","links_6_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_6_description":"field_694460b46a011","links_7_title":"Lorem ipsum","_links_7_title":"field_694460786a00f","links_7_link":{"title":"","url":"#","target":""},"_links_7_link":"field_694460cc6a012","links_7_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_7_description":"field_694460b46a011","links":8,"_links":"field_694460586a00e","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"6","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/posts {"name":"acf/posts","data":{"posts_display_as":"news","_posts_display_as":"field_571dfd4c0d9d9","posts_display_as_conditional":"news","_posts_display_as_conditional":"field_6762ecffda0e3","allow_user_modification":"0","_allow_user_modification":"field_67813612eb109","preamble":"","_preamble":"field_636249fee87cc","posts_open_links_in_new_tab":"","_posts_open_links_in_new_tab":"field_68469afc46fa3","show_as_slider":"0","_show_as_slider":"field_6356477fbc5e4","posts_highlight_first":"0","_posts_highlight_first":"field_628e0ffba7da4","posts_columns":"grid-md-6","_posts_columns":"field_571dfdf50d9da","posts_fields":["date","excerpt","title","image"],"_posts_fields":"field_571e01e7f246c","custom_block_title":"Nyheter","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","posts_data_source":"posttype","_posts_data_source":"field_571dfaafe6984","posts_data_post_type":"nyhet","_posts_data_post_type":"field_571dfc40f8114","posts_count":"4","_posts_count":"field_571dff4eb46c3","posts_pagination":"disabled","_posts_pagination":"field_671b3d7e4e7ed","archive_link":"1","_archive_link":"field_57ecf1007b749","archive_link_title":"Till nyhetsarkiv","_archive_link_title":"field_67e6e75d155eb","archive_link_style":"primary","_archive_link_style":"field_695d1fdbd4b25","archive_link_above_posts":"0","_archive_link_above_posts":"field_67e6eed195ff6","posts_data_network_sources":"","_posts_data_network_sources":"field_6710ff6562e8c","posts_data_get_posts_from_user_group":"0","_posts_data_get_posts_from_user_group":"field_6925d1012f482","posts_sort_by":"date","_posts_sort_by":"field_571dffca1d90b","posts_sort_order":"asc","_posts_sort_order":"field_571e00241d90c","posts_taxonomy_filter":"0","_posts_taxonomy_filter":"field_571e046536f0f","taxonomy_display":["nyhetskategori"],"_taxonomy_display":"field_630645dcff161"},"mode":"edit","metadata":{"name":"Modul: Nyheter navigationssida","patternName":"core/block/1846"}} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lorem ipsum rubrik</h2>
<!-- /wp:heading -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"0","_amount":"field_63cfdba39a6d2","border_radius":"md","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"rgb(237,241,233)","_color":"field_63cfdc219a6d3","text_color":"#0a0a0a","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview","metadata":{"name":"Modul: Bild vänster, text höger (klickbart kort)","patternName":"core/block/1850"}} -->
<!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:image {"id":__PITEA_DUMMY_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__PITEA_DUMMY_IMAGE_URL__" alt="Dummy image" class="wp-image-__PITEA_DUMMY_IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"1","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"Lorem ipsum","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"https//test.com","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"0","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs_0_custom_background_color":"--color-additional-4::#edf1e9","_manual_inputs_0_custom_background_color":"field_689b2cf733d44","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-4","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"0","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_image":__PITEA_DUMMY_IMAGE_ID__,"_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs_1_eyebrow":"","_manual_inputs_1_eyebrow":"field_6945264b7d66e","manual_inputs_1_title":"Lorem ipsum","_manual_inputs_1_title":"field_64ff22fdd91b8","manual_inputs_1_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_1_content":"field_64ff231ed91b9","manual_inputs_1_link":"#","_manual_inputs_1_link":"field_64ff232ad91ba","manual_inputs_1_link_text":"","_manual_inputs_1_link_text":"field_65002bce6d459","manual_inputs_1_show_link_as_button":"0","_manual_inputs_1_show_link_as_button":"field_69985fac063ef","manual_inputs_1_image":__PITEA_DUMMY_IMAGE_ID__,"_manual_inputs_1_image":"field_64ff2355d91bb","manual_inputs_1_box_icon":"","_manual_inputs_1_box_icon":"field_65293de2a26c7","manual_inputs_2_eyebrow":"","_manual_inputs_2_eyebrow":"field_6945264b7d66e","manual_inputs_2_title":"Lorem ipsum","_manual_inputs_2_title":"field_64ff22fdd91b8","manual_inputs_2_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_2_content":"field_64ff231ed91b9","manual_inputs_2_link":"#","_manual_inputs_2_link":"field_64ff232ad91ba","manual_inputs_2_link_text":"","_manual_inputs_2_link_text":"field_65002bce6d459","manual_inputs_2_show_link_as_button":"0","_manual_inputs_2_show_link_as_button":"field_69985fac063ef","manual_inputs_2_image":__PITEA_DUMMY_IMAGE_ID__,"_manual_inputs_2_image":"field_64ff2355d91bb","manual_inputs_2_box_icon":"","_manual_inputs_2_box_icon":"field_65293de2a26c7","manual_inputs":3,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit","metadata":{"name":"Modul: Puffar/inkastare undersida","patternName":"core/block/1858"}} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"Vanliga frågor","_custom_block_title":"field_block_title","display_as":"accordion","_display_as":"field_64ff23d0d91bf","display_as_conditional":"accordion","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","free_text_filtering":"0","_free_text_filtering":"field_67289fa6dfea3","accordion_spaced_sections":"0","_accordion_spaced_sections":"field_67f66637f8734","accordion_column_marking":"","_accordion_column_marking":"field_650067ed6cc3c","accordion_column_titles":"","_accordion_column_titles":"field_65005968bbc75","manual_inputs_0_title":"Lorem ipsum dolor sit amet?","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_accordion_column_values":"","_manual_inputs_0_accordion_column_values":"field_64ff2372d91bc","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_1_title":"Lorem ipsum dolor sit amet?","_manual_inputs_1_title":"field_64ff22fdd91b8","manual_inputs_1_accordion_column_values":"","_manual_inputs_1_accordion_column_values":"field_64ff2372d91bc","manual_inputs_1_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_1_content":"field_64ff231ed91b9","manual_inputs_2_title":"Lorem ipsum dolor sit amet?","_manual_inputs_2_title":"field_64ff22fdd91b8","manual_inputs_2_accordion_column_values":"","_manual_inputs_2_accordion_column_values":"field_64ff2372d91bc","manual_inputs_2_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_2_content":"field_64ff231ed91b9","manual_inputs":3,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->
EOT;

        $post_content = $this->injectDummyMediaIntoTemplates($post_content);
        if (is_wp_error($post_content)) {
            return $post_content;
        }

        $post_id = wp_insert_post([
            'post_title'   => 'Navigationssida – huvudområde',
            'post_content' => $post_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_parent'  => 0,
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta = [
            '_wp_page_template'               => 'one-page.blade.php',
            'post_single_show_featured_image'  => '0',
            '_post_single_show_featured_image' => 'field_56c33e148efe3',
            'post_one_page_show_title'         => '0',
            '_post_one_page_show_title'        => 'field_64f8759c6a2a2',
            'post_table_of_contents'           => '0',
            '_post_table_of_contents'          => 'field_68651a9b0c6ac',
            'exclude_from_google_translate'    => '0',
            '_exclude_from_google_translate'   => 'field_646c5d27c7ebf',
            'quicklinks_placement'             => 'default',
            '_quicklinks_placement'            => 'field_64227ca019e18',
            'share_button_placement'           => 'none',
            '_share_button_placement'          => 'field_share_button_placement',
            'show_accessibility_buttons'       => '0',
            '_show_accessibility_buttons'      => 'field_show_accessibility_buttons',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Create the theme page and redirect the user to the block editor.
     * Fires on load-{hook} before any output is sent.
     *
     * @return void
     */
    public function handleCreateThemePage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pitea-customisation'));
        }

        $post_id = $this->insertThemePage();

        if (is_wp_error($post_id)) {
            wp_die(esc_html($post_id->get_error_message()));
        }

        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Create the theme page with starter block content and ACF metadata.
     *
     * @return int|\WP_Error
     */
    private function insertThemePage(): int|\WP_Error
    {
        $post_content = <<<'EOT'
<!-- wp:acf/container {"name":"acf/container","data":{"amount":"4","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"#D1DBC8","_color":"field_63cfdc219a6d3","text_color":"#000000","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","className":"top-element","metadata":{"name":"Modul: Hero temasida","patternName":"core/block/2056"}} -->
<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Temasida</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>En ingress som sammanfattar sidans innehåll. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":__PITEA_DUMMY_INVERTED_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__PITEA_DUMMY_INVERTED_URL__" alt="Dummybild" class="wp-image-__PITEA_DUMMY_INVERTED_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"2","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lorem ipsum</h2>
<!-- /wp:heading -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-4","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"0","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs_1_eyebrow":"","_manual_inputs_1_eyebrow":"field_6945264b7d66e","manual_inputs_1_title":"Lorem ipsum","_manual_inputs_1_title":"field_64ff22fdd91b8","manual_inputs_1_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_1_content":"field_64ff231ed91b9","manual_inputs_1_link":"#","_manual_inputs_1_link":"field_64ff232ad91ba","manual_inputs_1_link_text":"","_manual_inputs_1_link_text":"field_65002bce6d459","manual_inputs_1_show_link_as_button":"0","_manual_inputs_1_show_link_as_button":"field_69985fac063ef","manual_inputs_1_image":"","_manual_inputs_1_image":"field_64ff2355d91bb","manual_inputs_1_box_icon":"","_manual_inputs_1_box_icon":"field_65293de2a26c7","manual_inputs_2_eyebrow":"","_manual_inputs_2_eyebrow":"field_6945264b7d66e","manual_inputs_2_title":"Lorem ipsum","_manual_inputs_2_title":"field_64ff22fdd91b8","manual_inputs_2_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_2_content":"field_64ff231ed91b9","manual_inputs_2_link":"#","_manual_inputs_2_link":"field_64ff232ad91ba","manual_inputs_2_link_text":"","_manual_inputs_2_link_text":"field_65002bce6d459","manual_inputs_2_show_link_as_button":"0","_manual_inputs_2_show_link_as_button":"field_69985fac063ef","manual_inputs_2_image":"","_manual_inputs_2_image":"field_64ff2355d91bb","manual_inputs_2_box_icon":"","_manual_inputs_2_box_icon":"field_65293de2a26c7","manual_inputs":3,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit","metadata":{"name":"Modul: Puffar/inkastare undersida","categories":[78],"patternName":"core/block/1858"}} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"4","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"#F6EFE5","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Puffar/inkastare med ikon","categories":[78],"patternName":"core/block/1872"}} -->
<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"2","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lorem ipsum</h2>
<!-- /wp:heading -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:acf/link-cards {"name":"acf/link-cards","data":{"custom_block_title":"","_custom_block_title":"field_block_title","columns":"2","_columns":"field_67892a1b3c4d5e70","cards_0_title":"Lorem ipsum","_cards_0_title":"field_67892a1b3c4d5e72","cards_0_description":"Dolor sit amet","_cards_0_description":"field_67892a1b3c4d5e73","cards_0_link":"","_cards_0_link":"field_67892a1b3c4d5e74","cards_0_icon":"fa-regular fa-icons","_cards_0_icon":"field_67892a1b3c4d5e75","cards_0_color_theme":"{\"mode\":\"theme\",\"theme\":\"brown\",\"backgroundColor\":\"#764a0f\",\"iconColor\":\"#e7d6bf\"}","_cards_0_color_theme":"field_67892a1b3c4d5e76","cards_1_title":"Lorem ipsum","_cards_1_title":"field_67892a1b3c4d5e72","cards_1_description":"Dolor sit amet","_cards_1_description":"field_67892a1b3c4d5e73","cards_1_link":"","_cards_1_link":"field_67892a1b3c4d5e74","cards_1_icon":"fa-regular fa-icons","_cards_1_icon":"field_67892a1b3c4d5e75","cards_1_color_theme":"{\"mode\":\"theme\",\"theme\":\"brown\",\"backgroundColor\":\"#764a0f\",\"iconColor\":\"#e7d6bf\"}","_cards_1_color_theme":"field_67892a1b3c4d5e76","cards_2_title":"Lorem ipsum","_cards_2_title":"field_67892a1b3c4d5e72","cards_2_description":"Dolor sit amet","_cards_2_description":"field_67892a1b3c4d5e73","cards_2_link":"","_cards_2_link":"field_67892a1b3c4d5e74","cards_2_icon":"fa-regular fa-icons","_cards_2_icon":"field_67892a1b3c4d5e75","cards_2_color_theme":"{\"mode\":\"theme\",\"theme\":\"brown\",\"backgroundColor\":\"#764a0f\",\"iconColor\":\"#e7d6bf\"}","_cards_2_color_theme":"field_67892a1b3c4d5e76","cards_3_title":"Lorem ipsum","_cards_3_title":"field_67892a1b3c4d5e72","cards_3_description":"Dolor sit amet","_cards_3_description":"field_67892a1b3c4d5e73","cards_3_link":"","_cards_3_link":"field_67892a1b3c4d5e74","cards_3_icon":"fa-regular fa-icons","_cards_3_icon":"field_67892a1b3c4d5e75","cards_3_color_theme":"{\"mode\":\"theme\",\"theme\":\"brown\",\"backgroundColor\":\"#764a0f\",\"iconColor\":\"#e7d6bf\"}","_cards_3_color_theme":"field_67892a1b3c4d5e76","cards":4,"_cards":"field_67892a1b3c4d5e71","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:columns {"verticalAlignment":"center","metadata":{"name":"Modul: Bild vänster, text höger (undersida)","categories":[78],"patternName":"core/block/1887"}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"45%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:45%"><!-- wp:image {"id":__PITEA_DUMMY_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__PITEA_DUMMY_IMAGE_URL__" alt="" class="wp-image-__PITEA_DUMMY_IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"55%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:55%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"1","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_button_color":"primary","_manual_inputs_0_button_color":"field_69986078063f1","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->

<!-- wp:columns {"verticalAlignment":"center","metadata":{"name":"Modul: Text vänster, bild höger (undersida)","categories":[78],"patternName":"core/block/1888"}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"55%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:55%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"1","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_button_color":"primary","_manual_inputs_0_button_color":"field_69986078063f1","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"45%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:45%"><!-- wp:image {"id":__PITEA_DUMMY_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__PITEA_DUMMY_IMAGE_URL__" alt="" class="wp-image-__PITEA_DUMMY_IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"16","_space_amount":"field_611d0016546f1"},"mode":"preview"} /-->
EOT;

        $post_content = $this->injectDummyMediaIntoTemplates($post_content);
        if (is_wp_error($post_content)) {
            return $post_content;
        }

        $post_id = wp_insert_post([
            'post_title'   => 'Temasida',
            'post_content' => $post_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_parent'  => 0,
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta = [
            '_wp_page_template'          => 'one-page.blade.php',
            'share_button_placement'     => 'none',
            '_share_button_placement'    => 'field_share_button_placement',
            'show_accessibility_buttons' => '0',
            '_show_accessibility_buttons' => 'field_show_accessibility_buttons',
            '_customer_feedback_exclude' => '1',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Create the navigation second-level page and redirect the user to the block editor.
     *
     * @return void
     */
    public function handleCreateNavSecondLevelPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pitea-customisation'));
        }

        $post_id = $this->insertNavSecondLevelPage();

        if (is_wp_error($post_id)) {
            wp_die(esc_html($post_id->get_error_message()));
        }

        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Create the navigation second-level page with starter block content and ACF metadata.
     *
     * @return int|\WP_Error
     */
    private function insertNavSecondLevelPage(): int|\WP_Error
    {
        $post_content = <<<'EOT'
<!-- wp:acf/container {"name":"acf/container","data":{"amount":"0","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Hero undersida","patternName":"core/block/1820"}} -->
<!-- wp:acf/hero {"name":"acf/hero","data":{"custom_block_title":"","_custom_block_title":"field_block_title","mod_hero_display_as":"default","_mod_hero_display_as":"field_63ca5ed1394e1","mod_hero_byline":"","_mod_hero_byline":"field_614b3f1e6ed4a","mod_hero_meta":"","_mod_hero_meta":"field_63d78c4897632","mod_hero_body":"","_mod_hero_body":"field_614b3f5a6ed4b","mod_hero_background_type":"image","_mod_hero_background_type":"field_62c3f89f983b1","mod_hero_background_image":{"id":__PITEA_DUMMY_IMAGE_ID__,"top":"50","left":"50"},"_mod_hero_background_image":"field_614b3f786ed4c","mod_hero_size":"normal","_mod_hero_size":"field_614b43a186da4","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"edit"} /-->
<!-- /wp:acf/container -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lorem ipsum</h2>
<!-- /wp:heading -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns {"metadata":{"name":"Modul: Rekommenderat"}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"80%"} -->
<div class="wp-block-column" style="flex-basis:80%"><!-- wp:acf/recommend {"name":"acf/recommend","data":{"custom_block_title":"","_custom_block_title":"field_block_title","recommend_link_list_0_recommend_link_label":"Intern länk","_recommend_link_list_0_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_0_recommend_link_target":"","_recommend_link_list_0_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_0_recommend_link_is_external":"0","_recommend_link_list_0_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_0_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_0_recommend_link_icon":"field_696e48c055752","recommend_link_list_0_recommend_link_style":"filled","_recommend_link_list_0_recommend_link_style":"field_696e496bfea18","recommend_link_list_0_recommend_link_color":"primary","_recommend_link_list_0_recommend_link_color":"field_696e49b2fea19","recommend_link_list_1_recommend_link_label":"Intern länk","_recommend_link_list_1_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_1_recommend_link_target":"","_recommend_link_list_1_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_1_recommend_link_is_external":"0","_recommend_link_list_1_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_1_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_1_recommend_link_icon":"field_696e48c055752","recommend_link_list_1_recommend_link_style":"filled","_recommend_link_list_1_recommend_link_style":"field_696e496bfea18","recommend_link_list_1_recommend_link_color":"primary","_recommend_link_list_1_recommend_link_color":"field_696e49b2fea19","recommend_link_list_2_recommend_link_label":"Intern länk","_recommend_link_list_2_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_2_recommend_link_target":"","_recommend_link_list_2_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_2_recommend_link_is_external":"0","_recommend_link_list_2_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_2_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_2_recommend_link_icon":"field_696e48c055752","recommend_link_list_2_recommend_link_style":"filled","_recommend_link_list_2_recommend_link_style":"field_696e496bfea18","recommend_link_list_2_recommend_link_color":"primary","_recommend_link_list_2_recommend_link_color":"field_696e49b2fea19","recommend_link_list_3_recommend_link_label":"Intern länk","_recommend_link_list_3_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_3_recommend_link_target":"","_recommend_link_list_3_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_3_recommend_link_is_external":"0","_recommend_link_list_3_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_3_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_3_recommend_link_icon":"field_696e48c055752","recommend_link_list_3_recommend_link_style":"filled","_recommend_link_list_3_recommend_link_style":"field_696e496bfea18","recommend_link_list_3_recommend_link_color":"primary","_recommend_link_list_3_recommend_link_color":"field_696e49b2fea19","recommend_link_list_4_recommend_link_label":"Intern länk","_recommend_link_list_4_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_4_recommend_link_target":"","_recommend_link_list_4_recommend_link_target":"field_61ea7b1c2b205","recommend_link_list_4_recommend_link_is_external":"0","_recommend_link_list_4_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_4_recommend_link_icon":"fa-regular fa-link","_recommend_link_list_4_recommend_link_icon":"field_696e48c055752","recommend_link_list_4_recommend_link_style":"filled","_recommend_link_list_4_recommend_link_style":"field_696e496bfea18","recommend_link_list_4_recommend_link_color":"primary","_recommend_link_list_4_recommend_link_color":"field_696e49b2fea19","recommend_link_list_5_recommend_link_label":"Extern länk","_recommend_link_list_5_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_5_recommend_link_target_external":"#","_recommend_link_list_5_recommend_link_target_external":"field_673b052aa3c4d","recommend_link_list_5_recommend_link_is_external":"1","_recommend_link_list_5_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_5_recommend_link_icon":"fa-regular fa-up-right-from-square","_recommend_link_list_5_recommend_link_icon":"field_696e48c055752","recommend_link_list_5_recommend_link_style":"outlined","_recommend_link_list_5_recommend_link_style":"field_696e496bfea18","recommend_link_list_5_recommend_link_color":"primary","_recommend_link_list_5_recommend_link_color":"field_696e49b2fea19","recommend_link_list_6_recommend_link_label":"Extern länk","_recommend_link_list_6_recommend_link_label":"field_61ea7afd2b204","recommend_link_list_6_recommend_link_target_external":"#","_recommend_link_list_6_recommend_link_target_external":"field_673b052aa3c4d","recommend_link_list_6_recommend_link_is_external":"1","_recommend_link_list_6_recommend_link_is_external":"field_673b050aa3c4c","recommend_link_list_6_recommend_link_icon":"fa-regular fa-up-right-from-square","_recommend_link_list_6_recommend_link_icon":"field_696e48c055752","recommend_link_list_6_recommend_link_style":"outlined","_recommend_link_list_6_recommend_link_style":"field_696e496bfea18","recommend_link_list_6_recommend_link_color":"primary","_recommend_link_list_6_recommend_link_color":"field_696e49b2fea19","recommend_link_list":7,"_recommend_link_list":"field_61ea7ae22b203","template":"button","_template":"field_628b9314f8dee","recommend_link_icon_position":"before","_recommend_link_icon_position":"field_696f8c415f804","rekai_userootpath":"0","_rekai_userootpath":"field_628ca57c99f4b","rekai_subtree":"","_rekai_subtree":"field_628c963a693aa","rekai_excludetree":"","_rekai_excludetree":"field_628c96c84e43c","rekai_advanced_options":"","_rekai_advanced_options":"field_628b4e43953e8","rekai":"","_rekai":"field_628c958c693a9","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"20%"} -->
<div class="wp-block-column" style="flex-basis:20%"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"0","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"#EDF1E9","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Snabblänkar på bakgrundsyta","patternName":"core/block/1841"}} -->
<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"6","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/quick-links {"name":"acf/quick-links","data":{"custom_block_title":"","_custom_block_title":"field_block_title","max_items_per_row":"4","_max_items_per_row":"field_69445f256a00a","use_icons":"0","_use_icons":"field_69445fc16a00b","use_short_description":"1","_use_short_description":"field_694460036a00c","link_gap":"2","_link_gap":"field_6957c3d4da403","links_0_title":"Lorem ipsum","_links_0_title":"field_694460786a00f","links_0_link":{"title":"","url":"#","target":""},"_links_0_link":"field_694460cc6a012","links_0_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_0_description":"field_694460b46a011","links_1_title":"Lorem ipsum","_links_1_title":"field_694460786a00f","links_1_link":{"title":"","url":"#","target":""},"_links_1_link":"field_694460cc6a012","links_1_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_1_description":"field_694460b46a011","links_2_title":"Lorem ipsum","_links_2_title":"field_694460786a00f","links_2_link":{"title":"","url":"#","target":""},"_links_2_link":"field_694460cc6a012","links_2_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_2_description":"field_694460b46a011","links_3_title":"Lorem ipsum","_links_3_title":"field_694460786a00f","links_3_link":{"title":"","url":"#","target":""},"_links_3_link":"field_694460cc6a012","links_3_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_3_description":"field_694460b46a011","links_4_title":"Lorem ipsum","_links_4_title":"field_694460786a00f","links_4_link":{"title":"","url":"#","target":""},"_links_4_link":"field_694460cc6a012","links_4_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_4_description":"field_694460b46a011","links_5_title":"Lorem ipsum","_links_5_title":"field_694460786a00f","links_5_link":{"title":"","url":"#","target":""},"_links_5_link":"field_694460cc6a012","links_5_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_5_description":"field_694460b46a011","links_6_title":"Lorem ipsum","_links_6_title":"field_694460786a00f","links_6_link":{"title":"","url":"#","target":""},"_links_6_link":"field_694460cc6a012","links_6_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_6_description":"field_694460b46a011","links_7_title":"Lorem ipsum","_links_7_title":"field_694460786a00f","links_7_link":{"title":"","url":"#","target":""},"_links_7_link":"field_694460cc6a012","links_7_description":"Etiam blandit dignissim augue a pretium. Cras efficitur nisl pretium erat egestas, quis porttitor odio dapibus.","_links_7_description":"field_694460b46a011","links":8,"_links":"field_694460586a00e","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"6","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns {"metadata":{"name":"Modul: Vanliga frågor","categories":[78],"patternName":"core/block/1860"}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%","metadata":{"name":"Vanliga frågor"}} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"Vanliga frågor","_custom_block_title":"field_block_title","display_as":"accordion","_display_as":"field_64ff23d0d91bf","display_as_conditional":"accordion","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","free_text_filtering":"0","_free_text_filtering":"field_67289fa6dfea3","accordion_spaced_sections":"0","_accordion_spaced_sections":"field_67f66637f8734","accordion_column_marking":"","_accordion_column_marking":"field_650067ed6cc3c","accordion_column_titles":"","_accordion_column_titles":"field_65005968bbc75","manual_inputs_0_title":"Lorem ipsum dolor sit amet?","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_accordion_column_values":"","_manual_inputs_0_accordion_column_values":"field_64ff2372d91bc","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_1_title":"Lorem ipsum dolor sit amet?","_manual_inputs_1_title":"field_64ff22fdd91b8","manual_inputs_1_accordion_column_values":"","_manual_inputs_1_accordion_column_values":"field_64ff2372d91bc","manual_inputs_1_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_1_content":"field_64ff231ed91b9","manual_inputs_2_title":"Lorem ipsum dolor sit amet?","_manual_inputs_2_title":"field_64ff22fdd91b8","manual_inputs_2_accordion_column_values":"","_manual_inputs_2_accordion_column_values":"field_64ff2372d91bc","manual_inputs_2_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Etiam a tincidunt purus, quis gravida turpis. Nullam imperdiet nibh et velit luctus, vitae aliquam turpis suscipit. Donec sit amet pellentesque nibh, quis consectetur mi. Praesent cursus mauris ac sodales gravida. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Cras sit amet viverra odio, ac bibendum enim. Sed pretium pellentesque velit, sit amet egestas purus rutrum et.","_manual_inputs_2_content":"field_64ff231ed91b9","manual_inputs":3,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"4","_amount":"field_63cfdba39a6d2","border_radius":"","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"#F6EFE5","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"preview","metadata":{"name":"Modul: Puffar/inkastare med ikon","patternName":"core/block/1872"}} -->
<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/link-cards {"name":"acf/link-cards","data":{"custom_block_title":"","_custom_block_title":"field_block_title","columns":"2","_columns":"field_67892a1b3c4d5e70","cards_0_title":"Lorem ipsum","_cards_0_title":"field_67892a1b3c4d5e72","cards_0_description":"Dolor sit amet","_cards_0_description":"field_67892a1b3c4d5e73","cards_0_link":"","_cards_0_link":"field_67892a1b3c4d5e74","cards_0_icon":"fa-regular fa-icons","_cards_0_icon":"field_67892a1b3c4d5e75","cards_0_color_theme":"{\u0022mode\u0022:\u0022theme\u0022,\u0022theme\u0022:\u0022brown\u0022,\u0022backgroundColor\u0022:\u0022#764a0f\u0022,\u0022iconColor\u0022:\u0022#e7d6bf\u0022}","_cards_0_color_theme":"field_67892a1b3c4d5e76","cards_1_title":"Lorem ipsum","_cards_1_title":"field_67892a1b3c4d5e72","cards_1_description":"Dolor sit amet","_cards_1_description":"field_67892a1b3c4d5e73","cards_1_link":"","_cards_1_link":"field_67892a1b3c4d5e74","cards_1_icon":"fa-regular fa-icons","_cards_1_icon":"field_67892a1b3c4d5e75","cards_1_color_theme":"{\u0022mode\u0022:\u0022theme\u0022,\u0022theme\u0022:\u0022brown\u0022,\u0022backgroundColor\u0022:\u0022#764a0f\u0022,\u0022iconColor\u0022:\u0022#e7d6bf\u0022}","_cards_1_color_theme":"field_67892a1b3c4d5e76","cards_2_title":"Lorem ipsum","_cards_2_title":"field_67892a1b3c4d5e72","cards_2_description":"Dolor sit amet","_cards_2_description":"field_67892a1b3c4d5e73","cards_2_link":"","_cards_2_link":"field_67892a1b3c4d5e74","cards_2_icon":"fa-regular fa-icons","_cards_2_icon":"field_67892a1b3c4d5e75","cards_2_color_theme":"{\u0022mode\u0022:\u0022theme\u0022,\u0022theme\u0022:\u0022brown\u0022,\u0022backgroundColor\u0022:\u0022#764a0f\u0022,\u0022iconColor\u0022:\u0022#e7d6bf\u0022}","_cards_2_color_theme":"field_67892a1b3c4d5e76","cards_3_title":"Lorem ipsum","_cards_3_title":"field_67892a1b3c4d5e72","cards_3_description":"Dolor sit amet","_cards_3_description":"field_67892a1b3c4d5e73","cards_3_link":"","_cards_3_link":"field_67892a1b3c4d5e74","cards_3_icon":"fa-regular fa-icons","_cards_3_icon":"field_67892a1b3c4d5e75","cards_3_color_theme":"{\u0022mode\u0022:\u0022theme\u0022,\u0022theme\u0022:\u0022brown\u0022,\u0022backgroundColor\u0022:\u0022#764a0f\u0022,\u0022iconColor\u0022:\u0022#e7d6bf\u0022}","_cards_3_color_theme":"field_67892a1b3c4d5e76","cards":4,"_cards":"field_67892a1b3c4d5e71","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"4","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns {"verticalAlignment":"center","metadata":{"name":"Modul: Bild vänster, text höger (undersida)","patternName":"core/block/1887"}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"45%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:45%"><!-- wp:image {"id":__PITEA_DUMMY_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__PITEA_DUMMY_IMAGE_URL__" alt="Dummy image" class="wp-image-__PITEA_DUMMY_IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"55%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:55%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"1","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_button_color":"primary","_manual_inputs_0_button_color":"field_69986078063f1","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:columns {"verticalAlignment":"center","metadata":{"name":"Modul: Text vänster, bild höger (undersida)","patternName":"core/block/1888"}} -->
<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","width":"55%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:55%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"1","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_button_color":"primary","_manual_inputs_0_button_color":"field_69986078063f1","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"45%","className":"has-custom-width"} -->
<div class="wp-block-column is-vertically-aligned-center has-custom-width" style="flex-basis:45%"><!-- wp:image {"id":__PITEA_DUMMY_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__PITEA_DUMMY_IMAGE_URL__" alt="Dummy image" class="wp-image-__PITEA_DUMMY_IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
EOT;

        $post_content = $this->injectDummyMediaIntoTemplates($post_content);
        if (is_wp_error($post_content)) {
            return $post_content;
        }

        $post_id = wp_insert_post([
            'post_title'   => 'Navigationssida – underområde',
            'post_content' => $post_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_parent'  => 0,
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta = [
            '_wp_page_template'          => 'one-page.blade.php',
            'share_button_placement'     => 'none',
            '_share_button_placement'    => 'field_share_button_placement',
            'show_accessibility_buttons' => '0',
            '_show_accessibility_buttons' => 'field_show_accessibility_buttons',
            '_customer_feedback_exclude' => '1',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Create the content page and redirect the user to the block editor.
     *
     * @return void
     */
    public function handleCreateContentPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pitea-customisation'));
        }

        $post_id = $this->insertContentPage();

        if (is_wp_error($post_id)) {
            wp_die(esc_html($post_id->get_error_message()));
        }

        wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Create the content page with starter block content and ACF metadata.
     *
     * @return int|\WP_Error
     */
    private function insertContentPage(): int|\WP_Error
    {
        $post_content = <<<'EOT'
<!-- wp:paragraph -->
<p>En ingress som sammanfattar sidans innehåll. Exempelvis: Här hittar du information om hur du ansöker om plats, vad som gäller för avgifter och öppettider samt vad ditt barn får ta del av i förskolan.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">En rubrik</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh. Nulla vitae venenatis diam. Aliquam sapien urna, volutpat sit amet lacinia quis, ornare nec leo. Ut ut vestibulum risus. Aliquam erat volutpat. Aliquam quis erat vitae arcu mattis tempus sit amet eget sem. Sed hendrerit sapien at condimentum finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
<!-- /wp:paragraph -->

<!-- wp:acf/step-by-step {"name":"acf/step-by-step","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","timeline_steps_0_step_title":"Steg 1: Gör såhär","_timeline_steps_0_step_title":"field_step-by-step_title","timeline_steps_0_step_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh.","_timeline_steps_0_step_content":"field_step-by-step_content","timeline_steps_0_open_by_default":"1","_timeline_steps_0_open_by_default":"field_6949387375053","timeline_steps_1_step_title":"Steg 2: Gör såhär","_timeline_steps_1_step_title":"field_step-by-step_title","timeline_steps_1_step_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh.","_timeline_steps_1_step_content":"field_step-by-step_content","timeline_steps_1_open_by_default":"0","_timeline_steps_1_open_by_default":"field_6949387375053","timeline_steps_2_step_title":"Steg 3: Gör såhär","_timeline_steps_2_step_title":"field_step-by-step_title","timeline_steps_2_step_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh.","_timeline_steps_2_step_content":"field_step-by-step_content","timeline_steps_2_open_by_default":"0","_timeline_steps_2_open_by_default":"field_6949387375053","timeline_steps_3_step_title":"Steg 4: Gör såhär","_timeline_steps_3_step_title":"field_step-by-step_title","timeline_steps_3_step_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh.","_timeline_steps_3_step_content":"field_step-by-step_content","timeline_steps_3_open_by_default":"0","_timeline_steps_3_open_by_default":"field_6949387375053","timeline_steps":4,"_timeline_steps":"field_step-by-step_steps"},"mode":"preview","metadata":{"name":"Modul: Steg för steg","patternName":"core/block/1898"}} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">En andra rubrik</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Något annat kul på sidan</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh. Nulla vitae venenatis diam. Aliquam sapien urna, volutpat sit amet lacinia quis, ornare nec leo. Ut ut vestibulum risus. Aliquam erat volutpat. Aliquam quis erat vitae arcu mattis tempus sit amet eget sem. Sed hendrerit sapien at condimentum finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
<!-- /wp:paragraph -->

<!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"accordion","_display_as":"field_64ff23d0d91bf","display_as_conditional":"accordion","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","free_text_filtering":"0","_free_text_filtering":"field_67289fa6dfea3","accordion_spaced_sections":"0","_accordion_spaced_sections":"field_67f66637f8734","accordion_column_marking":"","_accordion_column_marking":"field_650067ed6cc3c","accordion_column_titles":"","_accordion_column_titles":"field_65005968bbc75","manual_inputs_0_title":"Hur söker man en förskoleplats?","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_accordion_column_values":"","_manual_inputs_0_accordion_column_values":"field_64ff2372d91bc","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris sed ultricies ligula. Fusce gravida, enim non ultrices tempus, dolor lacus bibendum orci, id gravida tellus justo quis nibh. Nulla vitae venenatis diam. Aliquam sapien urna, volutpat sit amet lacinia quis, ornare nec leo. Ut ut vestibulum risus. Aliquam erat volutpat. Aliquam quis erat vitae arcu mattis tempus sit amet eget sem. Sed hendrerit sapien at condimentum finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview","metadata":{"name":"Modul: Vanliga frågor (samlingssida)","patternName":"core/block/1899"}} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"2","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/slider {"name":"acf/slider","data":{"custom_block_title":"","_custom_block_title":"field_block_title","slider_format":"ratio-16-9","_slider_format":"field_573dce058a66e","slides_autoslide":"0","_slides_autoslide":"field_5731c6d886811","slides_per_page":"1","_slides_per_page":"field_633d95fb739ac","additional_options":["wrapAround"],"_additional_options":"field_58933fb6f5ed4","slides_0_image":{"id":__PITEA_DUMMY_IMAGE_ID__,"top":50,"left":50},"_slides_0_image":"field_56a5ed2f398dc","slides_0_textblock_position":"bottom","_slides_0_textblock_position":"field_56e7fa230ee09","slides_0_textblock_title":"","_slides_0_textblock_title":"field_5702597b7d869","slides_0_textblock_content":"","_slides_0_textblock_content":"field_56ab235393f04","slides_0_link_type":"false","_slides_0_link_type":"field_56fa82a2d464d","slides_1_image":{"id":__PITEA_DUMMY_IMAGE_ID__,"top":50,"left":50},"_slides_1_image":"field_56a5ed2f398dc","slides_1_textblock_position":"bottom","_slides_1_textblock_position":"field_56e7fa230ee09","slides_1_textblock_title":"","_slides_1_textblock_title":"field_5702597b7d869","slides_1_textblock_content":"","_slides_1_textblock_content":"field_56ab235393f04","slides_1_link_type":"false","_slides_1_link_type":"field_56fa82a2d464d","slides_2_image":{"id":__PITEA_DUMMY_IMAGE_ID__,"top":50,"left":50},"_slides_2_image":"field_56a5ed2f398dc","slides_2_textblock_position":"bottom","_slides_2_textblock_position":"field_56e7fa230ee09","slides_2_textblock_title":"","_slides_2_textblock_title":"field_5702597b7d869","slides_2_textblock_content":"","_slides_2_textblock_content":"field_56ab235393f04","slides_2_link_type":"false","_slides_2_link_type":"field_56fa82a2d464d","_slides_layout_meta":{"disabled":[],"renamed":[]},"slides":["image","image","image"],"_slides":"field_56a5e994398d6","slider_show_stepper":"1","_slider_show_stepper":"field_slider_show_stepper","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit","metadata":{"name":"Modul: Bildsnurra","patternName":"core/block/1900"}} /-->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"2","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/inlaylist {"name":"acf/inlaylist","data":{"custom_block_title":"Länkar","_custom_block_title":"field_block_title","icon_last":"1","_icon_last":"field_689f3c1fb7268","items_0_type":"internal","_items_0_type":"field_569e068b33f31","items_0_title":"Länk till intern sida","_items_0_title":"field_569e0567eb085","items_0_link_internal":1330,"_items_0_link_internal":"field_569e05bceb086","items_0_date":"0","_items_0_date":"field_569e05f8eb087","items_1_type":"internal","_items_1_type":"field_569e068b33f31","items_1_title":"Länk till PDF","_items_1_title":"field_569e0567eb085","items_1_link_internal":811,"_items_1_link_internal":"field_569e05bceb086","items_1_date":"0","_items_1_date":"field_569e05f8eb087","items_2_type":"external","_items_2_type":"field_569e068b33f31","items_2_titel":"Extern länk","_items_2_titel":"field_608e69429b2f7","items_2_link_external":"https://www.aftonbladet.se","_items_2_link_external":"field_569e06f633f32","items":3,"_items":"field_569e0559eb084","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit","metadata":{"name":"Modul: Länkar/Filer","patternName":"core/block/1905"}} /-->

<!-- wp:acf/container {"name":"acf/container","data":{"amount":"4","_amount":"field_63cfdba39a6d2","border_radius":"md","_border_radius":"field_6807afdfba66c","shadow":"0","_shadow":"field_68088e6bbe241","content_width":"standard","_content_width":"field_644b6d221b7a4","backgroundImage":"","_backgroundImage":"field_6405fea65cc8f","background_color_type":"default","_background_color_type":"field_64831fa89c119","color":"#F6EFE5","_color":"field_63cfdc219a6d3","text_color":"","_text_color":"field_644b77128c900","lang":"auto","_lang":"field_636e42408367e"},"mode":"preview","metadata":{"name":"Modul: Kontaktruta samlingssida","patternName":"core/block/1902"}} -->
<!-- wp:heading -->
<h2 class="wp-block-heading">Kontakta utbildningsförvaltningen</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent velit purus, dapibus eget lorem faucibus, consequat fringilla urna.</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p><strong>Ring</strong>: 0911-69 60 00</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p><a href="mailto:kommun@pitea.se" data-type="mailto" data-id="mailto:" target="_blank" rel="noreferrer noopener"><strong>Maila</strong>: kommun@pitea.se</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:acf/container -->
EOT;

        $post_content = $this->injectDummyMediaIntoTemplates($post_content);
        if (is_wp_error($post_content)) {
            return $post_content;
        }

        $post_id = wp_insert_post([
            'post_title'   => 'Innehållssida',
            'post_content' => $post_content,
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_parent'  => 0,
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $meta = [
            '_wp_page_template'          => 'one-page.blade.php',
            'share_button_placement'     => 'none',
            '_share_button_placement'    => 'field_share_button_placement',
            'show_accessibility_buttons' => '0',
            '_show_accessibility_buttons' => 'field_show_accessibility_buttons',
            '_customer_feedback_exclude' => '1',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        return $post_id;
    }

    /**
     * Replace dummy placeholders with real attachment IDs and URLs for this site.
     *
     * @return string|\WP_Error
     */
    private function injectDummyMediaIntoTemplates(string $postContent): string|\WP_Error
    {
        $standardId = $this->ensureDummyAttachmentToMediaLibrary('dummy-image.jpg');
        if (is_wp_error($standardId)) {
            return $standardId;
        }

        $invertedId = $this->ensureDummyAttachmentToMediaLibrary('dummy-image-inverted.jpg');
        if (is_wp_error($invertedId)) {
            return $invertedId;
        }

        $standardUrl = esc_url((string) wp_get_attachment_url($standardId));
        $invertedUrl = esc_url((string) wp_get_attachment_url($invertedId));

        return str_replace(
            [
                self::PLACEHOLDER_DUMMY_INVERTED_URL,
                self::PLACEHOLDER_DUMMY_IMAGE_URL,
                self::PLACEHOLDER_DUMMY_INVERTED_ID,
                self::PLACEHOLDER_DUMMY_IMAGE_ID,
            ],
            [
                $invertedUrl,
                $standardUrl,
                (string) $invertedId,
                (string) $standardId,
            ],
            $postContent
        );
    }

    /**
     * Ensure a file from static/images/dummies/ exists as a Media Library attachment.
     * Caches the attachment ID in an option so IDs stay stable per environment.
     *
     * @param non-empty-string $fileName Basename only, e.g. dummy-image.jpg
     * @return int|\WP_Error
     */
    private function ensureDummyAttachmentToMediaLibrary(string $fileName): int|\WP_Error
    {
        $optionKey = 'pitea_customisation_dummy_attachment_' . md5($fileName);
        $cached    = get_option($optionKey);

        if (is_numeric($cached)) {
            $cached = (int) $cached;
            if ($cached > 0 && get_post_type($cached) === 'attachment') {
                return $cached;
            }
        }

        $source = PITEA_CUSTOMISATION_PATH . 'static/images/dummies/' . ltrim($fileName, '/');
        if (!is_readable($source)) {
            return new \WP_Error(
                'pitea_dummy_missing',
                sprintf(
                    /* translators: %s: path relative to plugin */
                    __('Dummy image file not found: %s', 'pitea-customisation'),
                    'static/images/dummies/' . $fileName
                )
            );
        }

        global $wpdb;
        $basename = basename($source);
        $like     = '%' . $wpdb->esc_like($basename);
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            WHERE p.post_type = 'attachment' AND pm.meta_value LIKE %s
            ORDER BY p.ID ASC
            LIMIT 1",
            $like
        ));

        if ($existing) {
            $existing = (int) $existing;
            update_option($optionKey, $existing, false);

            return $existing;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploadDir = wp_upload_dir();
        if (!empty($uploadDir['error'])) {
            return new \WP_Error('pitea_dummy_upload_dir', $uploadDir['error']);
        }

        if (!wp_mkdir_p($uploadDir['path'])) {
            return new \WP_Error(
                'pitea_dummy_mkdir',
                __('Could not create upload directory for dummy images.', 'pitea-customisation')
            );
        }

        $destName = wp_unique_filename($uploadDir['path'], 'pitea-template-' . $basename);
        $destPath = $uploadDir['path'] . '/' . $destName;

        if (!copy($source, $destPath)) {
            return new \WP_Error(
                'pitea_dummy_copy',
                sprintf(
                    /* translators: %s: file name */
                    __('Could not copy dummy image %s into uploads.', 'pitea-customisation'),
                    $basename
                )
            );
        }

        $fileType = wp_check_filetype($destName, null);
        if (empty($fileType['type'])) {
            return new \WP_Error(
                'pitea_dummy_mime',
                sprintf(
                    /* translators: %s: file name */
                    __('Unknown file type for dummy image %s.', 'pitea-customisation'),
                    $basename
                )
            );
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => $fileType['type'],
                'post_title'     => sanitize_file_name(pathinfo($basename, PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_author'    => get_current_user_id() ?: 0,
            ],
            $destPath
        );

        if (is_wp_error($attachmentId)) {
            return $attachmentId;
        }

        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return new \WP_Error(
                'pitea_dummy_insert',
                __('Could not register dummy image in the media library.', 'pitea-customisation')
            );
        }

        $metadata = wp_generate_attachment_metadata($attachmentId, $destPath);
        wp_update_attachment_metadata($attachmentId, $metadata);
        update_option($optionKey, $attachmentId, false);

        return $attachmentId;
    }
}
