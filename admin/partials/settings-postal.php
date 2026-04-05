<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php settings_errors( 'foto_nano' ); ?>

<div class="foto-nano-section">
    <h2>Configuracion de Postal</h2>
    <p class="description">Configura los marcos, textos y estilos para el modo Postal.</p>

    <h3>Texto y Estilo</h3>
    <table class="form-table">
        <tr>
            <th><label for="postal_texto_defecto">Texto por Defecto</label></th>
            <td>
                <input type="text" id="postal_texto_defecto" name="postal_texto_defecto"
                       value="<?php echo esc_attr( $settings['postal_texto_defecto'] ?? 'Recuerdo de mi visita' ); ?>"
                       class="regular-text">
                <p class="description">El usuario podra editar este texto al momento de generar la postal.</p>
            </td>
        </tr>
        <tr>
            <th><label for="postal_fuente_size">Tamano de Fuente</label></th>
            <td>
                <input type="number" id="postal_fuente_size" name="postal_fuente_size"
                       value="<?php echo esc_attr( $settings['postal_fuente_size'] ?? 24 ); ?>"
                       min="12" max="72" class="small-text"> px
            </td>
        </tr>
        <tr>
            <th><label for="postal_color_texto">Color del Texto</label></th>
            <td>
                <input type="color" id="postal_color_texto" name="postal_color_texto"
                       value="<?php echo esc_attr( $settings['postal_color_texto'] ?? '#ffffff' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="postal_posicion_nombre">Posicion del Nombre</label></th>
            <td>
                <select id="postal_posicion_nombre" name="postal_posicion_nombre">
                    <option value="top-left" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'top-left' ); ?>>Arriba Izquierda</option>
                    <option value="top-center" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'top-center' ); ?>>Arriba Centro</option>
                    <option value="top-right" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'top-right' ); ?>>Arriba Derecha</option>
                    <option value="bottom-left" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'bottom-left' ); ?>>Abajo Izquierda</option>
                    <option value="bottom-center" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'bottom-center' ); ?>>Abajo Centro</option>
                    <option value="bottom-right" <?php selected( $settings['postal_posicion_nombre'] ?? '', 'bottom-right' ); ?>>Abajo Derecha</option>
                </select>
            </td>
        </tr>
    </table>

    <h3>Marcos de Postal</h3>
    <div id="foto-nano-marcos-list">
        <?php
        $marcos = $settings['postal_marcos'] ?? array();
        if ( ! empty( $marcos ) ) :
            foreach ( $marcos as $i => $marco ) :
        ?>
            <div class="foto-nano-item-card" data-index="<?php echo $i; ?>">
                <div class="foto-nano-item-header">
                    <strong><?php echo esc_html( $marco['nombre'] ); ?></strong>
                    <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label>Nombre del Marco</label></th>
                        <td><input type="text" name="marco_nombre[]" value="<?php echo esc_attr( $marco['nombre'] ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label>Imagen del Marco</label></th>
                        <td>
                            <input type="hidden" name="marco_imagen[]" value="<?php echo esc_attr( $marco['imagen'] ); ?>" class="foto-nano-image-input">
                            <div class="foto-nano-image-preview">
                                <?php if ( $marco['imagen'] ) : ?>
                                    <img src="<?php echo esc_url( $marco['imagen'] ); ?>" style="max-width:150px;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                            <p class="description">Usa una imagen PNG con transparencia para mejores resultados.</p>
                        </td>
                    </tr>
                </table>
            </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <button type="button" id="foto-nano-add-marco" class="button button-secondary" style="margin-top:15px;">
        + Agregar Marco
    </button>
</div>

<template id="foto-nano-marco-template">
    <div class="foto-nano-item-card">
        <div class="foto-nano-item-header">
            <strong>Nuevo Marco</strong>
            <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
        </div>
        <table class="form-table">
            <tr>
                <th><label>Nombre del Marco</label></th>
                <td><input type="text" name="marco_nombre[]" value="" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>Imagen del Marco</label></th>
                <td>
                    <input type="hidden" name="marco_imagen[]" value="" class="foto-nano-image-input">
                    <div class="foto-nano-image-preview"></div>
                    <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                    <p class="description">Usa una imagen PNG con transparencia para mejores resultados.</p>
                </td>
            </tr>
        </table>
    </div>
</template>
