<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Security {

    private static $cipher = 'aes-256-cbc';

    /**
     * Encriptar un valor sensible (API keys).
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::$cipher ) );
        $encrypted = openssl_encrypt( $value, self::$cipher, $key, 0, $iv );

        if ( $encrypted === false ) {
            return $value;
        }

        return base64_encode( $iv . '::' . $encrypted );
    }

    /**
     * Desencriptar un valor.
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $decoded = base64_decode( $value, true );
        if ( $decoded === false || strpos( $decoded, '::' ) === false ) {
            return $value; // No encriptado, retornar tal cual
        }

        $key = self::get_encryption_key();
        list( $iv, $encrypted ) = explode( '::', $decoded, 2 );
        $decrypted = openssl_decrypt( $encrypted, self::$cipher, $key, 0, $iv );

        return $decrypted !== false ? $decrypted : $value;
    }

    /**
     * Obtener clave de encriptacion.
     */
    private static function get_encryption_key() {
        if ( defined( 'FOTO_NANO_ENCRYPTION_KEY' ) ) {
            return FOTO_NANO_ENCRYPTION_KEY;
        }
        // Fallback: usar AUTH_KEY de WordPress
        return defined( 'AUTH_KEY' ) ? AUTH_KEY : 'foto-nano-default-key-change-me';
    }

    /**
     * Verificar rate limit por IP.
     */
    public static function check_rate_limit( $action = 'generate', $max_attempts = 10, $window = 3600 ) {
        $ip = self::get_client_ip();
        $transient_key = 'foto_nano_rl_' . md5( $ip . '_' . $action );
        $attempts = (int) get_transient( $transient_key );

        if ( $attempts >= $max_attempts ) {
            return false;
        }

        set_transient( $transient_key, $attempts + 1, $window );
        return true;
    }

    /**
     * Obtener intentos restantes.
     */
    public static function get_remaining_attempts( $action = 'generate', $max_attempts = 10 ) {
        $ip = self::get_client_ip();
        $transient_key = 'foto_nano_rl_' . md5( $ip . '_' . $action );
        $attempts = (int) get_transient( $transient_key );
        return max( 0, $max_attempts - $attempts );
    }

    /**
     * Obtener IP del cliente de forma segura.
     */
    private static function get_client_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $ips[0] );
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
    }

    /**
     * Validar archivo subido (tipo, tamano, contenido).
     */
    public static function validate_uploaded_file( $file, $max_size_mb = 10 ) {
        $errors = array();

        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'invalid_upload', 'Archivo no valido.' );
        }

        // Verificar tamano
        $max_bytes = $max_size_mb * 1024 * 1024;
        if ( $file['size'] > $max_bytes ) {
            return new WP_Error( 'file_too_large', 'El archivo excede el tamano maximo de ' . $max_size_mb . 'MB.' );
        }

        // Verificar tipo MIME real (no confiar en extension)
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime = $finfo->file( $file['tmp_name'] );
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

        if ( ! in_array( $mime, $allowed_mimes, true ) ) {
            return new WP_Error( 'invalid_type', 'Tipo de archivo no permitido. Solo se aceptan: JPG, PNG, GIF, WebP.' );
        }

        // Verificar que es realmente una imagen valida
        $image_info = @getimagesize( $file['tmp_name'] );
        if ( $image_info === false ) {
            return new WP_Error( 'invalid_image', 'El archivo no es una imagen valida.' );
        }

        // Verificar dimensiones maximas
        if ( $image_info[0] > 8192 || $image_info[1] > 8192 ) {
            return new WP_Error( 'image_too_large', 'La imagen excede las dimensiones maximas (8192x8192).' );
        }

        return true;
    }

    /**
     * Sanitizar nombre de archivo de forma segura.
     */
    public static function sanitize_filename( $filename ) {
        $filename = sanitize_file_name( $filename );
        // Eliminar doble extension y caracteres peligrosos
        $filename = preg_replace( '/\.(php|phtml|phar|js|html|htm|svg|sh|bash|exe|bat|cmd)(\.|$)/i', '.blocked$2', $filename );
        return $filename;
    }

    /**
     * Generar token CSRF adicional con tiempo de expiracion.
     */
    public static function generate_token( $action = 'foto_nano' ) {
        $token = wp_create_nonce( $action . '_' . session_id() );
        return $token;
    }

    /**
     * Agregar headers de seguridad.
     */
    public static function add_security_headers() {
        if ( headers_sent() ) {
            return;
        }
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    }

    /**
     * Limpiar archivos temporales antiguos (mas de 1 hora).
     */
    public static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/foto-nano/temp';

        if ( ! is_dir( $temp_dir ) ) {
            return;
        }

        $files = glob( $temp_dir . '/*' );
        $now = time();

        foreach ( $files as $file ) {
            if ( is_file( $file ) && ( $now - filemtime( $file ) ) > 3600 ) {
                @unlink( $file );
            }
        }
    }

    /**
     * Ofuscar API key para mostrar en admin.
     */
    public static function mask_api_key( $key ) {
        if ( empty( $key ) || strlen( $key ) < 8 ) {
            return str_repeat( '*', strlen( $key ) );
        }
        return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
    }
}
