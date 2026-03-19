<?php

namespace PiteaCustomisation\Customisations\Admin;

class PostTemplates
{
    private const PAGE = 'pitea-create-navigation-page';

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
<!-- wp:acf/hero {"name":"acf/hero","data":{"custom_block_title":"","_custom_block_title":"field_block_title","mod_hero_display_as":"default","_mod_hero_display_as":"field_63ca5ed1394e1","mod_hero_byline":"","_mod_hero_byline":"field_614b3f1e6ed4a","mod_hero_meta":"","_mod_hero_meta":"field_63d78c4897632","mod_hero_body":"","_mod_hero_body":"field_614b3f5a6ed4b","mod_hero_background_type":"image","_mod_hero_background_type":"field_62c3f89f983b1","mod_hero_background_image":{"id":"469","top":"50","left":"50"},"_mod_hero_background_image":"field_614b3f786ed4c","mod_hero_size":"normal","_mod_hero_size":"field_614b43a186da4","lang":"auto","_lang":"field_636e42408367e"},"align":"full","mode":"edit"} /-->
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
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:image {"id":471,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="https://pitea.local/wp-content/uploads/2026/01/fd7450470648bc7be5d5371975a4b304c2af4324-2.png?_t=1773414694" alt="Kids fishing" class="wp-image-471"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%"><!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-12","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"1","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"Lorem ipsum","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum rubrik","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"https//test.com","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"Lorem Ipsum","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"0","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_image":"","_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs_0_custom_background_color":"--color-additional-4::#edf1e9","_manual_inputs_0_custom_background_color":"field_689b2cf733d44","manual_inputs":1,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:acf/container -->

<!-- wp:acf/spacer {"name":"acf/spacer","data":{"custom_block_title":"","_custom_block_title":"field_block_title","lang":"auto","_lang":"field_636e42408367e","space_amount":"8","_space_amount":"field_611d0016546f1"},"mode":"edit"} /-->

<!-- wp:acf/manualinput {"name":"acf/manualinput","data":{"custom_block_title":"","_custom_block_title":"field_block_title","display_as":"card","_display_as":"field_64ff23d0d91bf","display_as_conditional":"card","_display_as_conditional":"field_6752f959acfda","allow_user_modification":"0","_allow_user_modification":"field_67126c170c176","columns":"o-grid-4","_columns":"field_65001d039d4c4","highlight_first_input":"0","_highlight_first_input":"field_663372f4922a5","title_above_image":"0","_title_above_image":"field_68975344a0707","disable_resize_layout_shift":"0","_disable_resize_layout_shift":"field_689751b4887b6","use_custom_card_color":"0","_use_custom_card_color":"field_689b2ce333d43","manual_inputs_0_eyebrow":"","_manual_inputs_0_eyebrow":"field_6945264b7d66e","manual_inputs_0_title":"Lorem ipsum","_manual_inputs_0_title":"field_64ff22fdd91b8","manual_inputs_0_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_0_content":"field_64ff231ed91b9","manual_inputs_0_link":"#","_manual_inputs_0_link":"field_64ff232ad91ba","manual_inputs_0_link_text":"","_manual_inputs_0_link_text":"field_65002bce6d459","manual_inputs_0_show_link_as_button":"0","_manual_inputs_0_show_link_as_button":"field_69985fac063ef","manual_inputs_0_image":472,"_manual_inputs_0_image":"field_64ff2355d91bb","manual_inputs_0_box_icon":"","_manual_inputs_0_box_icon":"field_65293de2a26c7","manual_inputs_1_eyebrow":"","_manual_inputs_1_eyebrow":"field_6945264b7d66e","manual_inputs_1_title":"Lorem ipsum","_manual_inputs_1_title":"field_64ff22fdd91b8","manual_inputs_1_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_1_content":"field_64ff231ed91b9","manual_inputs_1_link":"#","_manual_inputs_1_link":"field_64ff232ad91ba","manual_inputs_1_link_text":"","_manual_inputs_1_link_text":"field_65002bce6d459","manual_inputs_1_show_link_as_button":"0","_manual_inputs_1_show_link_as_button":"field_69985fac063ef","manual_inputs_1_image":472,"_manual_inputs_1_image":"field_64ff2355d91bb","manual_inputs_1_box_icon":"","_manual_inputs_1_box_icon":"field_65293de2a26c7","manual_inputs_2_eyebrow":"","_manual_inputs_2_eyebrow":"field_6945264b7d66e","manual_inputs_2_title":"Lorem ipsum","_manual_inputs_2_title":"field_64ff22fdd91b8","manual_inputs_2_content":"Dolor sit amet, consectetur adipiscing elit. Vivamus imperdiet imperdiet leo, eu accumsan neque aliquam vitae. Integer egestas vulputate risus porttitor porta.","_manual_inputs_2_content":"field_64ff231ed91b9","manual_inputs_2_link":"#","_manual_inputs_2_link":"field_64ff232ad91ba","manual_inputs_2_link_text":"","_manual_inputs_2_link_text":"field_65002bce6d459","manual_inputs_2_show_link_as_button":"0","_manual_inputs_2_show_link_as_button":"field_69985fac063ef","manual_inputs_2_image":472,"_manual_inputs_2_image":"field_64ff2355d91bb","manual_inputs_2_box_icon":"","_manual_inputs_2_box_icon":"field_65293de2a26c7","manual_inputs":3,"_manual_inputs":"field_64ff22b2d91b7","lang":"auto","_lang":"field_636e42408367e"},"mode":"edit","metadata":{"name":"Modul: Puffar/inkastare undersida","patternName":"core/block/1858"}} /-->

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

        $post_id = wp_insert_post([
            'post_title'   => '(Navigationssida huvudområde)',
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
}
