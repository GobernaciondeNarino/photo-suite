<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php settings_errors( 'foto_nano' ); ?>

<div class="foto-nano-section">
    <h2>API de Replicate</h2>
    <table class="form-table">
        <tr>
            <th><label for="replicate_api_key">API Key</label></th>
            <td>
                <input type="password" id="replicate_api_key" name="replicate_api_key"
                       value="<?php echo esc_attr( $settings['replicate_api_key'] ?? '' ); ?>"
                       class="regular-text" autocomplete="off">
                <p class="description">Obten tu API Key en <a href="https://replicate.com/account/api-tokens" target="_blank">replicate.com</a></p>
            </td>
        </tr>
        <tr>
            <th><label for="replicate_model">Modelo de Face-Swap</label></th>
            <td>
                <input type="text" id="replicate_model" name="replicate_model"
                       value="<?php echo esc_attr( $settings['replicate_model'] ?? 'lucataco/facefusion' ); ?>"
                       class="regular-text">
                <p class="description">Modelo de Replicate para face-swap (ej: lucataco/facefusion)</p>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2>Formatos de Imagen</h2>
    <table class="form-table">
        <tr>
            <th>Formatos Habilitados</th>
            <td>
                <?php
                $formatos = $settings['formatos_habilitados'] ?? array( '1:1' );
                $all_formats = array(
                    '1:1'  => '1:1 (Cuadrado)',
                    '9:16' => '9:16 (Vertical/Stories)',
                    '16:9' => '16:9 (Horizontal/Paisaje)',
                    '4:3'  => '4:3 (Clasico)',
                    '3:4'  => '3:4 (Retrato)',
                );
                foreach ( $all_formats as $val => $label ) :
                    $key = 'formato_' . str_replace( ':', '_', $val );
                    $checked = in_array( $val, $formatos ) ? 'checked' : '';
                ?>
                    <label style="display:block;margin-bottom:5px;">
                        <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php echo $checked; ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label for="formato_defecto">Formato por Defecto</label></th>
            <td>
                <select id="formato_defecto" name="formato_defecto">
                    <?php foreach ( $all_formats as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['formato_defecto'] ?? '1:1', $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2>Configuracion de Email</h2>
    <table class="form-table">
        <tr>
            <th><label for="email_remitente">Email Remitente</label></th>
            <td>
                <input type="email" id="email_remitente" name="email_remitente"
                       value="<?php echo esc_attr( $settings['email_remitente'] ?? get_option( 'admin_email' ) ); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="email_asunto">Asunto del Correo</label></th>
            <td>
                <input type="text" id="email_asunto" name="email_asunto"
                       value="<?php echo esc_attr( $settings['email_asunto'] ?? '' ); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="email_plantilla">Plantilla HTML del Correo</label></th>
            <td>
                <textarea id="email_plantilla" name="email_plantilla" rows="6" class="large-text"><?php echo esc_textarea( $settings['email_plantilla'] ?? '' ); ?></textarea>
                <p class="description">HTML que se incluira en el cuerpo del correo. La imagen se adjuntara automaticamente.</p>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2>Limites</h2>
    <table class="form-table">
        <tr>
            <th><label for="limite_generaciones">Generaciones por sesion</label></th>
            <td>
                <input type="number" id="limite_generaciones" name="limite_generaciones"
                       value="<?php echo esc_attr( $settings['limite_generaciones'] ?? 10 ); ?>"
                       min="1" max="100" class="small-text">
                <p class="description">Numero maximo de imagenes que un usuario puede generar por sesion.</p>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2>Shortcode</h2>
    <p>Usa el shortcode <code>[foto_nano]</code> en cualquier pagina o entrada para mostrar la aplicacion.</p>
</div>
