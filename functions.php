<?php //子テーマ用関数
if (!defined('ABSPATH')) exit;

//子テーマ用のビジュアルエディタースタイルを適用
add_editor_style();

//以下に子テーマ用の関数を書く
// 会員登録画面からユーザー名を取り除く
add_filter( 'wpmem_register_form_rows', function( $rows ) {
    unset( $rows['username'] );
    return $rows;
});
// メールアドレスからユーザー名を作成する
add_filter( 'wpmem_pre_validate_form', function( $fields ) {
    $fields['username'] = $fields['user_email'];
    return $fields;
});

// WP-Members関連のエラーを抑制する関数
function suppress_wpmembers_errors() {
    // エラーハンドラー関数を定義
    function custom_error_handler($errno, $errstr, $errfile) {
        // WP-Membersプラグインのエラーを抑制
        if (strpos($errfile, 'wp-members') !== false || 
            strpos($errfile, 'email-as-username-for-wp-members') !== false) {
            // 特定のエラーメッセージのみを抑制
            if (strpos($errstr, 'Undefined array key') !== false) {
                return true; // エラーを抑制
            }
        }
        // その他のエラーは通常通り処理
        return false;
    }
    
    // エラーハンドラーを設定（警告と通知のみ）
    set_error_handler('custom_error_handler', E_WARNING | E_NOTICE);
}

// フロントエンド表示時のみ実行
if (!is_admin() && !defined('DOING_AJAX')) {
    add_action('init', 'suppress_wpmembers_errors', 1);
}


// タクソノミーの子ターム取得用Ajaxハンドラー
function get_taxonomy_children_ajax() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$parent_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms) || empty($terms)) {
        wp_send_json_error('子タームが見つかりませんでした');
    }
    
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
}
add_action('wp_ajax_get_taxonomy_children', 'get_taxonomy_children_ajax');
add_action('wp_ajax_nopriv_get_taxonomy_children', 'get_taxonomy_children_ajax');

// タームリンク取得用Ajaxハンドラー
function get_term_link_ajax() {
    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$term_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term($term_id, $taxonomy);
    
    if (is_wp_error($term) || empty($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_ajax');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_ajax');

// スラッグからタームリンク取得用Ajaxハンドラー
function get_term_link_by_slug_ajax() {
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$slug || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term_by('slug', $slug, $taxonomy);
    
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_ajax');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_ajax');


/* ------------------------------------------------------------------------------ 
	親カテゴリー・親タームを選択できないようにする
------------------------------------------------------------------------------ */
require_once(ABSPATH . '/wp-admin/includes/template.php');
class Nocheck_Category_Checklist extends Walker_Category_Checklist {

  function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
    extract($args);
    if ( empty( $taxonomy ) )
      $taxonomy = 'category';

    if ( $taxonomy == 'category' )
      $name = 'post_category';
    else
      $name = 'tax_input['.$taxonomy.']';

    $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
    $cat_child = get_term_children( $category->term_id, $taxonomy );

    if( !empty( $cat_child ) ) {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), true, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    } else {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    }
  }

}

/**
 * 求人検索のパスURLを処理するための関数
 */

/**
 * カスタムリライトルールを追加
 */
function job_search_rewrite_rules() {
    // 特徴のみのクエリパラメータ対応
    add_rewrite_rule(
        'jobs/features/?$',
        'index.php?post_type=job&job_features_only=1',
        'top'
    );
    
    // /jobs/location/tokyo/ のようなURLルール
    add_rewrite_rule(
        'jobs/location/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]',
        'top'
    );
    
    // /jobs/position/nurse/ のようなURLルール
    add_rewrite_rule(
        'jobs/position/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]',
        'top'
    );
    
    // /jobs/type/full-time/ のようなURLルール
    add_rewrite_rule(
        'jobs/type/([^/]+)/?$',
        'index.php?post_type=job&job_type=$matches[1]',
        'top'
    );
    
    // /jobs/facility/hospital/ のようなURLルール
    add_rewrite_rule(
        'jobs/facility/([^/]+)/?$',
        'index.php?post_type=job&facility_type=$matches[1]',
        'top'
    );
    
    // /jobs/feature/high-salary/ のようなURLルール
    add_rewrite_rule(
        'jobs/feature/([^/]+)/?$',
        'index.php?post_type=job&job_feature=$matches[1]',
        'top'
    );
    
    // 複合条件のURLルール
    
    // エリア + 職種
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]',
        'top'
    );
    
    // エリア + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // エリア + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // エリア + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // 職種 + 施設形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // 職種 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 三つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_feature=$matches[3]',
        'top'
    );
    
    // 追加: 四つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 雇用形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 職種 + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 追加: 五つの条件の組み合わせ（全条件）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]&job_feature=$matches[5]',
        'top'
    );
    
    // ページネーション対応（例：エリア + 職種の場合）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/page/([0-9]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&paged=$matches[3]',
        'top'
    );
    
    // 他のページネーションパターンも必要に応じて追加
}
add_action('init', 'job_search_rewrite_rules');

