<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Provider_OpenAI extends Foto_Nano_Provider_Base {

    protected $provider_id = 'openai';
    protected $provider_name = 'OpenAI';
    private $base_url = 'https://api.openai.com/v1';

    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key de OpenAI no configurada.' );
        }

        $model = $params['model'] ?? 'gpt-image-1';

        // OpenAI Images Edit: combinar rostro con template
        $source_base64 = $this->image_to_base64( $source_face_path );
        $target_base64 = $this->image_to_base64( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $prompt = $params['prompt'] ?? 'Seamlessly blend the person\'s face from the first image into the second image, maintaining natural lighting, skin tones, and proportions. Keep the background and composition of the target image intact.';

        // Usar Images Edit endpoint
        $body = array(
            'model'  => $model,
            'prompt' => $prompt,
            'image'  => array( $source_base64, $target_base64 ),
            'size'   => $this->get_size_from_format( $params['formato'] ?? '1:1' ),
        );

        $response = $this->make_request( $this->base_url . '/images/edits', array(
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
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $error_msg = $data['error']['message'] ?? 'Error desconocido de OpenAI.';
            return new WP_Error( 'api_error', 'OpenAI: ' . $error_msg );
        }

        // OpenAI devuelve la imagen directamente
        $image_url = '';
        $image_b64 = '';

        if ( ! empty( $data['data'][0]['url'] ) ) {
            $image_url = $data['data'][0]['url'];
        } elseif ( ! empty( $data['data'][0]['b64_json'] ) ) {
            $image_b64 = $data['data'][0]['b64_json'];
        }

        if ( $image_url ) {
            $saved = $this->download_image( $image_url );
        } elseif ( $image_b64 ) {
            $saved = $this->save_base64_image( $image_b64 );
        } else {
            return new WP_Error( 'api_error', 'OpenAI no devolvio una imagen.' );
        }

        if ( ! $saved ) {
            return new WP_Error( 'save_error', 'Error al guardar la imagen generada.' );
        }

        return array(
            'prediction_id' => $saved['filename'],
            'status'        => 'succeeded',
            'provider'      => $this->provider_id,
            'output'        => $saved['url'],
            'image_file'    => $saved['filename'],
            'synchronous'   => true,
        );
    }

    public function get_prediction( $prediction_id ) {
        // OpenAI es sincrono
        return array(
            'status' => 'succeeded',
            'output' => null,
            'error'  => null,
        );
    }

    public function is_synchronous() {
        return true;
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

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    private function get_size_from_format( $formato ) {
        switch ( $formato ) {
            case '9:16':
                return '1024x1792';
            case '16:9':
                return '1792x1024';
            default:
                return '1024x1024';
        }
    }

    private function save_base64_image( $b64 ) {
        $image_data = base64_decode( $b64 );
        if ( ! $image_data ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $filename = 'foto_nano_oai_' . uniqid() . '.png';
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
