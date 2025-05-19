<?php
/**
 * Plugin Name: 标签浏览量统计
 * Plugin URI: https://www.3520.net
 * Description: 统计WordPress标签的浏览量，重置标签浏览量，删除指定浏览量数值的标签。
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

// 获取被追踪的分类法，从选项中读取，并增加 filter 允许其他插件或主题修改
function tagstj_get_tracked_taxonomies() {
    $default_taxonomies = ['post_tag']; // 默认追踪 post_tag
    $selected_taxonomies = get_option('tagstj_selected_taxonomies', $default_taxonomies);
    // 确保返回的是数组
    if ( !is_array($selected_taxonomies) ) {
        $selected_taxonomies = $default_taxonomies;
    }
    return apply_filters( 'tagstj_tracked_taxonomies', $selected_taxonomies );
}

// 定义用于存储浏览量的 meta key
define( 'TAGSTJ_VIEWS_META_KEY', '_tagstj_views_count' );

/**
 * 增加标签浏览量
 */
function tagstj_increase_tag_view_count() {
    $tracked_taxonomies = tagstj_get_tracked_taxonomies();
    foreach ( $tracked_taxonomies as $taxonomy ) {
        if ( is_tax( $taxonomy ) || ( $taxonomy === 'post_tag' && is_tag() ) ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term && $term->taxonomy === $taxonomy ) {
                $count = get_term_meta( $term->term_id, TAGSTJ_VIEWS_META_KEY, true );
                $count = ! empty( $count ) ? absint( $count ) : 0;
                $count++;
                update_term_meta( $term->term_id, TAGSTJ_VIEWS_META_KEY, $count );
                break; // 一旦找到匹配的分类法并更新计数，就跳出循环
            }
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
// 为所有被追踪的分类法添加浏览量列
function tagstj_init_admin_columns() {
    $tracked_taxonomies = tagstj_get_tracked_taxonomies();
    foreach ( $tracked_taxonomies as $taxonomy ) {
        add_filter( "manage_edit-{$taxonomy}_columns", 'tagstj_add_views_column_to_tags_table' );
    }
}
add_action( 'admin_init', 'tagstj_init_admin_columns' );

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
// 为所有被追踪的分类法显示浏览量数据
function tagstj_init_admin_column_data() {
    $tracked_taxonomies = tagstj_get_tracked_taxonomies();
    foreach ( $tracked_taxonomies as $taxonomy ) {
        add_action( "manage_{$taxonomy}_custom_column", 'tagstj_display_views_in_tags_table', 10, 3 );
    }
}
add_action( 'admin_init', 'tagstj_init_admin_column_data' );

/**
 * 添加插件管理菜单
 */
function tagstj_add_admin_menu() {
    add_management_page(
        __( '标签浏览量统计管理', 'tagstj' ),
        __( '标签浏览量设置', 'tagstj' ),
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
    // 处理设置保存请求
    if ( isset( $_POST['tagstj_save_settings_nonce'] ) && wp_verify_nonce( $_POST['tagstj_save_settings_nonce'], 'tagstj_save_settings_action' ) ) {
        if ( isset( $_POST['tagstj_tracked_taxonomies'] ) && is_array( $_POST['tagstj_tracked_taxonomies'] ) ) {
            $selected_taxonomies = array_map( 'sanitize_text_field', $_POST['tagstj_tracked_taxonomies'] );
            update_option( 'tagstj_selected_taxonomies', $selected_taxonomies );
            echo '<div class="updated"><p>设置已保存。</p></div>';
        } else {
            // 如果没有选择任何分类法，则保存一个空数组或默认值
            update_option( 'tagstj_selected_taxonomies', ['post_tag'] ); // 或者 []
            echo '<div class="updated"><p>设置已保存（未选择任何分类法，将默认追踪文章标签）。</p></div>';
        }
    }

    // 处理重置所有标签浏览量的请求
    if ( isset( $_POST['tagstj_reset_views_nonce'] ) && wp_verify_nonce( $_POST['tagstj_reset_views_nonce'], 'tagstj_reset_views_action' ) ) {
        if ( isset( $_POST['tagstj_reset_all_views'] ) ) {
            tagstj_reset_all_tag_views();
            echo '<div class="updated"><p>所有已选分类法的项目浏览量已重置。</p></div>';
        }
    }

    // 处理删除指定浏览量标签的请求
    if ( isset( $_POST['tagstj_delete_views_nonce'] ) && wp_verify_nonce( $_POST['tagstj_delete_views_nonce'], 'tagstj_delete_views_action' ) ) {
        if ( isset( $_POST['tagstj_delete_view_tags'] ) ) {
            $view_threshold = isset( $_POST['tagstj_view_threshold'] ) ? intval( $_POST['tagstj_view_threshold'] ) : 0;
            $deleted_count = tagstj_delete_tags_by_view_count( $view_threshold );
            echo '<div class="updated"><p>成功删除了 ' . intval( $deleted_count ) . ' 个已选分类法下浏览量为 ' . $view_threshold . ' 的项目。</p></div>';
        }
    }

    $available_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
    $currently_tracked_taxonomies = tagstj_get_tracked_taxonomies(); // 获取当前实际追踪的（可能经过filter）
    $selected_taxonomies_option = get_option('tagstj_selected_taxonomies', ['post_tag']); // 获取选项中保存的

    ?>
    <div class="wrap">
        <h1>标签浏览量统计设置</h1>

        <form method="post" action="">
            <?php wp_nonce_field( 'tagstj_save_settings_action', 'tagstj_save_settings_nonce' ); ?>
            <h2>选择要追踪的分类法</h2>
            <p>请选择您希望统计和显示浏览量的分类法（模块）：</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">可用分类法</th>
                    <td>
                        <?php foreach ( $available_taxonomies as $taxonomy_slug => $taxonomy_obj ) : ?>
                            <?php 
                                // 对于层级分类法（如category），通常不作为“标签”使用，但为了通用性，这里列出所有public的
                                // 可以根据需要添加 $taxonomy_obj->hierarchical == false 的判断
                            ?>
                            <label style="margin-right: 20px;">
                                <input type="checkbox" name="tagstj_tracked_taxonomies[]" value="<?php echo esc_attr( $taxonomy_slug ); ?>" <?php checked( in_array( $taxonomy_slug, $selected_taxonomies_option ) ); ?>>
                                <?php echo esc_html( $taxonomy_obj->labels->name ); ?> (<code><?php echo esc_html( $taxonomy_slug ); ?></code>)
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description">选择后，插件将在这些分类法的管理页面显示“浏览量”列，并统计其下项目的浏览次数。</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="tagstj_save_settings" class="button button-primary" value="保存设置"></p>
        </form>

        <hr>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'tagstj_reset_views_action', 'tagstj_reset_views_nonce' ); ?>
            <h2>重置浏览量</h2>
            <p>此操作会将您在上方选择并保存的分类法下所有项目的浏览量重置为0。</p>
            <p><input type="submit" name="tagstj_reset_all_views" class="button button-secondary" value="重置所选分类法的浏览量" onclick="return confirm('确定要重置所选分类法下所有项目的浏览量吗？此操作不可撤销。');"></p>
        </form>

        <hr>

        <form method="post" action="">
            <?php wp_nonce_field( 'tagstj_delete_views_action', 'tagstj_delete_views_nonce' ); ?>
            <h2>删除指定浏览量的项目</h2>
            <p>此操作会删除您在上方选择并保存的分类法下，浏览量等于指定数字的项目。</p>
            <p>
                <label for="tagstj_view_threshold">要删除的浏览量值：</label>
                <input type="number" id="tagstj_view_threshold" name="tagstj_view_threshold" value="0" min="0" step="1" style="width: 100px;">
            </p>
            <p><input type="submit" name="tagstj_delete_view_tags" class="button button-danger" value="删除所选分类法的指定浏览量项目" onclick="return confirm('确定要删除所选分类法下所有浏览量等于您输入数字的项目吗？此操作不可撤销。');"></p>
        </form>
    </div>
    <?php
}

/**
 * 重置所有标签的浏览量
 */
function tagstj_reset_all_tag_views() {
    $tracked_taxonomies = tagstj_get_tracked_taxonomies();
    foreach ( $tracked_taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ) );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                delete_term_meta( $term->term_id, TAGSTJ_VIEWS_META_KEY );
            }
        }
    }
}

/**
 * 删除指定浏览量的标签
 *
 * @param int $view_threshold 要删除的浏览量阈值
 * @return int 删除的标签数量
 */
function tagstj_delete_tags_by_view_count( $view_threshold = 0 ) {
    $tracked_taxonomies = tagstj_get_tracked_taxonomies();
    $deleted_count = 0;
    foreach ( $tracked_taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ) );

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $views = tagstj_get_tag_view_count( $term->term_id );
                if ( $view_threshold === $views ) {
                    wp_delete_term( $term->term_id, $taxonomy );
                    $deleted_count++;
                }
            }
        }
    }
    return $deleted_count;
}



?>