/**
 * クエリ変数を追加
 */
function job_search_query_vars($vars) {
    $vars[] = 'job_location';
    $vars[] = 'job_position';
    $vars[] = 'job_type';
    $vars[] = 'facility_type';
    $vars[] = 'job_feature';
    $vars[] = 'job_features_only'; // 追加: 特徴のみの検索フラグ
    return $vars;
}
add_filter('query_vars', 'job_search_query_vars');

/**
 * URLパスとクエリパラメータを解析してフィルター条件を取得する関数
 */
function get_job_filters_from_url() {
    $filters = array();
    
    // 特徴のみのフラグをチェック
    $features_only = get_query_var('job_features_only');
    if (!empty($features_only)) {
        $filters['features_only'] = true;
    }
    
    // パス型URLからの条件取得
    $location = get_query_var('job_location');
    if (!empty($location)) {
        $filters['location'] = $location;
    }
    
    $position = get_query_var('job_position');
    if (!empty($position)) {
        $filters['position'] = $position;
    }
    
    $job_type = get_query_var('job_type');
    if (!empty($job_type)) {
        $filters['type'] = $job_type;
    }
    
    $facility_type = get_query_var('facility_type');
    if (!empty($facility_type)) {
        $filters['facility'] = $facility_type;
    }
    
    // 単一の特徴（パス型URL用）
    $job_feature = get_query_var('job_feature');
    if (!empty($job_feature)) {
        $filters['feature'] = $job_feature;
    }
    
    // クエリパラメータからの複数特徴取得
    if (isset($_GET['features']) && is_array($_GET['features'])) {
        $filters['features'] = array_map('sanitize_text_field', $_GET['features']);
    }
    
    return $filters;
}

/**
 * 特定の特徴フィルターのみを削除した場合のURLを生成する関数
 */
function remove_feature_from_url($feature_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除する特徴を配列から除外
    if (!empty($feature_slugs)) {
        $feature_slugs = array_values(array_diff($feature_slugs, array($feature_to_remove)));
    }
    
    // 単一特徴のパラメータが一致する場合、それも削除
    if ($job_feature_slug === $feature_to_remove) {
        $job_feature_slug = '';
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($feature_slugs)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs)) {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // クエリパラメータの追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 特定のフィルターを削除した場合のURLを生成する関数
 */
function remove_filter_from_url($filter_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除するフィルターを処理
    switch ($filter_to_remove) {
        case 'location':
            $location_slug = '';
            break;
        case 'position':
            $position_slug = '';
            break;
        case 'type':
            $job_type_slug = '';
            break;
        case 'facility':
            $facility_type_slug = '';
            break;
        case 'feature':
            $job_feature_slug = '';
            $feature_slugs = array(); // 全特徴をクリア
            break;
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs) && $filter_to_remove !== 'feature') {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($query_params)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // クエリパラメータを追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 求人アーカイブページのメインクエリを変更する
 */
function modify_job_archive_query($query) {
    // メインクエリのみに適用
    if (!is_admin() && $query->is_main_query() && 
        (is_post_type_archive('job') || 
        is_tax('job_location') || 
        is_tax('job_position') || 
        is_tax('job_type') || 
        is_tax('facility_type') || 
        is_tax('job_feature'))) {
        
        // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
        $feature_slugs = isset($_GET['features']) && is_array($_GET['features']) ? $_GET['features'] : array();
        
        // 特徴（job_feature）のパラメータがある場合のみ処理
        if (!empty($feature_slugs)) {
            // 既存のtax_queryを取得（なければ新規作成）
            $tax_query = $query->get('tax_query');
            
            if (!is_array($tax_query)) {
                $tax_query = array();
            }
            
            // 特徴の条件を追加
            $tax_query[] = array(
                'taxonomy' => 'job_feature',
                'field'    => 'slug',
                'terms'    => $feature_slugs,
                'operator' => 'IN',
            );
            
            // 複数の条件がある場合はAND条件で結合
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            
            // 更新したtax_queryを設定
            $query->set('tax_query', $tax_query);
        }
        
        // 特徴のみのフラグがある場合（/jobs/features/ エンドポイント）
        if (get_query_var('job_features_only')) {
            // この場合、クエリパラメータの特徴のみでフィルタリング
            if (!empty($feature_slugs)) {
                $tax_query = array(
                    array(
                        'taxonomy' => 'job_feature',
                        'field'    => 'slug',
                        'terms'    => $feature_slugs,
                        'operator' => 'IN',
                    )
                );
                
                $query->set('tax_query', $tax_query);
            }
        }
    }
}
add_action('pre_get_posts', 'modify_job_archive_query');

/**
 * タクソノミーの子ターム取得用AJAX処理
 */
function get_taxonomy_children_callback() {
    // セキュリティチェック
    if (!isset($_POST['taxonomy']) || !isset($_POST['parent_id'])) {
        wp_send_json_error('Invalid request');
    }
    
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    $parent_id = intval($_POST['parent_id']);
    
    // 子タームを取得
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms)) {
        wp_send_json_error($terms->get_error_message());
    }
    
    // 結果を整形して返送
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
}
add_action('wp_ajax_get_taxonomy_children', 'get_taxonomy_children_callback');
add_action('wp_ajax_nopriv_get_taxonomy_children', 'get_taxonomy_children_callback');

