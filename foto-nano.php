<?php
/**
 * Plugin Name: Foto-Nano
 * Plugin URI: https://narino.gov.co
 * Description: Genera imagenes personalizadas con IA: fotos con mascotas, fondos escenicos y postales usando face-swap.
 * Version: 1.0.0
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

define( 'FOTO_NANO_VERSION', '1.0.0' );
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

    // Opciones por defecto
    $defaults = array(
        'replicate_api_key'    => '',
        'replicate_model'      => 'lucataco/facefusion',
        'formatos_habilitados' => array( '1:1', '9:16', '16:9', '4:3', '3:4' ),
        'formato_defecto'      => '1:1',
        'email_remitente'      => get_option( 'admin_email' ),
        'email_asunto'         => 'Tu foto personalizada - Foto-Nano',
        'email_plantilla'      => '<h2>Aqui esta tu foto personalizada</h2><p>Gracias por usar Foto-Nano.</p>',
        'limite_generaciones'  => 10,
        'mascotas'             => array(),
        'fondos'               => array(),
        'postal_marcos'        => array(),
        'postal_texto_defecto' => 'Recuerdo de mi visita',
        'postal_fuente_size'   => 24,
        'postal_color_texto'   => '#ffffff',
        'postal_posicion_nombre' => 'bottom-center',
    );

    if ( ! get_option( 'foto_nano_settings' ) ) {
        add_option( 'foto_nano_settings', $defaults );
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
        array_map( 'unlink', glob( $temp_dir . '/*' ) );
    }
}
register_deactivation_hook( __FILE__, 'foto_nano_deactivate' );

// Cargar clases
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-admin.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-api.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-shortcode.php';
require_once FOTO_NANO_PLUGIN_DIR . 'includes/class-foto-nano-email.php';

// Iniciar plugin
Foto_Nano::get_instance();
