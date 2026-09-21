document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('uploadForm');
    if (!form) {
        return;
    }

    var currentStep = 1;
    var totalSteps = 3;
    var stepCounter = document.querySelector('[data-step-count]');
    var stepItems = document.querySelectorAll('[data-stepper-item]');
    var stepPanels = document.querySelectorAll('[data-form-step]');

    var modal = document.querySelector('[data-upload-modal]');
    var progressBar = modal ? modal.querySelector('[data-progress-bar]') : null;
    var progressText = modal ? modal.querySelector('[data-progress-text]') : null;

    var submitButton = form.querySelector('button[type="submit"]');

    function updateStepper() {
        stepItems.forEach(function (item) {
            var step = parseInt(item.getAttribute('data-step'), 10);
            var circle = item.querySelector('[data-step-circle]');
            var label = item.querySelector('[data-step-label]');

            if (!circle || !label) {
                return;
            }

            var baseCircle = 'flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition';
            var baseLabel = 'text-[11px] font-semibold uppercase tracking-[0.2em]';

            if (step < currentStep) {
                circle.className = baseCircle + ' border-emerald-400/40 bg-emerald-500/20 text-emerald-200';
                label.className = baseLabel + ' text-emerald-200';
            } else if (step === currentStep) {
                circle.className = baseCircle + ' border-accent bg-accent text-white shadow-elev-1';
                label.className = baseLabel + ' text-text';
            } else {
                circle.className = baseCircle + ' border-white/10 bg-white/5 text-muted';
                label.className = baseLabel + ' text-muted';
            }

            if (step === currentStep) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    function showStep(step) {
        if (step < 1 || step > totalSteps) {
            return;
        }

        currentStep = step;
        stepPanels.forEach(function (panel) {
            var panelStep = panel.getAttribute('data-form-step');
            panel.classList.toggle('hidden', panelStep !== String(currentStep));
        });

        if (stepCounter) {
            stepCounter.textContent = String(currentStep);
        }

        updateStepper();

        if (currentStep === 3) {
            updateSummary();
        }

        var formTop = form.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({ top: Math.max(0, formTop - 120), behavior: 'smooth' });
    }

    function setFieldState(field, isValid) {
        if (!field) {
            return;
        }
        var invalidClasses = ['border-rose-400/60', 'ring-2', 'ring-rose-500/30'];
        if (isValid) {
            field.classList.remove.apply(field.classList, invalidClasses);
            field.removeAttribute('aria-invalid');
        } else {
            field.classList.add.apply(field.classList, invalidClasses);
            field.setAttribute('aria-invalid', 'true');
        }
    }

    function setDropzoneState(zone, state) {
        if (!zone) {
            return;
        }
        zone.classList.remove('border-accent/60', 'bg-accent/10', 'border-emerald-400/60', 'bg-emerald-500/10', 'border-rose-400/60', 'bg-rose-500/10');
        if (state === 'drag') {
            zone.classList.add('border-accent/60', 'bg-accent/10');
        } else if (state === 'has-file') {
            zone.classList.add('border-emerald-400/60', 'bg-emerald-500/10');
        } else if (state === 'error') {
            zone.classList.add('border-rose-400/60', 'bg-rose-500/10');
        }
    }

    function validateStep(step) {
        var panel = document.querySelector('[data-form-step="' + step + '"]');
        if (!panel) {
            return true;
        }

        var requiredFields = panel.querySelectorAll('[required]');
        var firstInvalid = null;

        requiredFields.forEach(function (field) {
            var valid = true;
            if (field.type === 'checkbox') {
                valid = field.checked;
            } else if (field.type === 'file') {
                valid = field.files && field.files.length > 0;
                var zone = field.closest('[data-dropzone]');
                if (zone) {
                    setDropzoneState(zone, valid ? 'has-file' : 'error');
                }
            } else if (field.tagName === 'SELECT') {
                valid = field.value !== '';
            } else {
                valid = field.value.trim() !== '';
            }

            if (!valid && !firstInvalid) {
                firstInvalid = field;
            }

            if (field.type !== 'file') {
                setFieldState(field, valid);
            }
        });

        if (firstInvalid) {
            if (firstInvalid.type !== 'file') {
                firstInvalid.focus();
                if (typeof firstInvalid.reportValidity === 'function') {
                    firstInvalid.reportValidity();
                }
            }
            return false;
        }

        return true;
    }

    function formatFileSize(bytes) {
        var size = bytes / (1024 * 1024);
        return size.toFixed(size >= 10 ? 0 : 1) + ' MB';
    }

    function showToast(message, tone) {
        var toneClasses = {
            success: 'border-emerald-400/40 bg-emerald-500/20 text-emerald-100',
            error: 'border-rose-400/40 bg-rose-500/20 text-rose-100',
            info: 'border-sky-400/40 bg-sky-500/20 text-sky-100'
        };

        var toast = document.createElement('div');
        toast.className = 'fixed right-6 top-24 z-50 w-80 rounded-2xl border p-4 text-sm shadow-elev-2 transition';
        toast.classList.add('opacity-0');
        toast.className += ' ' + (toneClasses[tone] || toneClasses.info);
        toast.innerHTML = '<div class="flex items-start gap-3"><i class="fas fa-bell mt-0.5"></i><span>' + message + '</span></div>';

        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.remove('opacity-0');
        });

        setTimeout(function () {
            toast.classList.add('opacity-0');
        }, 2500);

        setTimeout(function () {
            toast.remove();
        }, 3000);
    }

    function setupDropzone(type, options) {
        var zone = document.querySelector('[data-dropzone="' + type + '"]');
        if (!zone) {
            return;
        }

        var input = zone.querySelector('[data-file-input]');
        var placeholder = zone.querySelector('[data-file-placeholder]');
        var info = zone.querySelector('[data-file-info]');
        var nameEl = zone.querySelector('[data-file-name]');
        var metaEl = zone.querySelector('[data-file-meta]');
        var preview = zone.querySelector('[data-file-preview]');
        var removeBtn = zone.querySelector('[data-file-remove]');

        function clearFile() {
            if (input) {
                input.value = '';
            }
            zone.dataset.hasFile = '';
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }
            if (info) {
                info.classList.add('hidden');
            }
            if (preview) {
                preview.src = '';
            }
            setDropzoneState(zone, 'default');
        }

        function handleFile(file) {
            if (options.type && !file.type.startsWith(options.type + '/')) {
                showToast('Format de fichier invalide.', 'error');
                return;
            }
            if (options.maxSize && file.size > options.maxSize) {
                showToast(options.errorMessage || 'Fichier trop volumineux.', 'error');
                return;
            }

            if (input) {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            }

            if (nameEl) {
                nameEl.textContent = file.name;
            }
            if (metaEl) {
                metaEl.textContent = formatFileSize(file.size);
            }

            if (options.preview && preview) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    preview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }

            zone.dataset.hasFile = 'true';
            if (placeholder) {
                placeholder.classList.add('hidden');
            }
            if (info) {
                info.classList.remove('hidden');
                info.classList.add('flex');
            }
            setDropzoneState(zone, 'has-file');
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                clearFile();
            });
        }

        zone.addEventListener('click', function (event) {
            if (event.target.closest('[data-file-remove]')) {
                return;
            }
            if (input) {
                input.click();
            }
        });

        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            setDropzoneState(zone, 'drag');
        });

        zone.addEventListener('dragleave', function () {
            if (zone.dataset.hasFile) {
                setDropzoneState(zone, 'has-file');
            } else {
                setDropzoneState(zone, 'default');
            }
        });

        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            var files = event.dataTransfer.files;
            if (files && files[0]) {
                handleFile(files[0]);
            }
        });

        if (input) {
            input.addEventListener('change', function () {
                if (input.files && input.files[0]) {
                    handleFile(input.files[0]);
                }
            });
        }
    }

    function updateDescriptionCount() {
        var description = document.getElementById('description');
        var counter = document.querySelector('[data-description-count]');
        if (!description || !counter) {
            return;
        }
        counter.textContent = String(description.value.length);
    }

    function updateDistribution() {
        var priceWrapper = document.querySelector('[data-price-wrapper]');
        var priceInput = document.getElementById('price');
        var selected = document.querySelector('input[name="distribution"]:checked');

        if (!priceWrapper || !priceInput) {
            return;
        }

        if (selected && selected.value === 'paid') {
            priceWrapper.classList.remove('hidden');
            priceInput.required = true;
        } else {
            priceWrapper.classList.add('hidden');
            priceInput.required = false;
            priceInput.value = '';
        }

        updateSummary();
    }

    function updateSummary() {
        var title = document.getElementById('title');
        var artist = document.getElementById('artist');
        var genre = document.getElementById('genre');
        var priceInput = document.getElementById('price');
        var distribution = document.querySelector('input[name="distribution"]:checked');

        var titleTarget = document.querySelector('[data-summary-title]');
        var artistTarget = document.querySelector('[data-summary-artist]');
        var genreTarget = document.querySelector('[data-summary-genre]');
        var distributionTarget = document.querySelector('[data-summary-distribution]');

        if (titleTarget && title) {
            titleTarget.textContent = title.value.trim() || '-';
        }
        if (artistTarget && artist) {
            artistTarget.textContent = artist.value.trim() || '-';
        }
        if (genreTarget && genre) {
            var genreLabel = genre.options[genre.selectedIndex] ? genre.options[genre.selectedIndex].text : '-';
            genreTarget.textContent = genreLabel || '-';
        }
        if (distributionTarget) {
            var distText = 'Gratuit';
            if (distribution) {
                if (distribution.value === 'premium') {
                    distText = 'Premium uniquement';
                } else if (distribution.value === 'paid') {
                    distText = priceInput && priceInput.value ? 'Payant (' + priceInput.value + ' FCFA)' : 'Payant (a definir)';
                }
            }
            distributionTarget.textContent = distText;
        }
    }

    function openModal() {
        if (!modal) {
            return;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }


    document.querySelectorAll('[data-step-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        });
    });

    document.querySelectorAll('[data-step-prev]').forEach(function (button) {
        button.addEventListener('click', function () {
            showStep(currentStep - 1);
        });
    });

    form.addEventListener('input', function (event) {
        var target = event.target;
        if (target && target.hasAttribute('required') && target.type !== 'file') {
            var isValid = target.type === 'checkbox' ? target.checked : target.value.trim() !== '';
            setFieldState(target, isValid);
        }
        if (currentStep === 3) {
            updateSummary();
        }
    });

    form.addEventListener('change', function (event) {
        if (currentStep === 3) {
            updateSummary();
        }
        if (event.target && event.target.name === 'distribution') {
            updateDistribution();
        }
    });

    form.addEventListener('submit', function (event) {
        if (!validateStep(currentStep)) {
            event.preventDefault();
            return;
        }
        if (currentStep !== totalSteps) {
            event.preventDefault();
            showStep(totalSteps);
            return;
        }
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.classList.add('opacity-70', 'cursor-not-allowed');
        }
        openModal();
    });

    var description = document.getElementById('description');
    if (description) {
        description.addEventListener('input', updateDescriptionCount);
        updateDescriptionCount();
    }

    document.querySelectorAll('input[name="distribution"]').forEach(function (input) {
        input.addEventListener('change', updateDistribution);
    });

    setupDropzone('audio', {
        type: 'audio',
        maxSize: 50 * 1024 * 1024,
        errorMessage: 'Le fichier audio ne doit pas depasser 50MB.'
    });

    setupDropzone('cover', {
        type: 'image',
        maxSize: 5 * 1024 * 1024,
        preview: true,
        errorMessage: 'L image ne doit pas depasser 5MB.'
    });

    updateStepper();
    updateDistribution();
});
