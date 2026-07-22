<?php
/**
 * Harnais de test — charge le VRAI code du plugin (includes/db.php, includes/rest.php)
 * en simulant juste assez de WordPress pour l'exécuter hors serveur.
 *
 * $wpdb est adossé à SQLite en mémoire via un shim qui traduit les quelques
 * constructions MySQL réellement utilisées par le plugin. Aucune logique métier
 * n'est réimplémentée ici : les assertions portent sur les fonctions du plugin.
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ . '/stubs/' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'OBJECT' ) )  define( 'OBJECT', 'OBJECT' );
if ( ! defined( 'OBJECT_K' ) ) define( 'OBJECT_K', 'OBJECT_K' );
define( 'VB_PLUGIN_URL', 'http://example.test/wp-content/plugins/younessWeb-manager/' );

/* ---------- fonctions WordPress utilisées par le plugin ---------- */

function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_email( $v ) { return filter_var( trim( (string) $v ), FILTER_VALIDATE_EMAIL ) ?: ''; }
function esc_url_raw( $v ) { return trim( (string) $v ); }
function current_time( $type ) { return date( $type === 'mysql' ? 'Y-m-d H:i:s' : $type ); }
function sanitize_sql_orderby( $v ) { return preg_match( '/^[a-z_]+$/i', (string) $v ) ? $v : false; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function wp_generate_password( $len = 12 ) { return substr( str_repeat( 'k3yA', 32 ), 0, $len ); }
function selected( $a, $b, $echo = true ) { return $a == $b ? 'selected' : ''; }
function esc_html( $v ) { return htmlspecialchars( (string) $v ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v ); }
// function_exists : certains fichiers de test déclarent déjà ces stubs avant
// de charger le harnais. Une redéclaration serait fatale.
if ( ! function_exists( 'esc_textarea' ) )   { function esc_textarea( $v ) { return htmlspecialchars( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) )     { function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags | JSON_UNESCAPED_UNICODE ); } }
if ( ! function_exists( 'checked' ) )        { function checked( $a, $b = true, $echo = true ) { return $a == $b ? 'checked' : ''; } }
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'http://example.test/wp-json/' . $p; }

// Les options sont modélisées par un tableau PHP, ET reflétées dans la table
// wp_options SQLite : vb_contract_seq_options() interroge la table réelle,
// alors que le reste du plugin passe par get_option/update_option.
$GLOBALS['__options'] = [];
function vb_test_sync_option( $k, $v ) {
    global $wpdb;
    if ( ! isset( $wpdb ) || ! ( $wpdb instanceof TestWpdb ) ) return;
    $wpdb->query( "INSERT INTO wp_options (option_name, option_value) VALUES ("
        . "'" . addslashes( $k ) . "', '" . addslashes( is_scalar( $v ) ? (string) $v : json_encode( $v ) ) . "') "
        . "ON CONFLICT(option_name) DO UPDATE SET option_value=excluded.option_value" );
}
function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function add_option( $k, $v ) { if ( ! isset( $GLOBALS['__options'][ $k ] ) ) { $GLOBALS['__options'][ $k ] = $v; vb_test_sync_option( $k, $v ); } return true; }
function update_option( $k, $v, $a = false ) { $GLOBALS['__options'][ $k ] = $v; vb_test_sync_option( $k, $v ); return true; }
// Guard : whatsapp-prefill.test.php déclare aussi delete_option (les
// déclarations de fonctions au niveau fichier sont hoistées avant le require).
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }
}

$GLOBALS['__actions'] = [];
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__hooks'][ $h ][] = $cb; return true; }
function do_action( $h, ...$args ) { $GLOBALS['__actions'][] = [ 'hook' => $h, 'args' => $args ]; }
function fired_actions( $hook ) {
    return array_values( array_filter( $GLOBALS['__actions'], fn( $a ) => $a['hook'] === $hook ) );
}
function reset_actions() { $GLOBALS['__actions'] = []; }

$GLOBALS['__routes'] = [];
function register_rest_route( $ns, $route, $args ) { $GLOBALS['__routes'][ "$ns$route" ] = $args; }

class WP_Error {
    public $code, $message, $data;
    public function __construct( $c = '', $m = '', $d = [] ) { $this->code = $c; $this->message = $m; $this->data = $d; }
    public function get_error_code() { return $this->code; }
    public function get_status() { return $this->data['status'] ?? 500; }
}
class WP_REST_Response {
    public $data, $status;
    public function __construct( $d = null, $s = 200 ) { $this->data = $d; $this->status = $s; }
}
class WP_REST_Request {
    private $headers = [], $json;
    public function __construct( $json = [], $headers = [] ) {
        $this->json = $json;
        foreach ( $headers as $k => $v ) $this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] = $v;
    }
    public function get_header( $k ) { return $this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] ?? null; }
    public function get_json_params() { return $this->json; }
}

