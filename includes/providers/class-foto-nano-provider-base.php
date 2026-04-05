<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase base abstracta para proveedores de API de generacion de imagenes.
 */
abstract class Foto_Nano_Provider_Base {

    protected $api_key;
    protected $provider_id;
    protected $provider_name;

    public function __construct( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * Crear una prediccion/generacion de imagen.
     *
     * @param string $source_face_path Path de la imagen con el rostro.
     * @param string $target_image_path Path de la imagen template/objetivo.
     * @param array  $params Parametros adicionales del proveedor.
     * @return array|WP_Error Datos de la prediccion o error.
     */
    abstract public function create_prediction( $source_face_path, $target_image_path, $params = array() );

    /**
     * Obtener el estado de una prediccion.
     *
     * @param string $prediction_id ID de la prediccion.
     * @return array|WP_Error
     */
    abstract public function get_prediction( $prediction_id );

    /**
     * Verificar si la API key es valida.
     *
     * @return bool|WP_Error
     */
    abstract public function validate_api_key();

    /**
     * Obtener el ID del proveedor.
     */
    public function get_id() {
        return $this->provider_id;
    }

    /**
     * Obtener el nombre del proveedor.
     */
    public function get_name() {
        return $this->provider_name;
    }

    /**
     * Verificar si el proveedor soporta generacion sincrona (sin polling).
     */
    public function is_synchronous() {
        return false;
    }

    /**
     * Convertir imagen a data URI base64.
     */
    protected function image_to_data_uri( $path ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        $data = file_get_contents( $path );
        $info = getimagesize( $path );

        if ( ! $info ) {
            return false;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode( $data );
    }

    /**
     * Convertir imagen a base64 puro (sin prefijo data URI).
     */
    protected function image_to_base64( $path ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        $data = file_get_contents( $path );
        return base64_encode( $data );
    }

    /**
     * Hacer request HTTP con manejo de errores estandar.
     */
    protected function make_request( $url, $args = array() ) {
        $defaults = array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        $args = wp_parse_args( $args, $defaults );
        $method = $args['method'] ?? 'GET';
        unset( $args['method'] );

        if ( $method === 'POST' ) {
            $response = wp_remote_post( $url, $args );
        } else {
            $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_connection_error',
                sprintf( 'Error de conexion con %s: %s', $this->provider_name, $response->get_error_message() )
            );
        }

        return $response;
    }

    /**
     * Descargar imagen desde URL y guardar localmente.
     */
    protected function download_image( $url ) {
        $upload_dir = wp_upload_dir();
        $gen_filename = 'foto_nano_' . uniqid() . '.jpg';
        $gen_path = $upload_dir['basedir'] . '/foto-nano/generated/' . $gen_filename;

        $response = wp_remote_get( $url, array( 'timeout' => 60 ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return false;
        }

        file_put_contents( $gen_path, $body );

        return array(
            'path'     => $gen_path,
            'filename' => $gen_filename,
            'url'      => $upload_dir['baseurl'] . '/foto-nano/generated/' . $gen_filename,
        );
    }
}
