<?php
/**
 * Plugin Name: 标签浏览量统计
 * Plugin URI: https://www.3520.net
 * Description: 统计WordPress标签的浏览量，并提供管理功能。
 * Version: 1.0.0
 * Author: Trae AI
 * Author URI: https://www.3520.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tagstj
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 后续插件代码将在这里添加

// 定义用于存储浏览量的 meta key
define( 'TAGSTJ_VIEWS_META_KEY', '_tagstj_views_count' );

/**
 * 增加标签浏览量
 */
function tagstj_increase_tag_view_count() {
    if ( is_tag() ) {
        $term = get_queried_object();
        if ( $term instanceof WP_Term && $term->taxonomy === 'post_tag' ) {
            $count = get_term_meta( $term->term_id, TAGSTJ_VIEWS_META_KEY, true );
            $count = ! empty( $count ) ? absint( $count ) : 0;
            $count++;
            update_term_meta( $term->term_id, TAGSTJ_VIEWS_META_KEY, $count );
        }
    }
}
add_action( 'wp_head', 'tagstj_increase_tag_view_count' );

/**
 * 获取标签浏览量
 *
 * @param int $term_id 标签ID
 * @return int 浏览量
 */
function tagstj_get_tag_view_count( $term_id ) {
    $count = get_term_meta( $term_id, TAGSTJ_VIEWS_META_KEY, true );
    return ! empty( $count ) ? absint( $count ) : 0;
}

/**
 * 在标签管理页面添加浏览量列
 *
 * @param array $columns
 * @return array
 */
function tagstj_add_views_column_to_tags_table( $columns ) {
    $columns['tag_views'] = __( '浏览量', 'tagstj' );
    return $columns;
}
add_filter( 'manage_edit-post_tag_columns', 'tagstj_add_views_column_to_tags_table' );

/**
 * 显示标签管理页面的浏览量数据
 *
 * @param string $deprecated
 * @param string $column_name
 * @param int    $term_id
 */
function tagstj_display_views_in_tags_table( $deprecated, $column_name, $term_id ) {
    if ( 'tag_views' === $column_name ) {
        echo esc_html( tagstj_get_tag_view_count( $term_id ) );
    }
}
add_action( 'manage_post_tag_custom_column', 'tagstj_display_views_in_tags_table', 10, 3 );

/**
 * 添加插件管理菜单
 */
function tagstj_add_admin_menu() {
    add_management_page(
        __( '标签浏览量统计管理', 'tagstj' ),
        __( '标签浏览量', 'tagstj' ),
        'manage_options',
        'tagstj-settings',
        'tagstj_render_settings_page'
    );
}
add_action( 'admin_menu', 'tagstj_add_admin_menu' );

/**
 * 渲染插件设置页面
 */
function tagstj_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // 处理重置请求
    if ( isset( $_POST['tagstj_reset_views_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['tagstj_reset_views_nonce'] ), 'tagstj_reset_views' ) ) {
        tagstj_reset_all_tag_views();
        echo '<div class="updated"><p>' . esc_html__( '所有标签的浏览量已重置。', 'tagstj' ) . '</p></div>';
    }

    // 处理删除无浏览量标签请求
    if ( isset( $_POST['tagstj_delete_zero_view_tags_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['tagstj_delete_zero_view_tags_nonce'] ), 'tagstj_delete_zero_view_tags' ) ) {
        $deleted_count = tagstj_delete_zero_view_tags();
        echo '<div class="updated"><p>' . sprintf( esc_html__( '%d 个没有浏览量的标签已被删除。', 'tagstj' ), esc_html( $deleted_count ) ) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'tagstj_reset_views', 'tagstj_reset_views_nonce' ); ?>
            <p>
                <input type="submit" name="tagstj_reset_views" class="button button-primary" value="<?php esc_attr_e( '重置所有标签浏览量', 'tagstj' ); ?>">
            </p>
            <p class="description">
                <?php esc_html_e( '此操作将删除所有标签的浏览量统计数据。', 'tagstj' ); ?>
            </p>
        </form>
        <hr>
        <form method="post" action="">
            <?php wp_nonce_field( 'tagstj_delete_zero_view_tags', 'tagstj_delete_zero_view_tags_nonce' ); ?>
            <p>
                <input type="submit" name="tagstj_delete_zero_view_tags" class="button button-danger" value="<?php esc_attr_e( '删除没有浏览量的标签', 'tagstj' ); ?>">
            </p>
            <p class="description">
                <?php esc_html_e( '此操作将删除所有浏览量为0的标签。请谨慎操作！', 'tagstj' ); ?>
            </p>
        </form>
    </div>
    <?php
}

/**
 * 重置所有标签的浏览量
 */
function tagstj_reset_all_tag_views() {
    $tags = get_terms( array(
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
    ) );

    if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
        foreach ( $tags as $tag ) {
            delete_term_meta( $tag->term_id, TAGSTJ_VIEWS_META_KEY );
        }
    }
}

/**
 * 删除没有浏览量的标签
 *
 * @return int 删除的标签数量
 */
function tagstj_delete_zero_view_tags() {
    $tags = get_terms( array(
        'taxonomy'   => 'post_tag',
        'hide_empty' => false,
    ) );

    $deleted_count = 0;
    if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
        foreach ( $tags as $tag ) {
            $views = tagstj_get_tag_view_count( $tag->term_id );
            if ( 0 === $views ) {
                wp_delete_term( $tag->term_id, 'post_tag' );
                $deleted_count++;
            }
        }
    }
    return $deleted_count;
}



?>