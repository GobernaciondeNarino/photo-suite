<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Provider_HuggingFace extends Foto_Nano_Provider_Base {

    protected $provider_id = 'huggingface';
    protected $provider_name = 'Hugging Face';
    private $base_url = 'https://api-inference.huggingface.co';

    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Token de Hugging Face no configurado.' );
        }

        $model = $params['model'] ?? 'stabilityai/stable-diffusion-xl-base-1.0';

        // Para modelos de image-to-image / face-swap
        $source_base64 = $this->image_to_base64( $source_face_path );
        $target_base64 = $this->image_to_base64( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $body = array(
            'inputs' => array(
                'source_image' => $source_base64,
                'target_image' => $target_base64,
            ),
            'parameters' => array(
                'guidance_scale' => 7.5,
                'num_inference_steps' => 50,
            ),
            'options' => array(
                'wait_for_model' => true,
            ),
        );

        $response = $this->make_request( $this->base_url . '/models/' . $model, array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 120,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );

        // HF puede devolver la imagen directamente como binary
        if ( $code === 200 && strpos( $content_type, 'image/' ) !== false ) {
            $image_data = wp_remote_retrieve_body( $response );
            $result = $this->save_generated_image( $image_data );

            if ( ! $result ) {
                return new WP_Error( 'save_error', 'Error al guardar la imagen generada.' );
            }

            return array(
                'prediction_id' => $result['filename'],
                'status'        => 'succeeded',
                'provider'      => $this->provider_id,
                'output'        => $result['url'],
                'image_file'    => $result['filename'],
                'synchronous'   => true,
            );
        }

        // Respuesta JSON (modelo en cola o error)
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 503 && isset( $data['estimated_time'] ) ) {
            // Modelo cargando
            return array(
                'prediction_id' => 'hf_' . uniqid(),
                'status'        => 'starting',
                'provider'      => $this->provider_id,
                'estimated_time' => $data['estimated_time'],
                'model'         => $model,
                '_source_path'  => $source_face_path,
                '_target_path'  => $target_image_path,
            );
        }

        if ( $code !== 200 ) {
            $error_msg = $data['error'] ?? 'Error desconocido de Hugging Face.';
            return new WP_Error( 'api_error', 'Hugging Face: ' . $error_msg );
        }

        // Respuesta JSON con URL
        if ( isset( $data[0]['generated_image'] ) ) {
            return array(
                'prediction_id' => 'hf_' . uniqid(),
                'status'        => 'succeeded',
                'provider'      => $this->provider_id,
                'output'        => $data[0]['generated_image'],
                'synchronous'   => true,
            );
        }

        return new WP_Error( 'api_error', 'Respuesta inesperada de Hugging Face.' );
    }

    public function get_prediction( $prediction_id ) {
        // HF es mayormente sincrono - las respuestas llegan directamente
        // Si el modelo estaba cargando, re-enviar la solicitud
        return array(
            'status' => 'processing',
            'output' => null,
            'error'  => null,
        );
    }

    public function is_synchronous() {
        return true;
    }

    public function validate_api_key() {
        $response = $this->make_request( 'https://huggingface.co/api/whoami', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    private function save_generated_image( $image_data ) {
        $upload_dir = wp_upload_dir();
        $filename = 'foto_nano_hf_' . uniqid() . '.png';
        $filepath = $upload_dir['basedir'] . '/foto-nano/generated/' . $filename;

        if ( file_put_contents( $filepath, $image_data ) === false ) {
            return false;
        }

        return array(
            'filename' => $filename,
            'path'     => $filepath,
            'url'      => $upload_dir['baseurl'] . '/foto-nano/generated/' . $filename,
        );
    }
}
