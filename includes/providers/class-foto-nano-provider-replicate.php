<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Provider_Replicate extends Foto_Nano_Provider_Base {

    protected $provider_id = 'replicate';
    protected $provider_name = 'Replicate';
    private $base_url = 'https://api.replicate.com/v1';

    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key de Replicate no configurada.' );
        }

        $source_base64 = $this->image_to_data_uri( $source_face_path );
        $target_base64 = $this->image_to_data_uri( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $model = $params['model'] ?? 'lucataco/facefusion';
        $version = $this->get_model_version( $model );

        $body = array(
            'version' => $version,
            'input'   => array(
                'source_image' => $source_base64,
                'target_image' => $target_base64,
            ),
        );

        $response = $this->make_request( $this->base_url . '/predictions', array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 201 ) {
            $error_msg = $data['detail'] ?? 'Error desconocido de la API.';
            return new WP_Error( 'api_error', 'Replicate: ' . $error_msg );
        }

        return array(
            'prediction_id' => $data['id'],
            'status'        => $data['status'],
            'provider'      => $this->provider_id,
        );
    }

    public function get_prediction( $prediction_id ) {
        $response = $this->make_request( $this->base_url . '/predictions/' . $prediction_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return array(
            'status' => $data['status'] ?? 'unknown',
            'output' => $data['output'] ?? null,
            'error'  => $data['error'] ?? null,
        );
    }

    public function validate_api_key() {
        $response = $this->make_request( $this->base_url . '/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200;
    }

    private function get_model_version( $model ) {
        $cached = get_transient( 'foto_nano_model_version_' . md5( $model ) );
        if ( $cached ) {
            return $cached;
        }

        $response = $this->make_request( $this->base_url . '/models/' . $model . '/versions', array(
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
}
