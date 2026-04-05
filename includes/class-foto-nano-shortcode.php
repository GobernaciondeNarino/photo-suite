<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'foto_nano', array( $this, 'render' ) );
    }

    public function render( $atts ) {
        $settings = get_option( 'foto_nano_settings', array() );

        // Preparar datos para el frontend
        $mascotas = array();
        foreach ( ( $settings['mascotas'] ?? array() ) as $m ) {
            $mascotas[] = array(
                'id'     => $m['id'],
                'nombre' => $m['nombre'],
                'imagen' => $this->path_to_url( $m['imagen'] ?? '' ),
            );
        }

        $fondos = array();
        foreach ( ( $settings['fondos'] ?? array() ) as $f ) {
            $fondos[] = array(
                'id'        => $f['id'],
                'nombre'    => $f['nombre'],
                'imagen'    => $this->path_to_url( $f['imagen'] ?? '' ),
                'categoria' => $f['categoria'] ?? 'paisaje',
            );
        }

        $marcos = array();
        foreach ( ( $settings['postal_marcos'] ?? array() ) as $mk ) {
            $marcos[] = array(
                'id'     => $mk['id'],
                'nombre' => $mk['nombre'],
                'imagen' => $this->path_to_url( $mk['imagen'] ?? '' ),
            );
        }

        $formatos = $settings['formatos_habilitados'] ?? array( '1:1' );
        $formato_defecto = $settings['formato_defecto'] ?? '1:1';
        $postal_texto = $settings['postal_texto_defecto'] ?? 'Recuerdo de mi visita';

        // Inyectar datos al JS
        wp_localize_script( 'foto-nano-public', 'fotoNanoData', array(
            'mascotas'       => $mascotas,
            'fondos'         => $fondos,
            'marcos'         => $marcos,
            'formatos'       => $formatos,
            'formatoDefecto' => $formato_defecto,
            'postalTexto'    => $postal_texto,
        ) );

        ob_start();
        include FOTO_NANO_PLUGIN_DIR . 'public/partials/shortcode-display.php';
        return ob_get_clean();
    }

    private function path_to_url( $path ) {
        if ( empty( $path ) ) return '';
        if ( strpos( $path, 'http' ) === 0 ) return $path;
        $upload_dir = wp_upload_dir();
        return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
    }
}
