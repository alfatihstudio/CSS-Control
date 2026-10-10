<?php
/**
 * Plugin Name: Lalangs CSS Control
 * Description: Per-page Critical CSS and selective asynchronous stylesheet delivery with administrator preview and inspection.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Lalangs_CSS_Control {
    const OPTION = 'lalangs_css_control_rules';
    const NONCE = 'lalangs_css_control_preview';
    private static $deferred = array();

    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_post_lalangs_css_save', array( __CLASS__, 'save' ) );
        add_action( 'template_redirect', array( __CLASS__, 'preview_headers' ), 0 );
        add_action( 'wp_head', array( __CLASS__, 'critical_css' ), 7 );
        add_filter( 'style_loader_tag', array( __CLASS__, 'style_tag' ), 999, 4 );
        add_action( 'wp_footer', array( __CLASS__, 'inspector' ), 999 );
    }

    private static function rules() {
        $rules = get_option( self::OPTION, array() );
        return is_array( $rules ) ? $rules : array();
    }

    private static function default_rule( $key ) {
        $page_id = 'home' === $key ? (int) get_option( 'page_on_front' ) : (int) $key;
        return array(
            'enabled' => false,
            'critical_css' => '',
            'handles' => array(
                'kinsley-style', 'bootstrap', 'kinsley-dark',
                'elementor-frontend', 'elementor-post-9',
                'elementor-post-275', 'elementor-post-831', 'elementor-post-' . $page_id,
            ),
        );
    }

    private static function get_rule( $key ) {
        $rules = self::rules();
        $rule = isset( $rules[ $key ] ) && is_array( $rules[ $key ] ) ? $rules[ $key ] : array();
        $rule = array_merge( self::default_rule( $key ), $rule );
        $rule['critical_css'] = is_string( $rule['critical_css'] ) ? $rule['critical_css'] : '';
        $rule['handles'] = is_array( $rule['handles'] ) ? $rule['handles'] : array();
        return $rule;
    }

    private static function authorized_tools() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['lalangs_css_nonce'] ) || ! is_string( $_GET['lalangs_css_nonce'] ) ) {
            return false;
        }
        return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['lalangs_css_nonce'] ) ), self::NONCE );
    }

    private static function excluded_request() {
        return is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_preview() ||
            is_customize_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
            isset( $_GET['elementor-preview'] );
    }

    private static function current_key() {
        if ( is_front_page() ) {
            return 'home';
        }
        return is_page() ? (string) get_queried_object_id() : '';
    }

    private static function css_allowed( $css ) {
        return strlen( $css ) <= 153600 &&
            ! preg_match( '~</?\s*(?:style|script)\b|@import\b~i', $css );
    }

    private static function active_rule() {
        if ( self::excluded_request() ) {
            return false;
        }
        $key = self::current_key();
        if ( '' === $key ) {
            return false;
        }
        $rule = self::get_rule( $key );
        $preview = self::authorized_tools() && isset( $_GET['lalangs_css_preview'] );
        if ( ! $preview && empty( $rule['enabled'] ) ) {
            return false;
        }
        if ( '' === trim( $rule['critical_css'] ) || ! self::css_allowed( $rule['critical_css'] ) ) {
            return false;
        }
        return $rule;
    }

    public static function preview_headers() {
        if ( self::authorized_tools() && ( isset( $_GET['lalangs_css_preview'] ) || isset( $_GET['lalangs_css_inspect'] ) ) ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
            nocache_headers();
            do_action( 'litespeed_control_set_nocache', 'Lalangs CSS administrator preview' );
        }
    }

    public static function critical_css() {
        $rule = self::active_rule();
        if ( ! $rule ) {
            return;
        }
        // Validated administrator-supplied CSS. Closing style/script tags are rejected.
        echo '<style id="lalangs-critical-css">' . $rule['critical_css'] . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function style_tag( $tag, $handle, $href = '', $media = 'all' ) {
        $rule = self::active_rule();
        if ( ! $rule || ! in_array( $handle, $rule['handles'], true ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
            return $tag;
        }

        $processor = new WP_HTML_Tag_Processor( $tag );
        if ( ! $processor->next_tag( 'LINK' ) ) {
            return $tag;
        }
        $rel = strtolower( (string) $processor->get_attribute( 'rel' ) );
        if ( 'stylesheet' !== $rel || null !== $processor->get_attribute( 'onload' ) || null !== $processor->get_attribute( 'onerror' ) || null !== $processor->get_attribute( 'data-no-async' ) ) {
            return $tag;
        }
        $url = (string) $processor->get_attribute( 'href' );
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( $host && ! in_array( strtolower( $host ), array( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ) ), true ) ) {
            return $tag;
        }

        $original_media = $processor->get_attribute( 'media' );
        $original_media = is_string( $original_media ) && '' !== $original_media ? $original_media : 'all';
        if ( 'print' === strtolower( trim( $original_media ) ) ) {
            return $tag;
        }
        $processor->set_attribute( 'media', 'print' );
        $processor->set_attribute( 'data-lalangs-media', $original_media );
        $processor->set_attribute( 'data-lalangs-async', '1' );
        $processor->set_attribute( 'onload', "this.media=this.getAttribute('data-lalangs-media');this.onload=null;" );
        $processor->set_attribute( 'onerror', "this.media=this.getAttribute('data-lalangs-media');this.onerror=null;" );

        // The fallback retains all stylesheet attributes except ID to avoid duplicate IDs.
        $fallback = new WP_HTML_Tag_Processor( $tag );
        $fallback->next_tag( 'LINK' );
        $fallback->remove_attribute( 'id' );
        self::$deferred[ $handle ] = true;
        return $processor->get_updated_html() . '<noscript>' . $fallback->get_updated_html() . '</noscript>';
    }

    public static function admin_menu() {
        add_options_page( 'Lalangs CSS Control', 'Lalangs CSS Control', 'manage_options', 'lalangs-css-control', array( __CLASS__, 'settings' ) );
    }

    private static function edit_key() {
        if ( empty( $_GET['rule'] ) || ! is_string( $_GET['rule'] ) || 'home' === $_GET['rule'] ) {
            return 'home';
        }
        $id = absint( $_GET['rule'] );
        return $id && 'page' === get_post_type( $id ) ? (string) $id : 'home';
    }

    public static function settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $key = self::edit_key();
        $rule = self::get_rule( $key );
        $url = 'home' === $key ? home_url( '/' ) : get_permalink( (int) $key );
        $preview = add_query_arg( array( 'lalangs_css_preview' => '1', 'lalangs_css_inspect' => '1', 'lalangs_css_nonce' => wp_create_nonce( self::NONCE ) ), $url );
        $inspect = add_query_arg( array( 'lalangs_css_inspect' => '1', 'lalangs_css_nonce' => wp_create_nonce( self::NONCE ) ), $url );
        ?>
        <div class="wrap">
            <h1>Lalangs CSS Control <small>0.1.0</small></h1>
            <p>Kawalan per halaman. Critical CSS dimasukkan sendiri; plugin ini tidak menjana Critical CSS secara automatik.</p>
            <p>Matikan CSS Async, UCSS Inline dan CSS Combine dalam plugin optimasi lain sebelum menggunakan kawalan async ini. LiteSpeed page cache boleh dikekalkan.</p>
            <?php if ( isset( $_GET['result'] ) && is_string( $_GET['result'] ) ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( 'saved' === $_GET['result'] ? 'Tetapan disimpan. Purge cache halaman ini selepas perubahan.' : 'Tetapan tidak disimpan: CSS mesti tanpa tag HTML atau @import, maksimum 150 KB. Semak halaman sasaran juga.' ); ?></p></div>
            <?php endif; ?>
            <form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">
                <input type="hidden" name="page" value="lalangs-css-control">
                <label for="lalangs-rule"><strong>Halaman:</strong></label>
                <select id="lalangs-rule" name="rule">
                    <option value="home" <?php selected( $key, 'home' ); ?>>Homepage</option>
                    <?php foreach ( get_pages( array( 'sort_column' => 'post_title', 'post_status' => 'publish' ) ) as $page ) : ?>
                        <?php if ( (int) get_option( 'page_on_front' ) === $page->ID ) { continue; } ?>
                        <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $key, (string) $page->ID ); ?>><?php echo esc_html( $page->post_title . ' (#' . $page->ID . ')' ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Buka Tetapan</button>
            </form>
            <hr>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="lalangs_css_save">
                <input type="hidden" name="rule" value="<?php echo esc_attr( $key ); ?>">
                <?php wp_nonce_field( 'lalangs_css_save_' . $key ); ?>
                <p><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>> <strong>Aktifkan untuk pengunjung pada halaman ini</strong></label></p>
                <p>Biarkan OFF semasa menyediakan CSS. Simpan dan gunakan Preview dahulu. Critical CSS kosong bermaksud tiada optimasi berlaku.</p>
                <h2>Critical CSS</h2>
                <p>Masukkan CSS penting untuk hero, header dan layout awal pada desktop serta mobile. Jangan masukkan tag &lt;style&gt; atau @import.</p>
                <textarea name="critical_css" rows="18" class="large-text code" spellcheck="false" aria-label="Critical CSS"><?php echo esc_textarea( $rule['critical_css'] ); ?></textarea>
                <h2>Stylesheet handles untuk async</h2>
                <p>Satu handle setiap baris. Hanya handle ini diproses. Gunakan Inspect untuk mendapatkan nama sebenar. Google Fonts luar, tag yang sudah async dan data-no-async dilangkau.</p>
                <textarea name="handles" rows="10" class="large-text code" spellcheck="false" aria-label="Stylesheet handles"><?php echo esc_textarea( implode( "\n", $rule['handles'] ) ); ?></textarea>
                <p>CSS MotoPress/datepick/Select2 tidak disenaraikan secara default kerana borang tempahan masih menggunakannya.</p>
                <?php submit_button( 'Simpan Tetapan' ); ?>
            </form>
            <p><a class="button" href="<?php echo esc_url( $inspect ); ?>" target="_blank" rel="noopener noreferrer">Inspect CSS Halaman</a> <a class="button button-primary" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener noreferrer">Preview Tetapan Tersimpan</a></p>
            <p>Preview untuk administrator sahaja dan tidak dicache. Bagi rollback: OFF-kan halaman ini, simpan, kemudian purge cache. Nyahaktifkan plugin untuk menghentikan semua peraturannya.</p>
        </div>
        <?php
    }

    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied.', '', array( 'response' => 403 ) );
        }
        $raw_key = isset( $_POST['rule'] ) && is_string( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : 'home';
        $key = 'home' === $raw_key ? 'home' : (string) absint( $raw_key );
        check_admin_referer( 'lalangs_css_save_' . $key );
        $css = isset( $_POST['critical_css'] ) && is_string( $_POST['critical_css'] ) ? trim( wp_unslash( $_POST['critical_css'] ) ) : '';
        $result = 'invalid';
        if ( self::css_allowed( $css ) && ( 'home' === $key || 'page' === get_post_type( (int) $key ) ) ) {
            $text = isset( $_POST['handles'] ) && is_string( $_POST['handles'] ) ? wp_unslash( $_POST['handles'] ) : '';
            $handles = array();
            foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $handle ) {
                $handle = trim( $handle );
                if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $handle ) ) {
                    $handles[] = $handle;
                }
            }
            $rules = self::rules();
            $rules[ $key ] = array( 'enabled' => ! empty( $_POST['enabled'] ), 'critical_css' => $css, 'handles' => array_values( array_unique( $handles ) ) );
            update_option( self::OPTION, $rules, false );
            $result = 'saved';
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'lalangs-css-control', 'rule' => $key, 'result' => $result ), admin_url( 'options-general.php' ) ) );
        exit;
    }

    public static function inspector() {
        if ( ! self::authorized_tools() || ! isset( $_GET['lalangs_css_inspect'] ) || self::excluded_request() ) {
            return;
        }
        $styles = wp_styles();
        $handles = array_unique( array_merge( $styles->queue, $styles->done ) );
        echo '<details open style="position:relative;z-index:99999;margin:24px;padding:20px;background:#fff;color:#222;border:2px solid #a78351;font:14px/1.5 sans-serif;overflow:auto"><summary><strong>Lalangs CSS Inspector — administrator sahaja</strong></summary>';
        echo '<p>Senarai stylesheet WordPress pada halaman ini. CSS inline dan stylesheet yang ditulis terus oleh theme mungkin tidak termasuk.</p><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th>Handle</th><th>Status plugin</th><th>Source</th></tr></thead><tbody>';
        foreach ( $handles as $handle ) {
            if ( ! isset( $styles->registered[ $handle ] ) ) {
                continue;
            }
            $asset = $styles->registered[ $handle ];
            echo '<tr><td style="padding:8px;border-top:1px solid #ddd">' . esc_html( $handle ) . '</td><td style="padding:8px;border-top:1px solid #ddd">' . esc_html( isset( self::$deferred[ $handle ] ) ? 'Async oleh plugin ini' : 'Tidak diubah oleh plugin ini' ) . '</td><td style="padding:8px;border-top:1px solid #ddd;overflow-wrap:anywhere">' . esc_html( (string) $asset->src ) . '</td></tr>';
        }
        echo '</tbody></table></details>';
    }
}

Lalangs_CSS_Control::boot();
