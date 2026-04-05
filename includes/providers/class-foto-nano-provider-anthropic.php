<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Provider_Anthropic extends Foto_Nano_Provider_Base {

    protected $provider_id = 'anthropic';
    protected $provider_name = 'Claude (Anthropic)';
    private $base_url = 'https://api.anthropic.com/v1';

    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key de Anthropic no configurada.' );
        }

        $model = $params['model'] ?? 'claude-sonnet-4-20250514';

        $source_base64 = $this->image_to_base64( $source_face_path );
        $target_base64 = $this->image_to_base64( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $source_mime = $this->get_mime_type( $source_face_path );
        $target_mime = $this->get_mime_type( $target_image_path );

        $prompt = $params['prompt'] ?? 'Analyze both images. The first image contains a person\'s face. The second image is a template/background scene. Generate a new image that seamlessly places the person from the first image into the scene of the second image, maintaining natural proportions, lighting, and style. Return the final composed image.';

        $body = array(
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => array(
                        array(
                            'type'   => 'image',
                            'source' => array(
                                'type'         => 'base64',
                                'media_type'   => $source_mime,
                                'data'         => $source_base64,
                            ),
                        ),
                        array(
                            'type'   => 'image',
                            'source' => array(
                                'type'         => 'base64',
                                'media_type'   => $target_mime,
                                'data'         => $target_base64,
                            ),
                        ),
                        array(
                            'type' => 'text',
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
        );

        $response = $this->make_request( $this->base_url . '/messages', array(
            'method'  => 'POST',
            'headers' => array(
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2024-10-22',
                'Content-Type'      => 'application/json',
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
            $error_msg = $data['error']['message'] ?? 'Error desconocido de Anthropic.';
            return new WP_Error( 'api_error', 'Anthropic: ' . $error_msg );
        }

        // Buscar imagen generada en la respuesta
        $image_data = $this->extract_image_from_response( $data );

        if ( $image_data ) {
            $saved = $this->save_generated_image( $image_data );
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

        // Si Claude no genero imagen, devolver analisis como texto
        $text_response = $this->extract_text_from_response( $data );
        return new WP_Error( 'no_image', 'Claude analizo las imagenes pero no genero una imagen: ' . $text_response );
    }

    public function get_prediction( $prediction_id ) {
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
        $response = $this->make_request( $this->base_url . '/messages', array(
            'method'  => 'POST',
            'headers' => array(
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2024-10-22',
                'Content-Type'      => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 10,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => 'ping' ),
                ),
            ) ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200;
    }

    private function extract_image_from_response( $data ) {
        if ( empty( $data['content'] ) ) {
            return null;
        }

        foreach ( $data['content'] as $block ) {
            if ( $block['type'] === 'image' && ! empty( $block['source']['data'] ) ) {
                return base64_decode( $block['source']['data'] );
            }
        }

        return null;
    }

    private function extract_text_from_response( $data ) {
        if ( empty( $data['content'] ) ) {
            return '';
        }

        $texts = array();
        foreach ( $data['content'] as $block ) {
            if ( $block['type'] === 'text' ) {
                $texts[] = $block['text'];
            }
        }

        return implode( ' ', $texts );
    }

    private function get_mime_type( $path ) {
        $info = getimagesize( $path );
        return $info ? $info['mime'] : 'image/jpeg';
    }

    private function save_generated_image( $image_data ) {
        $upload_dir = wp_upload_dir();
        $filename = 'foto_nano_claude_' . uniqid() . '.png';
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
