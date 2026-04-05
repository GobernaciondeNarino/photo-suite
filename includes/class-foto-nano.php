<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Iniciar modulos
        Foto_Nano_Admin::get_instance();
        Foto_Nano_Shortcode::get_instance();

        // AJAX handlers
        add_action( 'wp_ajax_foto_nano_upload_photo', array( $this, 'ajax_upload_photo' ) );
        add_action( 'wp_ajax_nopriv_foto_nano_upload_photo', array( $this, 'ajax_upload_photo' ) );
        add_action( 'wp_ajax_foto_nano_generate', array( $this, 'ajax_generate' ) );
        add_action( 'wp_ajax_nopriv_foto_nano_generate', array( $this, 'ajax_generate' ) );
        add_action( 'wp_ajax_foto_nano_check_status', array( $this, 'ajax_check_status' ) );
        add_action( 'wp_ajax_nopriv_foto_nano_check_status', array( $this, 'ajax_check_status' ) );
        add_action( 'wp_ajax_foto_nano_send_email', array( $this, 'ajax_send_email' ) );
        add_action( 'wp_ajax_nopriv_foto_nano_send_email', array( $this, 'ajax_send_email' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'foto-nano', false, dirname( FOTO_NANO_PLUGIN_BASENAME ) . '/languages' );
    }

    public function enqueue_public_assets() {
        if ( ! is_admin() ) {
            wp_enqueue_style(
                'foto-nano-public',
                FOTO_NANO_PLUGIN_URL . 'public/css/foto-nano-public.css',
                array(),
                FOTO_NANO_VERSION
            );
            wp_enqueue_script(
                'foto-nano-public',
                FOTO_NANO_PLUGIN_URL . 'public/js/foto-nano-public.js',
                array( 'jquery' ),
                FOTO_NANO_VERSION,
                true
            );
            wp_localize_script( 'foto-nano-public', 'fotoNano', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'foto_nano_nonce' ),
                'strings' => array(
                    'cameraError'    => __( 'No se pudo acceder a la camara. Verifica los permisos.', 'foto-nano' ),
                    'uploading'      => __( 'Subiendo foto...', 'foto-nano' ),
                    'generating'     => __( 'Generando tu imagen con IA...', 'foto-nano' ),
                    'emailSent'      => __( 'Imagen enviada correctamente a tu correo.', 'foto-nano' ),
                    'emailError'     => __( 'Error al enviar el correo. Intenta de nuevo.', 'foto-nano' ),
                    'selectPet'      => __( 'Selecciona una mascota', 'foto-nano' ),
                    'selectBg'       => __( 'Selecciona un fondo', 'foto-nano' ),
                    'enterName'      => __( 'Ingresa tu nombre', 'foto-nano' ),
                    'processing'     => __( 'Procesando...', 'foto-nano' ),
                    'downloadReady'  => __( 'Tu imagen esta lista', 'foto-nano' ),
                    'retakePhoto'    => __( 'Retomar foto', 'foto-nano' ),
                    'errorGeneric'   => __( 'Ocurrio un error. Intenta de nuevo.', 'foto-nano' ),
                ),
            ) );
        }
    }

    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'foto-nano' ) === false ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style(
            'foto-nano-admin',
            FOTO_NANO_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            FOTO_NANO_VERSION
        );
        wp_enqueue_script(
            'foto-nano-admin',
            FOTO_NANO_PLUGIN_URL . 'admin/js/admin-script.js',
            array( 'jquery' ),
            FOTO_NANO_VERSION,
            true
        );
        wp_localize_script( 'foto-nano-admin', 'fotoNanoAdmin', array(
            'selectImage' => __( 'Seleccionar imagen', 'foto-nano' ),
            'useImage'    => __( 'Usar esta imagen', 'foto-nano' ),
        ) );
    }

    /**
     * AJAX: Subir foto capturada.
     */
    public function ajax_upload_photo() {
        check_ajax_referer( 'foto_nano_nonce', 'nonce' );

        if ( empty( $_FILES['photo'] ) ) {
            wp_send_json_error( array( 'message' => 'No se recibio la foto.' ) );
        }

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/foto-nano/temp';
        $filename = 'face_' . uniqid() . '.jpg';
        $filepath = $temp_dir . '/' . $filename;

        if ( ! move_uploaded_file( $_FILES['photo']['tmp_name'], $filepath ) ) {
            wp_send_json_error( array( 'message' => 'Error al guardar la foto.' ) );
        }

        wp_send_json_success( array(
            'filename' => $filename,
            'url'      => $upload_dir['baseurl'] . '/foto-nano/temp/' . $filename,
        ) );
    }

    /**
     * AJAX: Generar imagen con IA.
     */
    public function ajax_generate() {
        check_ajax_referer( 'foto_nano_nonce', 'nonce' );

        $mode     = sanitize_text_field( $_POST['mode'] ?? '' );
        $photo    = sanitize_file_name( $_POST['photo'] ?? '' );
        $formato  = sanitize_text_field( $_POST['formato'] ?? '1:1' );
        $options  = isset( $_POST['options'] ) ? $_POST['options'] : array();

        if ( empty( $mode ) || empty( $photo ) ) {
            wp_send_json_error( array( 'message' => 'Faltan datos requeridos.' ) );
        }

        $upload_dir = wp_upload_dir();
        $face_path = $upload_dir['basedir'] . '/foto-nano/temp/' . $photo;

        if ( ! file_exists( $face_path ) ) {
            wp_send_json_error( array( 'message' => 'Foto no encontrada.' ) );
        }

        $settings = get_option( 'foto_nano_settings', array() );
        $api = new Foto_Nano_Api( $settings['replicate_api_key'] ?? '' );

        // Componer imagen template segun modo
        $template_path = $this->compose_template( $mode, $options, $formato, $settings );

        if ( is_wp_error( $template_path ) ) {
            wp_send_json_error( array( 'message' => $template_path->get_error_message() ) );
        }

        // Enviar a Replicate para face-swap
        $result = $api->create_prediction( $face_path, $template_path );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'prediction_id' => $result['id'],
            'status'        => $result['status'],
        ) );
    }

    /**
     * AJAX: Verificar estado de prediccion.
     */
    public function ajax_check_status() {
        check_ajax_referer( 'foto_nano_nonce', 'nonce' );

        $prediction_id = sanitize_text_field( $_POST['prediction_id'] ?? '' );

        if ( empty( $prediction_id ) ) {
            wp_send_json_error( array( 'message' => 'ID de prediccion no proporcionado.' ) );
        }

        $settings = get_option( 'foto_nano_settings', array() );
        $api = new Foto_Nano_Api( $settings['replicate_api_key'] ?? '' );

        $result = $api->get_prediction( $prediction_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $response = array(
            'status' => $result['status'],
        );

        if ( $result['status'] === 'succeeded' ) {
            $output_url = is_array( $result['output'] ) ? $result['output'][0] : $result['output'];

            // Descargar imagen generada
            $upload_dir = wp_upload_dir();
            $gen_filename = 'foto_nano_' . uniqid() . '.jpg';
            $gen_path = $upload_dir['basedir'] . '/foto-nano/generated/' . $gen_filename;

            $image_data = wp_remote_get( $output_url );
            if ( ! is_wp_error( $image_data ) ) {
                file_put_contents( $gen_path, wp_remote_retrieve_body( $image_data ) );
                $response['image_url'] = $upload_dir['baseurl'] . '/foto-nano/generated/' . $gen_filename;
                $response['image_file'] = $gen_filename;
            }
        } elseif ( $result['status'] === 'failed' ) {
            $response['error'] = $result['error'] ?? 'La generacion fallo.';
        }

        wp_send_json_success( $response );
    }

    /**
     * AJAX: Enviar imagen por email.
     */
    public function ajax_send_email() {
        check_ajax_referer( 'foto_nano_nonce', 'nonce' );

        $email = sanitize_email( $_POST['email'] ?? '' );
        $image_file = sanitize_file_name( $_POST['image_file'] ?? '' );

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Correo electronico no valido.' ) );
        }

        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['basedir'] . '/foto-nano/generated/' . $image_file;

        if ( ! file_exists( $image_path ) ) {
            wp_send_json_error( array( 'message' => 'Imagen no encontrada.' ) );
        }

        $mailer = new Foto_Nano_Email();
        $result = $mailer->send( $email, $image_path );

        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Correo enviado correctamente.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Error al enviar el correo.' ) );
        }
    }

    /**
     * Componer imagen template segun el modo seleccionado.
     */
    private function compose_template( $mode, $options, $formato, $settings ) {
        $dimensions = $this->get_dimensions_from_format( $formato );
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        $canvas = imagecreatetruecolor( $width, $height );
        $white = imagecolorallocate( $canvas, 255, 255, 255 );
        imagefill( $canvas, 0, 0, $white );

        switch ( $mode ) {
            case 'mascota':
                $canvas = $this->compose_mascota( $canvas, $options, $settings, $width, $height );
                break;

            case 'fondo':
                $canvas = $this->compose_fondo( $canvas, $options, $settings, $width, $height );
                break;

            case 'postal':
                $canvas = $this->compose_postal( $canvas, $options, $settings, $width, $height );
                break;

            default:
                return new WP_Error( 'invalid_mode', 'Modo de generacion no valido.' );
        }

        if ( is_wp_error( $canvas ) ) {
            return $canvas;
        }

        $upload_dir = wp_upload_dir();
        $template_file = 'template_' . uniqid() . '.jpg';
        $template_path = $upload_dir['basedir'] . '/foto-nano/temp/' . $template_file;

        imagejpeg( $canvas, $template_path, 95 );
        imagedestroy( $canvas );

        return $template_path;
    }

    /**
     * Componer template: Persona con mascota.
     */
    private function compose_mascota( $canvas, $options, $settings, $width, $height ) {
        $mascota_id = sanitize_text_field( $options['mascota_id'] ?? '' );
        $mascotas = $settings['mascotas'] ?? array();

        // Buscar mascota seleccionada
        $mascota = null;
        foreach ( $mascotas as $m ) {
            if ( ( $m['id'] ?? '' ) === $mascota_id ) {
                $mascota = $m;
                break;
            }
        }

        if ( ! $mascota ) {
            return new WP_Error( 'no_mascota', 'Mascota no encontrada.' );
        }

        // Fondo
        $bg_path = $mascota['fondo'] ?? '';
        if ( $bg_path ) {
            $bg = $this->load_image( $bg_path );
            if ( $bg ) {
                imagecopyresampled( $canvas, $bg, 0, 0, 0, 0, $width, $height, imagesx( $bg ), imagesy( $bg ) );
                imagedestroy( $bg );
            }
        }

        // Mascota
        $mascota_path = $mascota['imagen'] ?? '';
        if ( $mascota_path ) {
            $pet_img = $this->load_image( $mascota_path );
            if ( $pet_img ) {
                $scale = ( $mascota['tamano'] ?? 30 ) / 100;
                $pet_w = (int) ( $width * $scale );
                $pet_h = (int) ( $pet_w * ( imagesy( $pet_img ) / imagesx( $pet_img ) ) );

                $position = $mascota['posicion'] ?? 'derecha';
                switch ( $position ) {
                    case 'izquierda':
                        $pet_x = (int) ( $width * 0.05 );
                        break;
                    case 'centro':
                        $pet_x = (int) ( ( $width - $pet_w ) / 2 );
                        break;
                    default: // derecha
                        $pet_x = (int) ( $width - $pet_w - $width * 0.05 );
                        break;
                }
                $pet_y = $height - $pet_h - (int) ( $height * 0.05 );

                imagecopyresampled( $canvas, $pet_img, $pet_x, $pet_y, 0, 0, $pet_w, $pet_h, imagesx( $pet_img ), imagesy( $pet_img ) );
                imagedestroy( $pet_img );
            }
        }

        return $canvas;
    }

    /**
     * Componer template: Fondo escenico.
     */
    private function compose_fondo( $canvas, $options, $settings, $width, $height ) {
        $fondo_id = sanitize_text_field( $options['fondo_id'] ?? '' );
        $fondos = $settings['fondos'] ?? array();

        $fondo = null;
        foreach ( $fondos as $f ) {
            if ( ( $f['id'] ?? '' ) === $fondo_id ) {
                $fondo = $f;
                break;
            }
        }

        if ( ! $fondo ) {
            return new WP_Error( 'no_fondo', 'Fondo no encontrado.' );
        }

        $bg_path = $fondo['imagen'] ?? '';
        if ( $bg_path ) {
            $bg = $this->load_image( $bg_path );
            if ( $bg ) {
                imagecopyresampled( $canvas, $bg, 0, 0, 0, 0, $width, $height, imagesx( $bg ), imagesy( $bg ) );
                imagedestroy( $bg );
            }
        }

        return $canvas;
    }

    /**
     * Componer template: Postal.
     */
    private function compose_postal( $canvas, $options, $settings, $width, $height ) {
        // Fondo
        $fondo_id = sanitize_text_field( $options['fondo_id'] ?? '' );
        $fondos = $settings['fondos'] ?? array();
        foreach ( $fondos as $f ) {
            if ( ( $f['id'] ?? '' ) === $fondo_id ) {
                $bg_path = $f['imagen'] ?? '';
                if ( $bg_path ) {
                    $bg = $this->load_image( $bg_path );
                    if ( $bg ) {
                        imagecopyresampled( $canvas, $bg, 0, 0, 0, 0, $width, $height, imagesx( $bg ), imagesy( $bg ) );
                        imagedestroy( $bg );
                    }
                }
                break;
            }
        }

        // Mascota en postal
        $mascota_id = sanitize_text_field( $options['mascota_id'] ?? '' );
        if ( $mascota_id ) {
            $mascotas = $settings['mascotas'] ?? array();
            foreach ( $mascotas as $m ) {
                if ( ( $m['id'] ?? '' ) === $mascota_id ) {
                    $pet_path = $m['imagen'] ?? '';
                    if ( $pet_path ) {
                        $pet_img = $this->load_image( $pet_path );
                        if ( $pet_img ) {
                            $scale = ( $m['tamano'] ?? 25 ) / 100;
                            $pet_w = (int) ( $width * $scale );
                            $pet_h = (int) ( $pet_w * ( imagesy( $pet_img ) / imagesx( $pet_img ) ) );
                            $pet_x = $width - $pet_w - (int) ( $width * 0.05 );
                            $pet_y = $height - $pet_h - (int) ( $height * 0.15 );
                            imagecopyresampled( $canvas, $pet_img, $pet_x, $pet_y, 0, 0, $pet_w, $pet_h, imagesx( $pet_img ), imagesy( $pet_img ) );
                            imagedestroy( $pet_img );
                        }
                    }
                    break;
                }
            }
        }

        // Marco
        $marco_id = sanitize_text_field( $options['marco_id'] ?? '' );
        if ( $marco_id ) {
            $marcos = $settings['postal_marcos'] ?? array();
            foreach ( $marcos as $mk ) {
                if ( ( $mk['id'] ?? '' ) === $marco_id ) {
                    $marco_path = $mk['imagen'] ?? '';
                    if ( $marco_path ) {
                        $marco_img = $this->load_image( $marco_path );
                        if ( $marco_img ) {
                            imagecopyresampled( $canvas, $marco_img, 0, 0, 0, 0, $width, $height, imagesx( $marco_img ), imagesy( $marco_img ) );
                            imagedestroy( $marco_img );
                        }
                    }
                    break;
                }
            }
        }

        // Texto postal
        $nombre = sanitize_text_field( $options['nombre'] ?? '' );
        $texto = sanitize_text_field( $options['texto_postal'] ?? ( $settings['postal_texto_defecto'] ?? '' ) );
        $font_size = (int) ( $settings['postal_fuente_size'] ?? 24 );
        $color_hex = $settings['postal_color_texto'] ?? '#ffffff';

        $r = hexdec( substr( $color_hex, 1, 2 ) );
        $g = hexdec( substr( $color_hex, 3, 2 ) );
        $b = hexdec( substr( $color_hex, 5, 2 ) );
        $text_color = imagecolorallocate( $canvas, $r, $g, $b );
        $shadow_color = imagecolorallocate( $canvas, 0, 0, 0 );

        // Nombre de la persona
        if ( $nombre ) {
            $name_y = $height - (int) ( $height * 0.08 );
            $name_x = (int) ( $width * 0.05 );

            // Sombra
            imagestring( $canvas, 5, $name_x + 1, $name_y + 1, $nombre, $shadow_color );
            imagestring( $canvas, 5, $name_x, $name_y, $nombre, $text_color );
        }

        // Texto de la postal
        if ( $texto ) {
            $text_y = $height - (int) ( $height * 0.04 );
            $text_x = (int) ( $width * 0.05 );

            imagestring( $canvas, 4, $text_x + 1, $text_y + 1, $texto, $shadow_color );
            imagestring( $canvas, 4, $text_x, $text_y, $texto, $text_color );
        }

        return $canvas;
    }

    /**
     * Cargar imagen desde path o URL.
     */
    private function load_image( $path ) {
        // Si es URL, convertir a path del servidor
        $path = $this->url_to_path( $path );

        if ( ! file_exists( $path ) ) {
            return false;
        }

        $info = getimagesize( $path );
        if ( ! $info ) {
            return false;
        }

        switch ( $info[2] ) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg( $path );
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng( $path );
                if ( $img ) {
                    imagealphablending( $img, true );
                    imagesavealpha( $img, true );
                }
                return $img;
            case IMAGETYPE_GIF:
                return imagecreatefromgif( $path );
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp( $path );
            default:
                return false;
        }
    }

    /**
     * Convertir URL a path del servidor.
     */
    private function url_to_path( $url ) {
        if ( empty( $url ) || strpos( $url, 'http' ) !== 0 ) {
            return $url; // Ya es un path
        }
        $upload_dir = wp_upload_dir();
        return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
    }

    /**
     * Obtener dimensiones segun formato.
     */
    private function get_dimensions_from_format( $formato ) {
        $base = 1080;
        switch ( $formato ) {
            case '9:16':
                return array( 'width' => 1080, 'height' => 1920 );
            case '16:9':
                return array( 'width' => 1920, 'height' => 1080 );
            case '4:3':
                return array( 'width' => 1440, 'height' => 1080 );
            case '3:4':
                return array( 'width' => 1080, 'height' => 1440 );
            case '1:1':
            default:
                return array( 'width' => 1080, 'height' => 1080 );
        }
    }
}
