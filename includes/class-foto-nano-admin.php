<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_save' ) );
    }

    public function add_menu() {
        add_menu_page(
            'Foto-Nano',
            'Foto-Nano',
            'manage_options',
            'foto-nano',
            array( $this, 'render_settings_page' ),
            'dashicons-camera',
            30
        );
    }

    public function render_settings_page() {
        $settings = get_option( 'foto_nano_settings', array() );
        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
        ?>
        <div class="wrap foto-nano-admin">
            <h1><span class="dashicons dashicons-camera" style="font-size:28px;margin-right:8px;"></span> Foto-Nano v<?php echo esc_html( FOTO_NANO_VERSION ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=foto-nano&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings" style="margin-right:4px;"></span> General
                </a>
                <a href="?page=foto-nano&tab=mascotas" class="nav-tab <?php echo $active_tab === 'mascotas' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-pets" style="margin-right:4px;"></span> Mascotas
                </a>
                <a href="?page=foto-nano&tab=fondos" class="nav-tab <?php echo $active_tab === 'fondos' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-image" style="margin-right:4px;"></span> Fondos
                </a>
                <a href="?page=foto-nano&tab=postal" class="nav-tab <?php echo $active_tab === 'postal' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-email-alt" style="margin-right:4px;"></span> Postal
                </a>
            </nav>

            <form method="post" action="">
                <?php wp_nonce_field( 'foto_nano_save_settings', 'foto_nano_nonce' ); ?>
                <input type="hidden" name="foto_nano_tab" value="<?php echo esc_attr( $active_tab ); ?>">

                <?php
                switch ( $active_tab ) {
                    case 'mascotas':
                        include FOTO_NANO_PLUGIN_DIR . 'admin/partials/settings-mascotas.php';
                        break;
                    case 'fondos':
                        include FOTO_NANO_PLUGIN_DIR . 'admin/partials/settings-fondos.php';
                        break;
                    case 'postal':
                        include FOTO_NANO_PLUGIN_DIR . 'admin/partials/settings-postal.php';
                        break;
                    default:
                        include FOTO_NANO_PLUGIN_DIR . 'admin/partials/settings-general.php';
                        break;
                }
                ?>

                <p class="submit">
                    <button type="submit" name="foto_nano_save" class="button button-primary button-hero">
                        <span class="dashicons dashicons-saved" style="margin-right:4px;margin-top:4px;"></span> Guardar Cambios
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_save() {
        if ( ! isset( $_POST['foto_nano_save'] ) ) {
            return;
        }

        if ( ! check_admin_referer( 'foto_nano_save_settings', 'foto_nano_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( 'foto_nano_settings', array() );
        $tab = sanitize_text_field( $_POST['foto_nano_tab'] ?? 'general' );

        switch ( $tab ) {
            case 'general':
                // Proveedor activo
                $allowed_providers = array( 'replicate', 'huggingface', 'openai', 'google', 'anthropic' );
                $active_provider = sanitize_text_field( $_POST['active_provider'] ?? 'replicate' );
                $settings['active_provider'] = in_array( $active_provider, $allowed_providers, true ) ? $active_provider : 'replicate';

                // API Keys - encriptar antes de guardar
                $api_keys = array();
                if ( ! empty( $_POST['api_keys'] ) && is_array( $_POST['api_keys'] ) ) {
                    foreach ( $_POST['api_keys'] as $provider_id => $key ) {
                        $provider_id = sanitize_key( $provider_id );
                        if ( ! in_array( $provider_id, $allowed_providers, true ) ) {
                            continue;
                        }
                        $key = sanitize_text_field( $key );
                        if ( ! empty( $key ) ) {
                            $api_keys[ $provider_id ] = Foto_Nano_Security::encrypt( $key );
                        }
                    }
                }
                $settings['api_keys'] = $api_keys;

                // Modelos por proveedor
                $provider_models = array();
                if ( ! empty( $_POST['provider_models'] ) && is_array( $_POST['provider_models'] ) ) {
                    foreach ( $_POST['provider_models'] as $provider_id => $model ) {
                        $provider_id = sanitize_key( $provider_id );
                        if ( in_array( $provider_id, $allowed_providers, true ) ) {
                            $provider_models[ $provider_id ] = sanitize_text_field( $model );
                        }
                    }
                }
                $settings['provider_models'] = $provider_models;

                // Compatibilidad: mantener replicate_api_key para migracion
                if ( ! empty( $api_keys['replicate'] ) ) {
                    $settings['replicate_api_key'] = $api_keys['replicate'];
                }
                $settings['replicate_model'] = $provider_models['replicate'] ?? 'lucataco/facefusion';

                // Formatos
                $settings['formato_defecto'] = sanitize_text_field( $_POST['formato_defecto'] ?? '1:1' );
                $formatos = array();
                $allowed_formats = array( '1:1', '9:16', '16:9', '4:3', '3:4' );
                foreach ( $allowed_formats as $f ) {
                    $key = 'formato_' . str_replace( ':', '_', $f );
                    if ( ! empty( $_POST[ $key ] ) ) {
                        $formatos[] = $f;
                    }
                }
                $settings['formatos_habilitados'] = ! empty( $formatos ) ? $formatos : array( '1:1' );

                // Email
                $settings['email_remitente']     = sanitize_email( $_POST['email_remitente'] ?? '' );
                $settings['email_asunto']        = sanitize_text_field( $_POST['email_asunto'] ?? '' );
                $settings['email_plantilla']     = wp_kses_post( $_POST['email_plantilla'] ?? '' );

                // Seguridad
                $settings['limite_generaciones'] = absint( $_POST['limite_generaciones'] ?? 10 );
                $settings['rate_limit_per_hour'] = absint( $_POST['rate_limit_per_hour'] ?? 20 );
                $settings['max_upload_size']     = absint( $_POST['max_upload_size'] ?? 10 );
                $settings['auto_cleanup']        = ! empty( $_POST['auto_cleanup'] ) ? 1 : 0;
                break;

            case 'mascotas':
                $mascotas = array();
                if ( ! empty( $_POST['mascota_nombre'] ) && is_array( $_POST['mascota_nombre'] ) ) {
                    foreach ( $_POST['mascota_nombre'] as $i => $nombre ) {
                        if ( empty( $nombre ) ) continue;
                        $mascotas[] = array(
                            'id'       => sanitize_title( $nombre ) . '_' . $i,
                            'nombre'   => sanitize_text_field( $nombre ),
                            'imagen'   => sanitize_text_field( $_POST['mascota_imagen'][ $i ] ?? '' ),
                            'tamano'   => absint( $_POST['mascota_tamano'][ $i ] ?? 30 ),
                            'posicion' => sanitize_text_field( $_POST['mascota_posicion'][ $i ] ?? 'derecha' ),
                            'fondo'    => sanitize_text_field( $_POST['mascota_fondo'][ $i ] ?? '' ),
                        );
                    }
                }
                $settings['mascotas'] = $mascotas;
                break;

            case 'fondos':
                $fondos = array();
                if ( ! empty( $_POST['fondo_nombre'] ) && is_array( $_POST['fondo_nombre'] ) ) {
                    foreach ( $_POST['fondo_nombre'] as $i => $nombre ) {
                        if ( empty( $nombre ) ) continue;
                        $fondos[] = array(
                            'id'        => sanitize_title( $nombre ) . '_' . $i,
                            'nombre'    => sanitize_text_field( $nombre ),
                            'imagen'    => sanitize_text_field( $_POST['fondo_imagen'][ $i ] ?? '' ),
                            'categoria' => sanitize_text_field( $_POST['fondo_categoria'][ $i ] ?? 'paisaje' ),
                        );
                    }
                }
                $settings['fondos'] = $fondos;
                break;

            case 'postal':
                $marcos = array();
                if ( ! empty( $_POST['marco_nombre'] ) && is_array( $_POST['marco_nombre'] ) ) {
                    foreach ( $_POST['marco_nombre'] as $i => $nombre ) {
                        if ( empty( $nombre ) ) continue;
                        $marcos[] = array(
                            'id'     => sanitize_title( $nombre ) . '_' . $i,
                            'nombre' => sanitize_text_field( $nombre ),
                            'imagen' => sanitize_text_field( $_POST['marco_imagen'][ $i ] ?? '' ),
                        );
                    }
                }
                $settings['postal_marcos']          = $marcos;
                $settings['postal_texto_defecto']    = sanitize_text_field( $_POST['postal_texto_defecto'] ?? '' );
                $settings['postal_fuente_size']      = absint( $_POST['postal_fuente_size'] ?? 24 );
                $settings['postal_color_texto']      = sanitize_hex_color( $_POST['postal_color_texto'] ?? '#ffffff' );
                $settings['postal_posicion_nombre']  = sanitize_text_field( $_POST['postal_posicion_nombre'] ?? 'bottom-center' );
                break;
        }

        update_option( 'foto_nano_settings', $settings );

        add_settings_error( 'foto_nano', 'settings_updated', 'Configuracion guardada correctamente.', 'updated' );
    }

    /**
     * Obtener modelo por defecto para un proveedor.
     */
    private function get_default_model( $provider_id ) {
        $defaults = array(
            'replicate'   => 'lucataco/facefusion',
            'huggingface' => 'stabilityai/stable-diffusion-xl-base-1.0',
            'openai'      => 'gpt-image-1',
            'google'      => 'gemini-2.0-flash-exp',
            'anthropic'   => 'claude-sonnet-4-20250514',
        );
        return $defaults[ $provider_id ] ?? '';
    }

    public function path_to_url( $path ) {
        $upload_dir = wp_upload_dir();
        return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
    }
}