function dbDelta( $sql ) {
    global $wpdb;
    foreach ( array_filter( array_map( 'trim', explode( ';', $sql ) ) ) as $stmt ) $wpdb->query( $stmt );
}

/* ---------- $wpdb : shim MySQL -> SQLite ---------- */

class TestWpdb {
    public $prefix = 'wp_';
    public $options = 'wp_options';   // vb_contract_seq_options() interroge cette table
    public $insert_id = 0;
    public $last_error = '';
    private PDO $pdo;

    public function __construct() {
        $this->pdo = new PDO( 'sqlite::memory:' );
        $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $this->pdo->sqliteCreateFunction( 'MONTH', fn( $d ) => (int) date( 'n', strtotime( $d ) ) );
        $this->pdo->sqliteCreateFunction( 'YEAR', fn( $d ) => (int) date( 'Y', strtotime( $d ) ) );
        $this->pdo->sqliteCreateFunction( 'NOW', fn() => date( 'Y-m-d H:i:s' ) );
        // Table des options, pour tester la sauvegarde des compteurs de contrats.
        $this->pdo->exec( "CREATE TABLE wp_options ( option_name TEXT PRIMARY KEY, option_value TEXT )" );
    }

    public function get_charset_collate() { return ''; }
    public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }

    public function prepare( $sql, ...$args ) {
        if ( count( $args ) === 1 && is_array( $args[0] ) ) $args = $args[0];
        $out = ''; $i = 0;
        // Remplace %s / %d / %f dans l'ordre, comme wpdb.
        for ( $p = 0; $p < strlen( $sql ); $p++ ) {
            if ( $sql[ $p ] === '%' && isset( $sql[ $p + 1 ] ) && in_array( $sql[ $p + 1 ], [ 's', 'd', 'f' ], true ) ) {
                $v = $args[ $i++ ] ?? '';
                $out .= $sql[ $p + 1 ] === 's' ? $this->pdo->quote( (string) $v ) : ( $sql[ $p + 1 ] === 'd' ? (int) $v : (float) $v );
                $p++;
            } else {
                $out .= $sql[ $p ];
            }
        }
        return $out;
    }

    /** Traduit les constructions MySQL utilisées par le plugin vers SQLite. */
    private function translate( $sql ) {
        $sql = trim( $sql );

        if ( preg_match( "/^SHOW TABLES LIKE '(.+)'$/i", $sql, $m ) ) {
            return "SELECT name FROM sqlite_master WHERE type='table' AND name='{$m[1]}'";
        }
        if ( preg_match( '/^DESC\s+(\S+)/i', $sql, $m ) ) {
            return "SELECT name FROM pragma_table_info('{$m[1]}')";
        }
        if ( preg_match( "/^SHOW INDEX FROM\s+(\S+)\s+WHERE Key_name = '(.+)'$/i", $sql, $m ) ) {
            return "SELECT name FROM sqlite_master WHERE type='index' AND name='{$m[2]}' AND tbl_name='{$m[1]}'";
        }
        if ( preg_match( '/^ALTER TABLE\s+(\S+)\s+ADD KEY\s+(\S+)\s*\((.+)\)$/i', $sql, $m ) ) {
            return "CREATE INDEX IF NOT EXISTS {$m[2]} ON {$m[1]} ({$m[3]})";
        }
        if ( preg_match( '/^ALTER TABLE\s+(\S+)\s+ADD COLUMN\s+(.+?)\s+AFTER\s+\S+$/i', $sql, $m ) ) {
            return "ALTER TABLE {$m[1]} ADD COLUMN {$m[2]}";
        }

        if ( stripos( $sql, 'CREATE TABLE' ) === 0 ) {
            // Types MySQL -> SQLite, PK auto-incrémentée, index inline extraits.
            $sql = preg_replace( '/\)\s*$/', ')', $sql );
            $sql = preg_replace( '/BIGINT\(20\) UNSIGNED NOT NULL AUTO_INCREMENT/i', 'INTEGER', $sql );
            $sql = preg_replace( '/PRIMARY KEY \((\w+)\)/i', 'PRIMARY KEY ($1 AUTOINCREMENT)', $sql );
            $sql = preg_replace( '/,\s*KEY\s+\w+\s*\([^)]*\)/i', '', $sql );
            // Les types d'abord : sinon le `datetime('now')` injecté juste après
            // serait lui-même réécrit en `TEXT('now')` par la règle DATETIME.
            $sql = preg_replace( '/\bDATETIME\b/i', 'TEXT', $sql );
            $sql = preg_replace( '/\b(VARCHAR|DECIMAL)\([\d,\s]+\)/i', 'TEXT', $sql );
            $sql = preg_replace( '/\bTINYINT\(1\)/i', 'INTEGER', $sql );
            $sql = preg_replace( '/\bBIGINT\(20\) UNSIGNED\b/i', 'INTEGER', $sql );
            $sql = preg_replace( '/DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/i', "DEFAULT (datetime('now'))", $sql );
            $sql = preg_replace( '/DEFAULT CURRENT_TIMESTAMP/i', "DEFAULT (datetime('now'))", $sql );
            // PRIMARY KEY (id AUTOINCREMENT) doit être inline en SQLite.
            $sql = preg_replace( '/,\s*PRIMARY KEY \(id AUTOINCREMENT\)/i', '', $sql );
            $sql = preg_replace( '/\bid\s+INTEGER\b/i', 'id INTEGER PRIMARY KEY AUTOINCREMENT', $sql );
            return rtrim( $sql, '; ' );
        }

        $sql = preg_replace( '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+(\d+)\s+HOUR\)/i', "datetime('now','-$1 hours')", $sql );
        return $sql;
    }

    public function query( $sql ) {
        try {
            $r = $this->pdo->exec( $this->translate( $sql ) );
            return $r === false ? false : $r;
        } catch ( PDOException $e ) { $this->last_error = $e->getMessage(); return false; }
    }

    private function fetch( $sql ) {
        try { return $this->pdo->query( $this->translate( $sql ) )->fetchAll( PDO::FETCH_ASSOC ); }
        catch ( PDOException $e ) { $this->last_error = $e->getMessage(); return []; }
    }

    public function get_var( $sql ) { $r = $this->fetch( $sql ); return $r ? array_values( $r[0] )[0] : null; }
    public function get_col( $sql, $i = 0 ) { return array_map( fn( $r ) => array_values( $r )[ $i ], $this->fetch( $sql ) ); }
    public function get_row( $sql ) { $r = $this->fetch( $sql ); return $r ? (object) $r[0] : null; }
    public function get_results( $sql, $output = OBJECT ) {
        $rows = $this->fetch( $sql );
        return $output === ARRAY_A ? $rows : array_map( fn( $r ) => (object) $r, $rows );
    }

    public function insert( $table, $data ) {
        $cols = array_keys( $data );
        $vals = implode( ',', array_map( fn( $v ) => is_null( $v ) ? 'NULL' : $this->pdo->quote( (string) $v ), $data ) );
        $ok = $this->query( "INSERT INTO $table (" . implode( ',', $cols ) . ") VALUES ($vals)" );
        if ( $ok === false ) return false;
        $this->insert_id = (int) $this->pdo->lastInsertId();
        return 1;
    }

    public function update( $table, $data, $where ) {
        $set = implode( ',', array_map( fn( $k ) => "$k=" . ( is_null( $data[ $k ] ) ? 'NULL' : $this->pdo->quote( (string) $data[ $k ] ) ), array_keys( $data ) ) );
        $w   = implode( ' AND ', array_map( fn( $k ) => "$k=" . $this->pdo->quote( (string) $where[ $k ] ), array_keys( $where ) ) );
        return $this->query( "UPDATE $table SET $set WHERE $w" );
    }

    public function delete( $table, $where ) {
        $w = implode( ' AND ', array_map( fn( $k ) => "$k=" . $this->pdo->quote( (string) $where[ $k ] ), array_keys( $where ) ) );
        return $this->query( "DELETE FROM $table WHERE $w" );
    }
}

global $wpdb;
$wpdb = new TestWpdb();

/* ---------- chargement du vrai code du plugin ---------- */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/rest.php';

/* ---------- micro-assertions ---------- */

$GLOBALS['__pass'] = 0; $GLOBALS['__fail'] = 0;
function ok( $cond, $label ) {
    if ( $cond ) { $GLOBALS['__pass']++; echo "  \033[32m✓\033[0m $label\n"; }
    else { $GLOBALS['__fail']++; echo "  \033[31m✗\033[0m $label\n"; }
}
function is_eq( $actual, $expected, $label ) {
    $c = $actual === $expected;
    if ( ! $c ) $label .= sprintf( "  (attendu %s, obtenu %s)", var_export( $expected, true ), var_export( $actual, true ) );
    ok( $c, $label );
}
function summary() {
    printf( "\n%d passés, %d échoués\n", $GLOBALS['__pass'], $GLOBALS['__fail'] );
    exit( $GLOBALS['__fail'] > 0 ? 1 : 0 );
}
