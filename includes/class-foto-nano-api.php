<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Api {

    private $api_key;
    private $base_url = 'https://api.replicate.com/v1';

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Crear prediccion de face-swap en Replicate.
     */
    public function create_prediction( $source_face_path, $target_image_path ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key de Replicate no configurada. Ve a Ajustes > Foto-Nano.' );
        }

        // Subir imagenes como base64
        $source_base64 = $this->image_to_data_uri( $source_face_path );
        $target_base64 = $this->image_to_data_uri( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $settings = get_option( 'foto_nano_settings', array() );
        $model = $settings['replicate_model'] ?? 'lucataco/facefusion';

        $body = array(
            'version' => $this->get_model_version( $model ),
            'input'   => array(
                'source_image' => $source_base64,
                'target_image' => $target_base64,
            ),
        );

        $response = wp_remote_post( $this->base_url . '/predictions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Error de conexion con Replicate: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 201 ) {
            $error_msg = $data['detail'] ?? 'Error desconocido de la API.';
            return new WP_Error( 'api_error', 'Replicate API error: ' . $error_msg );
        }

        return $data;
    }

    /**
     * Obtener estado de una prediccion.
     */
    public function get_prediction( $prediction_id ) {
        $response = wp_remote_get( $this->base_url . '/predictions/' . $prediction_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Error al consultar el estado: ' . $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data;
    }

    /**
     * Obtener version del modelo.
     */
    private function get_model_version( $model ) {
        $cached = get_transient( 'foto_nano_model_version_' . md5( $model ) );
        if ( $cached ) {
            return $cached;
        }

        $response = wp_remote_get( $this->base_url . '/models/' . $model . '/versions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $version = $data['results'][0]['id'] ?? '';

        if ( $version ) {
            set_transient( 'foto_nano_model_version_' . md5( $model ), $version, DAY_IN_SECONDS );
        }

        return $version;
    }

    /**
     * Convertir imagen a data URI base64.
     */
    private function image_to_data_uri( $path ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        $data = file_get_contents( $path );
        $info = getimagesize( $path );

        if ( ! $info ) {
            return false;
        }

        $mime = $info['mime'];
        return 'data:' . $mime . ';base64,' . base64_encode( $data );
    }
}