/**
 * タームのURLを取得するAJAX処理
 */
function get_term_link_callback() {
    // セキュリティチェック
    if (!isset($_POST['term_id']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $term_id = intval($_POST['term_id']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term($term_id, $taxonomy);
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_callback');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_callback');

/**
 * スラッグからタームリンクを取得するAJAX処理
 */
function get_term_link_by_slug_callback() {
    // セキュリティチェック
    if (!isset($_POST['slug']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Term not found');
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_callback');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_callback');

/**
 * URLが変更されたときにリライトルールをフラッシュする
 */
function flush_rewrite_rules_on_theme_activation() {
    if (get_option('job_search_rewrite_rules_flushed') != '1') {
        flush_rewrite_rules();
        update_option('job_search_rewrite_rules_flushed', '1');
    }
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_theme_activation');

// リライトルールの強制フラッシュと再登録
function force_rewrite_rules_refresh() {
    // 初回読み込み時にのみ実行
    if (!get_option('force_rewrite_refresh_done')) {
        // リライトルールを追加
        job_search_rewrite_rules();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 実行済みフラグを設定
        update_option('force_rewrite_refresh_done', '1');
    }
}
add_action('init', 'force_rewrite_rules_refresh', 99);

// 特徴のみのリライトルールを追加した後にフラッシュする
function flush_features_rewrite_rules() {
    if (!get_option('job_features_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('job_features_rewrite_flushed', true);
    }
}
add_action('init', 'flush_features_rewrite_rules', 999);

// リライトルールのデバッグ（必要に応じて）
function debug_rewrite_rules() {
    if (current_user_can('manage_options') && isset($_GET['debug_rewrite'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->rules);
        echo '</pre>';
        exit;
    }
}
add_action('init', 'debug_rewrite_rules', 100);

// 以下のコードがfunctions.phpに追加されているか確認してください
function job_path_query_vars($vars) {
    $vars[] = 'job_path';
    return $vars;
}
add_filter('query_vars', 'job_path_query_vars');

// 求人ステータス変更・削除用のアクション処理
add_action('admin_post_draft_job', 'set_job_to_draft');
add_action('admin_post_publish_job', 'set_job_to_publish');
add_action('admin_post_delete_job', 'delete_job_post');

/**
 * 求人を下書きに変更
 */
function set_job_to_draft() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'draft_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job' || 
        ($job_post->post_author != get_current_user_id() && !current_user_can('administrator'))) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=drafted'));
    exit;
}

/**
 * 求人を公開に変更
 */
function set_job_to_publish() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'publish_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job' || 
        ($job_post->post_author != get_current_user_id() && !current_user_can('administrator'))) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=published'));
    exit;
}

/**
 * 求人を削除
 */
function delete_job_post() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job' || 
        ($job_post->post_author != get_current_user_id() && !current_user_can('administrator'))) {
        wp_die('この求人を削除する権限がありません。');
    }
    
    // 削除
    wp_trash_post($job_id);
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=deleted'));
    exit;
}



/**
 * 求人用カスタムフィールドとメタボックスの設定
 */

/**
 * 求人投稿のメタボックスを追加
 */
function add_job_meta_boxes() {
    add_meta_box(
        'job_details',
        '求人詳細情報',
        'render_job_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'facility_details',
        '施設情報',
        'render_facility_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'workplace_environment',
        '職場環境',
        'render_workplace_environment_meta_box',
        'job',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_meta_boxes');

/**
 * 求人詳細情報のメタボックスをレンダリング
 */
function render_job_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_job_details', 'job_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $salary_range = get_post_meta($post->ID, 'salary_range', true);
    $working_hours = get_post_meta($post->ID, 'working_hours', true);
    $holidays = get_post_meta($post->ID, 'holidays', true);
    $benefits = get_post_meta($post->ID, 'benefits', true);
    $requirements = get_post_meta($post->ID, 'requirements', true);
    $application_process = get_post_meta($post->ID, 'application_process', true);
    $contact_info = get_post_meta($post->ID, 'contact_info', true);
    $bonus_raise = get_post_meta($post->ID, 'bonus_raise', true);
    
    // フォームを表示
    ?>
    <style>
        .job-form-row {
            margin-bottom: 15px;
        }
        .job-form-row label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .job-form-row input[type="text"],
        .job-form-row textarea {
            width: 100%;
        }
        .required {
            color: #f00;
        }
    </style>
    
    <div class="job-form-row">
        <label for="salary_range">給与範囲 <span class="required">*</span></label>
        <input type="text" id="salary_range" name="salary_range" value="<?php echo esc_attr($salary_range); ?>" required>
        <p class="description">例: 月給180,000円〜250,000円</p>
    </div>
    
    <div class="job-form-row">
        <label for="working_hours">勤務時間 <span class="required">*</span></label>
        <input type="text" id="working_hours" name="working_hours" value="<?php echo esc_attr($working_hours); ?>" required>
        <p class="description">例: 9:00〜18:00（休憩60分）</p>
    </div>
    
    <div class="job-form-row">
        <label for="holidays">休日・休暇 <span class="required">*</span></label>
        <input type="text" id="holidays" name="holidays" value="<?php echo esc_attr($holidays); ?>" required>
        <p class="description">例: 土日祝、年末年始、有給休暇あり</p>
    </div>
    
    <div class="job-form-row">
        <label for="benefits">福利厚生</label>
        <textarea id="benefits" name="benefits" rows="4"><?php echo esc_textarea($benefits); ?></textarea>
        <p class="description">社会保険、交通費支給、各種手当など</p>
    </div>
    
    <div class="job-form-row">
        <label for="bonus_raise">昇給・賞与</label>
        <textarea id="bonus_raise" name="bonus_raise" rows="4"><?php echo esc_textarea($bonus_raise); ?></textarea>
        <p class="description">昇給制度や賞与の詳細など</p>
    </div>
    
    <div class="job-form-row">
        <label for="requirements">応募要件</label>
        <textarea id="requirements" name="requirements" rows="4"><?php echo esc_textarea($requirements); ?></textarea>
        <p class="description">必要な資格や経験など</p>
    </div>
    
    <div class="job-form-row">
        <label for="application_process">選考プロセス</label>
        <textarea id="application_process" name="application_process" rows="4"><?php echo esc_textarea($application_process); ?></textarea>
        <p class="description">書類選考、面接回数など</p>
    </div>
    
    <div class="job-form-row">
        <label for="contact_info">応募方法・連絡先 <span class="required">*</span></label>
        <textarea id="contact_info" name="contact_info" rows="4" required><?php echo esc_textarea($contact_info); ?></textarea>
        <p class="description">電話番号、メールアドレス、応募フォームURLなど</p>
    </div>
    <?php
}

/**
 * 施設情報のメタボックスをレンダリング
 */
function render_facility_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_facility_details', 'facility_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $facility_name = get_post_meta($post->ID, 'facility_name', true);
    $facility_address = get_post_meta($post->ID, 'facility_address', true);
    $facility_tel = get_post_meta($post->ID, 'facility_tel', true);
    $facility_hours = get_post_meta($post->ID, 'facility_hours', true);
    $facility_url = get_post_meta($post->ID, 'facility_url', true);
    $facility_company = get_post_meta($post->ID, 'facility_company', true);
    $capacity = get_post_meta($post->ID, 'capacity', true);
    $staff_composition = get_post_meta($post->ID, 'staff_composition', true);
    
    // フォームを表示
    ?>
    <div class="job-form-row">
        <label for="facility_name">施設名 <span class="required">*</span></label>
        <input type="text" id="facility_name" name="facility_name" value="<?php echo esc_attr($facility_name); ?>" required>
    </div>
    
    <div class="job-form-row">
        <label for="facility_company">運営会社名</label>
        <input type="text" id="facility_company" name="facility_company" value="<?php echo esc_attr($facility_company); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_address">施設住所 <span class="required">*</span></label>
        <input type="text" id="facility_address" name="facility_address" value="<?php echo esc_attr($facility_address); ?>" required>
        <p class="description">例: 〒123-4567 神奈川県横浜市○○区△△町1-2-3</p>
    </div>
    
    <div class="job-form-row">
        <label for="capacity">利用者定員数</label>
        <input type="text" id="capacity" name="capacity" value="<?php echo esc_attr($capacity); ?>">
        <p class="description">例: 60名（0〜5歳児）</p>
    </div>
    
    <div class="job-form-row">
        <label for="staff_composition">スタッフ構成</label>
        <textarea id="staff_composition" name="staff_composition" rows="4"><?php echo esc_textarea($staff_composition); ?></textarea>
        <p class="description">例: 園長1名、主任保育士2名、保育士12名、栄養士2名、調理員3名、事務員1名</p>
    </div>
    
    <div class="job-form-row">
        <label for="facility_tel">施設電話番号</label>
        <input type="text" id="facility_tel" name="facility_tel" value="<?php echo esc_attr($facility_tel); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_hours">施設営業時間</label>
        <input type="text" id="facility_hours" name="facility_hours" value="<?php echo esc_attr($facility_hours); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_url">施設WebサイトURL</label>
        <input type="url" id="facility_url" name="facility_url" value="<?php echo esc_url($facility_url); ?>">
    </div>
    <?php
}

/**
 * 職場環境のメタボックスをレンダリング
 */
function render_workplace_environment_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_workplace_environment', 'workplace_environment_nonce');
    
    // 現在のカスタムフィールド値を取得
    $daily_schedule = get_post_meta($post->ID, 'daily_schedule', true);
    $staff_voices = get_post_meta($post->ID, 'staff_voices', true);
    
    // フォームを表示
    ?>
    <div class="job-form-row">
        <label for="daily_schedule">仕事の一日の流れ</label>
        <textarea id="daily_schedule" name="daily_schedule" rows="8"><?php echo esc_textarea($daily_schedule); ?></textarea>
        <p class="description">例：9:00 出勤・朝礼、9:30 午前の業務開始、12:00 お昼休憩 など時間ごとの業務内容</p>
    </div>
    
    <div class="job-form-row">
        <label for="staff_voices">職員の声</label>
        <textarea id="staff_voices" name="staff_voices" rows="8"><?php echo esc_textarea($staff_voices); ?></textarea>
        <p class="description">実際に働いているスタッフの声を入力（職種、勤続年数、コメントなど）</p>
    </div>
    <?php
}

/**
 * カスタムフィールドのデータを保存
 */
function save_job_meta_data($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 求人詳細情報の保存
    if (isset($_POST['job_details_nonce']) && wp_verify_nonce($_POST['job_details_nonce'], 'save_job_details')) {
        if (isset($_POST['salary_range'])) {
            update_post_meta($post_id, 'salary_range', sanitize_text_field($_POST['salary_range']));
        }
        
        if (isset($_POST['working_hours'])) {
            update_post_meta($post_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        }
        
        if (isset($_POST['holidays'])) {
            update_post_meta($post_id, 'holidays', sanitize_text_field($_POST['holidays']));
        }
        
        if (isset($_POST['benefits'])) {
            update_post_meta($post_id, 'benefits', wp_kses_post($_POST['benefits']));
        }
        
        if (isset($_POST['bonus_raise'])) {
            update_post_meta($post_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        }
        
        if (isset($_POST['requirements'])) {
            update_post_meta($post_id, 'requirements', wp_kses_post($_POST['requirements']));
        }
        
        if (isset($_POST['application_process'])) {
            update_post_meta($post_id, 'application_process', wp_kses_post($_POST['application_process']));
        }
        
        if (isset($_POST['contact_info'])) {
            update_post_meta($post_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        }
    }
    
    // 施設情報の保存
    if (isset($_POST['facility_details_nonce']) && wp_verify_nonce($_POST['facility_details_nonce'], 'save_facility_details')) {
        if (isset($_POST['facility_name'])) {
            update_post_meta($post_id, 'facility_name', sanitize_text_field($_POST['facility_name']));
        }
        
        if (isset($_POST['facility_company'])) {
            update_post_meta($post_id, 'facility_company', sanitize_text_field($_POST['facility_company']));
        }
        
        if (isset($_POST['facility_address'])) {
            update_post_meta($post_id, 'facility_address', sanitize_text_field($_POST['facility_address']));
        }
        
        if (isset($_POST['capacity'])) {
            update_post_meta($post_id, 'capacity', sanitize_text_field($_POST['capacity']));
        }
        
        if (isset($_POST['staff_composition'])) {
            update_post_meta($post_id, 'staff_composition', wp_kses_post($_POST['staff_composition']));
        }
        
        if (isset($_POST['facility_tel'])) {
            update_post_meta($post_id, 'facility_tel', sanitize_text_field($_POST['facility_tel']));
        }
        
        if (isset($_POST['facility_hours'])) {
            update_post_meta($post_id, 'facility_hours', sanitize_text_field($_POST['facility_hours']));
        }
        
        if (isset($_POST['facility_url'])) {
            update_post_meta($post_id, 'facility_url', esc_url_raw($_POST['facility_url']));
        }
    }
    
    // 職場環境の保存
    if (isset($_POST['workplace_environment_nonce']) && wp_verify_nonce($_POST['workplace_environment_nonce'], 'save_workplace_environment')) {
        if (isset($_POST['daily_schedule'])) {
            update_post_meta($post_id, 'daily_schedule', wp_kses_post($_POST['daily_schedule']));
        }
        
        if (isset($_POST['staff_voices'])) {
            update_post_meta($post_id, 'staff_voices', wp_kses_post($_POST['staff_voices']));
        }
    }
}
add_action('save_post_job', 'save_job_meta_data');

// 追加のカスタムフィールドを設定
function add_additional_job_fields($post_id) {
    // 本文タイトル
    if (isset($_POST['job_content_title'])) {
        update_post_meta($post_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
    }
    
    // GoogleMap埋め込みコード
    if (isset($_POST['facility_map'])) {
        update_post_meta($post_id, 'facility_map', wp_kses($_POST['facility_map'], array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array()
            )
        )));
    }
    
    // 仕事の一日の流れ（配列形式）
    if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
        $schedule_items = array();
        $count = count($_POST['daily_schedule_time']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['daily_schedule_time'][$i])) {
                $schedule_items[] = array(
                    'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                    'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                    'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    // 職員の声（配列形式）
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) {
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}

// 求人投稿保存時にカスタムフィールドを処理
add_action('save_post_job', function($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 追加フィールドを保存
    add_additional_job_fields($post_id);
}, 15);


// JavaScriptとCSSを登録・読み込むための関数
function register_job_search_scripts() {
    // URLパラメータを追加して、キャッシュを防止
    $version = '1.0.0';
    
    // スタイルシートの登録（必要に応じて）
    wp_register_style('job-search-style', get_stylesheet_directory_uri() . '/css/job-search.css', array(), $version);
    wp_enqueue_style('job-search-style');
    
    // JavaScriptの登録
    wp_register_script('job-search', get_stylesheet_directory_uri() . '/js/job-search.js', array('jquery'), $version, true);
    
    // JavaScriptにパラメータを渡す
    wp_localize_script('job-search', 'job_search_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'site_url' => home_url(),
        'nonce' => wp_create_nonce('job_search_nonce')
    ));
    
    // JavaScriptを読み込む
    wp_enqueue_script('job-search');
}
add_action('wp_enqueue_scripts', 'register_job_search_scripts');



/**
 * 退会処理の実装
 */

// 退会処理のアクションフックを追加
add_action('admin_post_delete_my_account', 'handle_delete_account');

/**
 * ユーザーアカウント削除処理
 */
function handle_delete_account() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }
    
    // nonceチェック
    if (!isset($_POST['delete_account_nonce']) || !wp_verify_nonce($_POST['delete_account_nonce'], 'delete_account_action')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 退会確認チェックボックスが選択されているか確認
    if (!isset($_POST['confirm_deletion'])) {
        wp_redirect(add_query_arg('error', 'no_confirmation', home_url('/withdrawal/')));
        exit;
    }
    
    // 現在のユーザー情報を取得
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_name = $current_user->display_name;
    $user_id = $current_user->ID;
    
    // 退会完了メールを送信
    send_account_deletion_email($user_email, $user_name);
    
    // ユーザーをログアウト
    wp_logout();
    
    // ユーザーアカウントを削除
    // WP-Membersのユーザー削除APIがあれば使用する
    if (function_exists('wpmem_delete_user')) {
        wpmem_delete_user($user_id);
    } else {
        // WP標準のユーザー削除機能を使用
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
    }
    
    // 退会完了ページへリダイレクト
    wp_redirect(home_url('/?account_deleted=true'));
    exit;
}

/**
 * 退会完了メールを送信する
 *
 * @param string $user_email 退会するユーザーのメールアドレス
 * @param string $user_name  退会するユーザーの表示名
 */
function send_account_deletion_email($user_email, $user_name) {
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // メールの件名
    $subject = sprintf('[%s] 退会手続き完了のお知らせ', $site_name);
    
    // メールの本文
    $message = sprintf(
        '%s 様
        
退会手続きが完了しました。

%s をご利用いただき、誠にありがとうございました。
アカウント情報および関連データはすべて削除されました。

またのご利用をお待ちしております。

------------------------------
%s
%s',
        $user_name,
        $site_name,
        $site_name,
        home_url()
    );
    
    // メールヘッダー
    $headers = array(
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Content-Type: text/plain; charset=UTF-8'
    );
    
    // メール送信
    wp_mail($user_email, $subject, $message, $headers);
    
    // 管理者にも通知
    $admin_subject = sprintf('[%s] ユーザー退会通知', $site_name);
    $admin_message = sprintf(
        '以下のユーザーが退会しました:
        
ユーザー名: %s
メールアドレス: %s
退会日時: %s',
        $user_name,
        $user_email,
        current_time('Y-m-d H:i:s')
    );
    
    wp_mail($admin_email, $admin_subject, $admin_message, $headers);
}

/**
 * トップページに退会完了メッセージを表示
 */
function show_account_deleted_message() {
    if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === 'true') {
        echo '<div class="account-deleted-message">';
        echo '<p><strong>退会手続きが完了しました。ご利用ありがとうございました。</strong></p>';
        echo '</div>';
        
        // スタイルを追加
        echo '<style>
        .account-deleted-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        </style>';
    }
}
add_action('wp_body_open', 'show_account_deleted_message');




/**
 * WordPressログイン画面とパスワードリセット画面のカスタマイズ
 */

// ログイン画面に独自のスタイルを適用
add_action('login_enqueue_scripts', 'custom_login_styles');

function custom_login_styles() {
    ?>
    <style type="text/css">
        /* 全体のスタイル */
        body.login {
            background-color: #f8f9fa;
            font-family: 'Helvetica Neue', Arial, sans-serif !important;
        }
        
        /* WordPressロゴを非表示 */
        #login h1 a {
            display: none;
        }
        
        /* フォーム全体の調整 */
        #login {
            width: 400px;
            padding: 5% 0 0;
        }
        
        /* 見出しを追加 */
        #login:before {
            content: "パスワード再設定";
            display: block;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        
        /* フォームのスタイル */
        .login form {
            margin-top: 20px;
            padding: 26px 24px 34px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* ラベルとフォーム要素 */
        .login label {
            font-size: 14px;
            color: #333;
            font-weight: bold;
        }
        
        .login form .input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            margin: 5px 0 15px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* ボタンスタイル */
        .login .button-primary {
            background-color: #0073aa;
            border-color: #0073aa;
            color: white;
            width: 100%;
            padding: 10px;
            text-shadow: none;
            box-shadow: none;
            border-radius: 4px;
            font-size: 16px;
            height: auto;
            line-height: normal;
            text-transform: none;
        }
        
        .login .button-primary:hover {
            background-color: #005f8a;
            border-color: #005f8a;
        }
        
        /* リンクのスタイル */
        #nav, #backtoblog {
            text-align: center;
            margin: 16px 0 0;
            font-size: 14px;
        }
        
        #nav a, #backtoblog a {
            color: #0073aa;
            text-decoration: none;
        }
        
        #nav a:hover, #backtoblog a:hover {
            color: #005f8a;
            text-decoration: underline;
        }
        
        /* メッセージスタイル */
        .login .message,
        .login #login_error {
            border-radius: 4px;
        }
        
        /* 余計な要素を非表示 */
        .login .privacy-policy-page-link {
            display: none;
        }
        
        /* パスワード強度インジケータを非表示 */
        .pw-weak {
            display: none !important;
        }
        
        /* パスワードリセット画面専用のスタイル */
        body.login-action-rp form p:first-child,
        body.login-action-resetpass form p:first-child {
            font-size: 14px;
            color: #333;
        }
        
        /* 文言を日本語化（CSSのcontentで置き換え） */
        body.login-action-lostpassword form p:first-child {
            display: none;  /* 元のテキストを非表示 */
        }
        
        body.login-action-lostpassword form:before {
            content: "メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            color: #333;
        }
        
        body.login-action-rp form:before,
        body.login-action-resetpass form:before {
            content: "新しいパスワードを設定してください。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
    </style>
    <?php
}

