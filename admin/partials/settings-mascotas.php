<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php settings_errors( 'foto_nano' ); ?>

<div class="foto-nano-section">
    <h2>Gestion de Mascotas</h2>
    <p class="description">Configura las imagenes de mascotas disponibles para los usuarios. Cada mascota se mostrara como opcion en la pantalla de generacion.</p>

    <div id="foto-nano-mascotas-list">
        <?php
        $mascotas = $settings['mascotas'] ?? array();
        if ( ! empty( $mascotas ) ) :
            foreach ( $mascotas as $i => $mascota ) :
        ?>
            <div class="foto-nano-item-card" data-index="<?php echo $i; ?>">
                <div class="foto-nano-item-header">
                    <strong><?php echo esc_html( $mascota['nombre'] ); ?></strong>
                    <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label>Nombre</label></th>
                        <td><input type="text" name="mascota_nombre[]" value="<?php echo esc_attr( $mascota['nombre'] ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label>Imagen de la Mascota</label></th>
                        <td>
                            <input type="hidden" name="mascota_imagen[]" value="<?php echo esc_attr( $mascota['imagen'] ); ?>" class="foto-nano-image-input">
                            <div class="foto-nano-image-preview">
                                <?php if ( $mascota['imagen'] ) : ?>
                                    <img src="<?php echo esc_url( $mascota['imagen'] ); ?>" style="max-width:150px;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Tamano relativo (%)</label></th>
                        <td>
                            <input type="range" name="mascota_tamano[]" value="<?php echo esc_attr( $mascota['tamano'] ?? 30 ); ?>" min="10" max="80" class="foto-nano-range">
                            <span class="foto-nano-range-value"><?php echo esc_html( $mascota['tamano'] ?? 30 ); ?>%</span>
                            <p class="description">Tamano de la mascota con respecto al ancho de la imagen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Posicion</label></th>
                        <td>
                            <select name="mascota_posicion[]">
                                <option value="izquierda" <?php selected( $mascota['posicion'] ?? '', 'izquierda' ); ?>>Izquierda</option>
                                <option value="centro" <?php selected( $mascota['posicion'] ?? '', 'centro' ); ?>>Centro</option>
                                <option value="derecha" <?php selected( $mascota['posicion'] ?? '', 'derecha' ); ?>>Derecha</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Imagen de Fondo</label></th>
                        <td>
                            <input type="hidden" name="mascota_fondo[]" value="<?php echo esc_attr( $mascota['fondo'] ?? '' ); ?>" class="foto-nano-image-input">
                            <div class="foto-nano-image-preview">
                                <?php if ( ! empty( $mascota['fondo'] ) ) : ?>
                                    <img src="<?php echo esc_url( $mascota['fondo'] ); ?>" style="max-width:150px;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button foto-nano-upload-btn">Seleccionar Fondo</button>
                            <p class="description">Fondo opcional para esta mascota.</p>
                        </td>
                    </tr>
                </table>
            </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <button type="button" id="foto-nano-add-mascota" class="button button-secondary" style="margin-top:15px;">
        + Agregar Mascota
    </button>
</div>

<template id="foto-nano-mascota-template">
    <div class="foto-nano-item-card">
        <div class="foto-nano-item-header">
            <strong>Nueva Mascota</strong>
            <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
        </div>
        <table class="form-table">
            <tr>
                <th><label>Nombre</label></th>
                <td><input type="text" name="mascota_nombre[]" value="" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>Imagen de la Mascota</label></th>
                <td>
                    <input type="hidden" name="mascota_imagen[]" value="" class="foto-nano-image-input">
                    <div class="foto-nano-image-preview"></div>
                    <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                </td>
            </tr>
            <tr>
                <th><label>Tamano relativo (%)</label></th>
                <td>
                    <input type="range" name="mascota_tamano[]" value="30" min="10" max="80" class="foto-nano-range">
                    <span class="foto-nano-range-value">30%</span>
                </td>
            </tr>
            <tr>
                <th><label>Posicion</label></th>
                <td>
                    <select name="mascota_posicion[]">
                        <option value="izquierda">Izquierda</option>
                        <option value="centro">Centro</option>
                        <option value="derecha" selected>Derecha</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Imagen de Fondo</label></th>
                <td>
                    <input type="hidden" name="mascota_fondo[]" value="" class="foto-nano-image-input">
                    <div class="foto-nano-image-preview"></div>
                    <button type="button" class="button foto-nano-upload-btn">Seleccionar Fondo</button>
                </td>
            </tr>
        </table>
    </div>
</template>
