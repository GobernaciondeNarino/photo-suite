<?php
/**
 * Plugin Name: Foto-Nano
 * Plugin URI: https://narino.gov.co
 * Description: Genera imagenes personalizadas con IA: fotos con mascotas, fondos escenicos y postales. Soporta multiples proveedores: Replicate, Hugging Face, OpenAI, Google AI y Claude.
 * Version: 2.0.0
 * Author: Gobernacion de Narino
 * Author URI: https://narino.gov.co
 * Text Domain: foto-nano
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FOTO_NANO_VERSION', '2.0.0' );
define( 'FOTO_NANO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOTO_NANO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FOTO_NANO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Activacion del plugin.
 */
function foto_nano_activate() {
    $upload_dir = wp_upload_dir();
    $foto_nano_dir = $upload_dir['basedir'] . '/foto-nano';
    $temp_dir = $foto_nano_dir . '/temp';
    $generated_dir = $foto_nano_dir . '/generated';

    if ( ! file_exists( $foto_nano_dir ) ) {
        wp_mkdir_p( $foto_nano_dir );
    }
    if ( ! file_exists( $temp_dir ) ) {
        wp_mkdir_p( $temp_dir );
    }
    if ( ! file_exists( $generated_dir ) ) {
        wp_mkdir_p( $generated_dir );
    }

    // Proteger directorios con .htaccess
    $htaccess_content = "Options -Indexes\n<FilesMatch '\.(php|phtml|phar|php5)$'>\nOrder Allow,Deny\nDeny from all\n</FilesMatch>";
    if ( ! file_exists( $foto_nano_dir . '/.htaccess' ) ) {
        file_put_contents( $foto_nano_dir . '/.htaccess', $htaccess_content );
    }
    // Proteger con index.php
    foreach ( array( $foto_nano_dir, $temp_dir, $generated_dir ) as $dir ) {
        $index_file = $dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }
    }

    // Opciones por defecto
    $defaults = array(
        'active_provider'      => 'replicate',
        'api_keys'             => array(),
        'provider_models'      => array(),
        'replicate_api_key'    => '',
        'replicate_model'      => 'lucataco/facefusion',
        'formatos_habilitados' => array( '1:1', '9:16', '16:9', '4:3', '3:4' ),
        'formato_defecto'      => '1:1',
        'email_remitente'      => get_option( 'admin_email' ),
        'email_asunto'         => 'Tu foto personalizada - Foto-Nano',
        'email_plantilla'      => '<h2>Aqui esta tu foto personalizada</h2><p>Gracias por usar Foto-Nano de la Gobernacion de Narino.</p>',
        'limite_generaciones'  => 10,
        'rate_limit_per_hour'  => 20,
        'max_upload_size'      => 10,
        'auto_cleanup'         => 1,
        'mascotas'             => array(),
        'fondos'               => array(),
        'postal_marcos'        => array(),
        'postal_texto_defecto' => 'Recuerdo de mi visita',
        'postal_fuente_size'   => 24,
        'postal_color_texto'   => '#ffffff',
        'postal_posicion_nombre' => 'bottom-center',
    );

    $existing = get_option( 'foto_nano_settings' );
    if ( ! $existing ) {
        add_option( 'foto_nano_settings', $defaults );
    } else {
        // Migrar configuracion existente a v2
        $migrated = false;
        if ( ! isset( $existing['active_provider'] ) ) {
            $existing['active_provider'] = 'replicate';
            $migrated = true;
        }
        if ( ! isset( $existing['api_keys'] ) ) {
            $existing['api_keys'] = array();
            // Migrar API key de Replicate existente
            if ( ! empty( $existing['replicate_api_key'] ) ) {
                // Solo encriptar si no esta ya encriptado
                $key = $existing['replicate_api_key'];
                if ( class_exists( 'Foto_Nano_Security' ) ) {
                    $existing['api_keys']['replicate'] = Foto_Nano_Security::encrypt( $key );
                } else {
                    $existing['api_keys']['replicate'] = $key;
                }
            }
            $migrated = true;
        }
        if ( ! isset( $existing['provider_models'] ) ) {
            $existing['provider_models'] = array();
            if ( ! empty( $existing['replicate_model'] ) ) {
                $existing['provider_models']['replicate'] = $existing['replicate_model'];
            }
            $migrated = true;
        }
        // Agregar nuevos defaults que no existan
        foreach ( $defaults as $key => $value ) {
            if ( ! isset( $existing[ $key ] ) ) {
                $existing[ $key ] = $value;
                $migrated = true;
            }
        }
        if ( $migrated ) {
            update_option( 'foto_nano_settings', $existing );
        }
    }

    // Programar cron de limpieza
    if ( ! wp_next_scheduled( 'foto_nano_cleanup_cron' ) ) {
        wp_schedule_event( time(), 'hourly', 'foto_nano_cleanup_cron' );
    }
}
register_activation_hook( __FILE__, 'foto_nano_activate' );

/**
 * Desactivacion del plugin.
 */
function foto_nano_deactivate() {
    // Limpiar archivos temporales
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/foto-nano/temp';
    if ( file_exists( $temp_dir ) ) {
        $files = glob( $temp_dir . '/*' );
        if ( $files ) {
            array_map( 'unlink', array_filter( $files, 'is_file' ) );
        }
    }

    // Limpiar cron
    $timestamp = wp_next_scheduled( 'foto_nano_cleanup_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'foto_nano_cleanup_cron' );
    }
}
register_deactivation_hook( __FILE__, 'foto_nano_deactivate' );

// Cargar clases - Seguridad primero
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-security.php';

// Proveedores de API
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-base.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-replicate.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-huggingface.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-openai.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-google.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/providers/class-foto-nano-provider-anthropic.php';

// Clases principales
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-api.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-admin.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-shortcode.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-email.php';

// Iniciar plugin
Foto_Nano::get_instance();