// ログイン画面のタイトルを変更
add_filter('login_title', 'custom_login_title', 10, 2);

function custom_login_title($title, $url) {
    if (isset($_GET['action']) && $_GET['action'] == 'lostpassword') {
        return 'パスワード再設定 | ' . get_bloginfo('name');
    } elseif (isset($_GET['action']) && ($_GET['action'] == 'rp' || $_GET['action'] == 'resetpass')) {
        return '新しいパスワードの設定 | ' . get_bloginfo('name');
    }
    return $title;
}

// ログイン画面のテキストを日本語化
add_filter('gettext', 'custom_login_text', 20, 3);

function custom_login_text($translated_text, $text, $domain) {
    if ($domain == 'default') {
        switch ($text) {
            // パスワードリセット関連
            case 'Enter your username or email address and you will receive a link to create a new password via email.':
                $translated_text = 'メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。';
                break;
            case 'Username or Email Address':
                $translated_text = 'メールアドレス';
                break;
            case 'Get New Password':
                $translated_text = 'パスワード再設定メールを送信';
                break;
            case 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.':
                $translated_text = 'パスワード再設定用のメールを送信しました。メールが届くまで数分かかる場合があります。10分以上経ってもメールが届かない場合は、再度試してください。';
                break;
            case 'There is no account with that username or email address.':
                $translated_text = '入力されたメールアドレスのアカウントが見つかりません。';
                break;
            
            // パスワード設定画面関連
            case 'Enter your new password below or generate one.':
            case 'Enter your new password below.':
                $translated_text = '新しいパスワードを入力してください。';
                break;
            case 'New password':
                $translated_text = '新しいパスワード';
                break;
            case 'Confirm new password':
                $translated_text = '新しいパスワード（確認）';
                break;
            case 'Reset Password':
                $translated_text = 'パスワードを変更';
                break;
            case 'Your password has been reset. <a href="%s">Log in</a>':
                $translated_text = 'パスワードが変更されました。<a href="%s">ログイン</a>してください。';
                break;
            
            // その他のリンク
            case 'Log in':
                $translated_text = 'ログイン';
                break;
            case '&larr; Back to %s':
                $translated_text = 'トップページに戻る';
                break;
        }
    }
    return $translated_text;
}

