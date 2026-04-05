(function($) {
    'use strict';

    var FotoNano = {
        state: {
            stream: null,
            photoBlob: null,
            photoFilename: null,
            selectedMode: null,
            selectedMascota: null,
            selectedFondo: null,
            selectedMarco: null,
            selectedFormato: null,
            activeFondoCategory: 'todos',
            predictionId: null,
            provider: null,
            pollTimer: null,
            progressValue: 0,
            progressTimer: null,
            generationCount: 0
        },

        init: function() {
            this.bindEvents();
            this.state.selectedFormato = (typeof fotoNanoData !== 'undefined') ? fotoNanoData.formatoDefecto : '1:1';
            this.state.provider = (typeof fotoNano !== 'undefined') ? fotoNano.provider : 'replicate';
            this.startCamera();
        },

        bindEvents: function() {
            var self = this;

            // Captura
            $('#foto-nano-btn-capture').on('click', function() { self.capturePhoto(); });
            $('#foto-nano-btn-retake').on('click', function() { self.retakePhoto(); });
            $('#foto-nano-btn-next-mode').on('click', function() { self.showScreen('mode'); });

            // Seleccion de modo
            $('.foto-nano-mode-card').on('click', function() {
                self.selectMode($(this).data('mode'));
            });

            // Volver
            $(document).on('click', '.foto-nano-btn-back', function() {
                self.showScreen($(this).data('back'));
            });

            // Generar
            $('#foto-nano-btn-generate').on('click', function() { self.generateImage(); });

            // Email
            $('#foto-nano-btn-send-email').on('click', function() { self.sendEmail(); });

            // Nueva foto
            $('#foto-nano-btn-new').on('click', function() { self.resetApp(); });

            // Grids
            $(document).on('click', '#foto-nano-mascota-grid .foto-nano-grid-item', function() {
                self.selectGridItem($(this), 'selectedMascota');
            });
            $(document).on('click', '#foto-nano-fondo-grid .foto-nano-grid-item', function() {
                self.selectGridItem($(this), 'selectedFondo');
            });
            $(document).on('click', '#foto-nano-marco-grid .foto-nano-grid-item', function() {
                self.selectGridItem($(this), 'selectedMarco');
            });

            // Formato
            $(document).on('click', '.foto-nano-format-option', function() {
                $('.foto-nano-format-option').removeClass('selected');
                $(this).addClass('selected');
                self.state.selectedFormato = $(this).data('format');
            });

            // Categoria fondos
            $(document).on('click', '.foto-nano-category-tab', function() {
                $('.foto-nano-category-tab').removeClass('active');
                $(this).addClass('active');
                self.state.activeFondoCategory = $(this).data('category');
                self.renderFondoGrid();
            });

            // Cerrar error con click
            $(document).on('click', '#foto-nano-error', function() {
                $(this).fadeOut(200);
            });

            // Teclado: Enter para email
            $('#foto-nano-email').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.sendEmail();
                }
            });
        },

        // --- Camera ---
        startCamera: function() {
            var self = this;
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
                    audio: false
                }).then(function(stream) {
                    self.state.stream = stream;
                    var video = document.getElementById('foto-nano-video');
                    if (video) {
                        video.srcObject = stream;
                    }
                }).catch(function() {
                    self.showError(fotoNano.strings.cameraError);
                });
            } else {
                self.showError(fotoNano.strings.cameraError);
            }
        },

        stopCamera: function() {
            if (this.state.stream) {
                this.state.stream.getTracks().forEach(function(track) { track.stop(); });
                this.state.stream = null;
            }
        },

        capturePhoto: function() {
            var video = document.getElementById('foto-nano-video');
            var canvas = document.getElementById('foto-nano-canvas');
            if (!video || !canvas) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            var ctx = canvas.getContext('2d');
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0);
            ctx.setTransform(1, 0, 0, 1, 0, 0);

            var self = this;
            canvas.toBlob(function(blob) {
                // Validar tamano del archivo
                var maxSize = (typeof fotoNano !== 'undefined' ? fotoNano.maxUploadSize : 10) * 1024 * 1024;
                if (blob.size > maxSize) {
                    self.showError(fotoNano.strings.fileTooLarge + (maxSize / 1024 / 1024) + 'MB');
                    return;
                }

                self.state.photoBlob = blob;
                var url = URL.createObjectURL(blob);
                $('#foto-nano-captured').attr('src', url);
                $('#foto-nano-preview-img').show();
                $('#foto-nano-video').hide();
                $('#foto-nano-btn-capture').hide();
                $('#foto-nano-btn-retake, #foto-nano-btn-next-mode').show();
            }, 'image/jpeg', 0.92);
        },

        retakePhoto: function() {
            this.state.photoBlob = null;
            $('#foto-nano-preview-img').hide();
            $('#foto-nano-video').show();
            $('#foto-nano-btn-capture').show();
            $('#foto-nano-btn-retake, #foto-nano-btn-next-mode').hide();

            if (!this.state.stream) {
                this.startCamera();
            }
        },

        // --- Navigation ---
        showScreen: function(screenName) {
            $('.foto-nano-screen').removeClass('foto-nano-screen--active');
            $('[data-screen="' + screenName + '"]').addClass('foto-nano-screen--active');

            if (screenName === 'capture' && !this.state.stream && !this.state.photoBlob) {
                this.startCamera();
            }

            // Scroll to top of container
            var container = document.getElementById('foto-nano-app');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        // --- Mode selection ---
        selectMode: function(mode) {
            this.state.selectedMode = mode;
            this.state.selectedMascota = null;
            this.state.selectedFondo = null;
            this.state.selectedMarco = null;

            this.setupOptionsScreen(mode);
            this.showScreen('options');
        },

        setupOptionsScreen: function(mode) {
            $('#foto-nano-opt-mascota, #foto-nano-opt-fondo, #foto-nano-opt-marco, #foto-nano-opt-nombre, #foto-nano-opt-texto').hide();

            switch (mode) {
                case 'mascota':
                    $('#foto-nano-options-title').text('Elige tu mascota');
                    $('#foto-nano-opt-mascota').show();
                    this.renderMascotaGrid();
                    break;

                case 'fondo':
                    $('#foto-nano-options-title').text('Elige un fondo escenico');
                    $('#foto-nano-opt-fondo').show();
                    this.renderFondoTabs();
                    this.renderFondoGrid();
                    break;

                case 'postal':
                    $('#foto-nano-options-title').text('Personaliza tu postal');
                    $('#foto-nano-opt-fondo, #foto-nano-opt-mascota, #foto-nano-opt-marco, #foto-nano-opt-nombre, #foto-nano-opt-texto').show();
                    this.renderMascotaGrid();
                    this.renderFondoTabs();
                    this.renderFondoGrid();
                    this.renderMarcoGrid();
                    $('#foto-nano-texto-postal').val(fotoNanoData.postalTexto || '');
                    break;
            }

            this.renderFormatSelector();
        },

        // --- Grid Renderers ---
        renderMascotaGrid: function() {
            var html = '';
            var mascotas = (typeof fotoNanoData !== 'undefined') ? fotoNanoData.mascotas : [];
            mascotas.forEach(function(m) {
                html += '<div class="foto-nano-grid-item" data-id="' + this.escapeHtml(m.id) + '">';
                html += '<img src="' + this.escapeHtml(m.imagen) + '" alt="' + this.escapeHtml(m.nombre) + '" loading="lazy">';
                html += '<div class="foto-nano-grid-item-name">' + this.escapeHtml(m.nombre) + '</div>';
                html += '</div>';
            }.bind(this));
            $('#foto-nano-mascota-grid').html(html || '<p style="color:var(--fn-text-muted);text-align:center;padding:20px;">No hay mascotas configuradas.</p>');
        },

        renderFondoTabs: function() {
            var categories = {
                'todos': 'Todos',
                'paisaje': 'Paisajes',
                'templo': 'Templos',
                'sitio-antiguo': 'Sitios Antiguos'
            };
            var html = '';
            for (var key in categories) {
                var active = (key === this.state.activeFondoCategory) ? ' active' : '';
                html += '<span class="foto-nano-category-tab' + active + '" data-category="' + key + '">' + categories[key] + '</span>';
            }
            $('#foto-nano-fondo-tabs').html(html);
        },

        renderFondoGrid: function() {
            var html = '';
            var fondos = (typeof fotoNanoData !== 'undefined') ? fotoNanoData.fondos : [];
            var category = this.state.activeFondoCategory;
            var self = this;

            fondos.forEach(function(f) {
                if (category !== 'todos' && f.categoria !== category) return;
                html += '<div class="foto-nano-grid-item" data-id="' + self.escapeHtml(f.id) + '">';
                html += '<img src="' + self.escapeHtml(f.imagen) + '" alt="' + self.escapeHtml(f.nombre) + '" loading="lazy">';
                html += '<div class="foto-nano-grid-item-name">' + self.escapeHtml(f.nombre) + '</div>';
                html += '</div>';
            });
            $('#foto-nano-fondo-grid').html(html || '<p style="color:var(--fn-text-muted);text-align:center;padding:20px;">No hay fondos configurados.</p>');
        },

        renderMarcoGrid: function() {
            var html = '';
            var marcos = (typeof fotoNanoData !== 'undefined') ? fotoNanoData.marcos : [];
            var self = this;
            marcos.forEach(function(m) {
                html += '<div class="foto-nano-grid-item" data-id="' + self.escapeHtml(m.id) + '">';
                html += '<img src="' + self.escapeHtml(m.imagen) + '" alt="' + self.escapeHtml(m.nombre) + '" loading="lazy">';
                html += '<div class="foto-nano-grid-item-name">' + self.escapeHtml(m.nombre) + '</div>';
                html += '</div>';
            });
            $('#foto-nano-marco-grid').html(html || '<p style="color:var(--fn-text-muted);text-align:center;padding:20px;">No hay marcos configurados.</p>');
        },

        renderFormatSelector: function() {
            var formatos = (typeof fotoNanoData !== 'undefined') ? fotoNanoData.formatos : ['1:1'];
            var selected = this.state.selectedFormato;
            var formatSizes = {
                '1:1':  { w: 30, h: 30 },
                '9:16': { w: 22, h: 40 },
                '16:9': { w: 40, h: 22 },
                '4:3':  { w: 36, h: 27 },
                '3:4':  { w: 27, h: 36 }
            };

            var html = '';
            formatos.forEach(function(f) {
                var size = formatSizes[f] || { w: 30, h: 30 };
                var sel = (f === selected) ? ' selected' : '';
                html += '<div class="foto-nano-format-option' + sel + '" data-format="' + f + '">';
                html += '<div class="foto-nano-format-preview" style="width:' + size.w + 'px;height:' + size.h + 'px;"></div>';
                html += '<span class="foto-nano-format-label">' + f + '</span>';
                html += '</div>';
            });
            $('#foto-nano-format-selector').html(html);
        },

        selectGridItem: function($item, stateKey) {
            $item.siblings().removeClass('selected');
            $item.addClass('selected');
            this.state[stateKey] = $item.data('id');
        },

        // --- Generation ---
        generateImage: function() {
            var self = this;
            var mode = this.state.selectedMode;

            if (!this.state.photoBlob) {
                this.showError('Primero debes tomar una foto.');
                return;
            }

            if (mode === 'mascota' && !this.state.selectedMascota) {
                this.showError(fotoNano.strings.selectPet);
                return;
            }

            if ((mode === 'fondo' || mode === 'postal') && !this.state.selectedFondo) {
                this.showError(fotoNano.strings.selectBg);
                return;
            }

            if (mode === 'postal' && !$('#foto-nano-nombre').val().trim()) {
                this.showError(fotoNano.strings.enterName);
                return;
            }

            // Subir foto primero
            this.showScreen('generating');
            this.startProgress();
            this.updateGeneratingStatus('Subiendo tu foto...');

            var formData = new FormData();
            formData.append('action', 'foto_nano_upload_photo');
            formData.append('nonce', fotoNano.nonce);
            formData.append('photo', this.state.photoBlob, 'photo.jpg');

            $.ajax({
                url: fotoNano.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.state.photoFilename = response.data.filename;
                        self.updateGeneratingStatus('Enviando a ' + (fotoNano.providerName || 'IA') + '...');
                        self.requestGeneration();
                    } else {
                        self.stopProgress();
                        self.showError(response.data.message || fotoNano.strings.errorGeneric);
                        self.showScreen('options');
                    }
                },
                error: function() {
                    self.stopProgress();
                    self.showError(fotoNano.strings.errorGeneric);
                    self.showScreen('options');
                }
            });
        },

        requestGeneration: function() {
            var self = this;
            var options = {};

            switch (this.state.selectedMode) {
                case 'mascota':
                    options.mascota_id = this.state.selectedMascota;
                    break;
                case 'fondo':
                    options.fondo_id = this.state.selectedFondo;
                    break;
                case 'postal':
                    options.fondo_id = this.state.selectedFondo;
                    options.mascota_id = this.state.selectedMascota || '';
                    options.marco_id = this.state.selectedMarco || '';
                    options.nombre = $('#foto-nano-nombre').val().trim();
                    options.texto_postal = $('#foto-nano-texto-postal').val().trim();
                    break;
            }

            $.ajax({
                url: fotoNano.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'foto_nano_generate',
                    nonce: fotoNano.nonce,
                    mode: this.state.selectedMode,
                    photo: this.state.photoFilename,
                    formato: this.state.selectedFormato,
                    options: options
                },
                success: function(response) {
                    if (response.success) {
                        self.state.predictionId = response.data.prediction_id;
                        self.state.provider = response.data.provider;

                        // Proveedor sincrono: resultado inmediato
                        if (response.data.image_url) {
                            self.completeProgress();
                            setTimeout(function() {
                                self.showResult(response.data.image_url, response.data.image_file);
                            }, 400);
                        } else {
                            // Proveedor asincrono: iniciar polling
                            self.updateGeneratingStatus('La IA esta procesando tu imagen...');
                            self.startPolling();
                        }
                    } else {
                        self.stopProgress();
                        self.showError(response.data.message || fotoNano.strings.errorGeneric);
                        self.showScreen('options');
                    }
                },
                error: function() {
                    self.stopProgress();
                    self.showError(fotoNano.strings.errorGeneric);
                    self.showScreen('options');
                }
            });
        },

        startPolling: function() {
            var self = this;
            this.state.pollTimer = setInterval(function() {
                self.checkStatus();
            }, 2000);
        },

        checkStatus: function() {
            var self = this;

            $.ajax({
                url: fotoNano.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'foto_nano_check_status',
                    nonce: fotoNano.nonce,
                    prediction_id: this.state.predictionId,
                    provider: this.state.provider
                },
                success: function(response) {
                    if (!response.success) {
                        self.stopPolling();
                        self.stopProgress();
                        self.showError(response.data.message || fotoNano.strings.errorGeneric);
                        self.showScreen('options');
                        return;
                    }

                    var data = response.data;

                    if (data.status === 'succeeded') {
                        self.stopPolling();
                        self.completeProgress();
                        setTimeout(function() {
                            self.showResult(data.image_url, data.image_file);
                        }, 500);
                    } else if (data.status === 'failed') {
                        self.stopPolling();
                        self.stopProgress();
                        self.showError(data.error || 'La generacion fallo.');
                        self.showScreen('options');
                    }
                },
                error: function() {
                    // No detener, reintentar
                }
            });
        },

        stopPolling: function() {
            if (this.state.pollTimer) {
                clearInterval(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        },

        // --- Progress bar ---
        startProgress: function() {
            var self = this;
            this.state.progressValue = 0;
            $('#foto-nano-progress-bar').css('width', '0%');

            this.state.progressTimer = setInterval(function() {
                if (self.state.progressValue < 90) {
                    self.state.progressValue += Math.random() * 4 + 1;
                    if (self.state.progressValue > 90) self.state.progressValue = 90;
                    $('#foto-nano-progress-bar').css('width', self.state.progressValue + '%');
                }
            }, 800);
        },

        completeProgress: function() {
            this.stopProgress();
            $('#foto-nano-progress-bar').css('width', '100%');
        },

        stopProgress: function() {
            if (this.state.progressTimer) {
                clearInterval(this.state.progressTimer);
                this.state.progressTimer = null;
            }
        },

        updateGeneratingStatus: function(msg) {
            $('#foto-nano-generating-status').text(msg);
        },

        // --- Result ---
        showResult: function(imageUrl, imageFile) {
            this.state.imageFile = imageFile;
            this.state.generationCount++;
            $('#foto-nano-result-img').attr('src', imageUrl);
            $('#foto-nano-btn-download').attr('href', imageUrl);
            $('#foto-nano-email-status').text('').removeClass('success error');
            this.showScreen('result');
        },

        // --- Email ---
        sendEmail: function() {
            var email = $('#foto-nano-email').val().trim();
            if (!email || !this.isValidEmail(email)) {
                $('#foto-nano-email-status').text('Ingresa un correo valido.').addClass('error').removeClass('success');
                return;
            }

            var self = this;
            var $btn = $('#foto-nano-btn-send-email');
            $btn.prop('disabled', true).text('Enviando...');

            $.ajax({
                url: fotoNano.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'foto_nano_send_email',
                    nonce: fotoNano.nonce,
                    email: email,
                    image_file: this.state.imageFile
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Enviar');
                    if (response.success) {
                        $('#foto-nano-email-status').text(fotoNano.strings.emailSent).addClass('success').removeClass('error');
                    } else {
                        $('#foto-nano-email-status').text(response.data.message || fotoNano.strings.emailError).addClass('error').removeClass('success');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Enviar');
                    $('#foto-nano-email-status').text(fotoNano.strings.emailError).addClass('error').removeClass('success');
                }
            });
        },

        isValidEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        // --- Reset ---
        resetApp: function() {
            this.stopPolling();
            this.stopProgress();
            this.state.photoBlob = null;
            this.state.photoFilename = null;
            this.state.selectedMode = null;
            this.state.selectedMascota = null;
            this.state.selectedFondo = null;
            this.state.selectedMarco = null;
            this.state.predictionId = null;
            this.state.activeFondoCategory = 'todos';

            $('#foto-nano-preview-img').hide();
            $('#foto-nano-video').show();
            $('#foto-nano-btn-capture').show();
            $('#foto-nano-btn-retake, #foto-nano-btn-next-mode').hide();
            $('#foto-nano-nombre').val('');
            $('#foto-nano-email').val('');

            this.startCamera();
            this.showScreen('capture');
        },

        // --- Security: escape HTML ---
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        // --- Error ---
        showError: function(msg) {
            $('#foto-nano-error-msg').text(msg);
            $('#foto-nano-error').fadeIn(200);
            setTimeout(function() {
                $('#foto-nano-error').fadeOut(200);
            }, 5000);
        }
    };

    $(document).ready(function() {
        if ($('#foto-nano-app').length) {
            FotoNano.init();
        }
    });

})(jQuery);