// パスワードリセットメールのカスタマイズ
add_filter('retrieve_password_message', 'custom_password_reset_email', 10, 4);
add_filter('retrieve_password_title', 'custom_password_reset_email_title', 10, 1);

function custom_password_reset_email_title($title) {
    $site_name = get_bloginfo('name');
    return '[' . $site_name . '] パスワード再設定のご案内';
}

function custom_password_reset_email($message, $key, $user_login, $user_data) {
    $site_name = get_bloginfo('name');
    
    // リセットURL
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    
    // メール本文
    $message = $user_data->display_name . " 様\r\n\r\n";
    $message .= "パスワード再設定のリクエストを受け付けました。\r\n\r\n";
    $message .= "以下のリンクをクリックして、新しいパスワードを設定してください：\r\n";
    $message .= $reset_url . "\r\n\r\n";
    $message .= "このリンクは24時間のみ有効です。\r\n\r\n";
    $message .= "リクエストに心当たりがない場合は、このメールを無視してください。\r\n\r\n";
    $message .= "------------------------------\r\n";
    $message .= $site_name . "\r\n";
    
    return $message;
}

// パスワード変更後のリダイレクト先を変更
add_action('login_form_resetpass', 'redirect_after_password_reset');

function redirect_after_password_reset() {
    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        add_filter('login_redirect', 'custom_password_reset_redirect', 10, 3);
    }
}

function custom_password_reset_redirect($redirect_to, $requested_redirect_to, $user) {
    return home_url('/login/?password-reset=success');
}