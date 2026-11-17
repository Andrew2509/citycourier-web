/**
 * City Courier — Studi Pendahuluan.
 * Mesin formulir multi-tahap: percabangan pertanyaan, validasi,
 * ringkasan jawaban, dan pengiriman tanpa memuat ulang halaman.
 */
(function () {
    'use strict';

    var form = document.getElementById('research-form');
    if (!form) return;

    var STEP_ORDER = ['identity', 'customer', 'courier', 'consent', 'review'];
    var stepEls = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var railEls = Array.prototype.slice.call(document.querySelectorAll('#steps-rail li'));

    var backBtn = document.getElementById('btn-back');
    var nextBtn = document.getElementById('btn-next');
    var submitBtn = document.getElementById('btn-submit');
    var submitLabel = submitBtn ? submitBtn.querySelector('[data-submit-label]') : null;
    var fillEl = document.getElementById('progress-fill');
    var currentEl = document.getElementById('progress-current');
    var totalEl = document.getElementById('progress-total');
    var barEl = document.querySelector('.progress-bar');
    var navMeta = document.getElementById('nav-meta');
    var alertBox = document.getElementById('form-alert');
    var announcer = document.getElementById('step-announcer');
    var reviewBody = document.getElementById('review-body');

    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var currentStep = 'identity';

    /* ── Helpers ─────────────────────────────────────────────────────── */

    function getStep(id) {
        for (var i = 0; i < stepEls.length; i++) {
            if (stepEls[i].getAttribute('data-step') === id) return stepEls[i];
        }
        return null;
    }

    function radioValue(name) {
        var el = form.querySelector('input[name="' + name + '"]:checked');
        return el ? el.value : '';
    }

    function checkedValues(name) {
        return Array.prototype.map.call(
            form.querySelectorAll('input[name="' + name + '"]:checked'),
            function (input) { return input.value; }
        );
    }

    // Cek apakah elemen berada di dalam panel kondisional yang disembunyikan,
    // tanpa menghitung section langkah itu sendiri (yang memang hidden saat review).
    function insideHiddenPanel(node, boundary) {
        var parent = node.parentElement;

        while (parent && parent !== boundary) {
            if (parent.hasAttribute('hidden')) return true;
            parent = parent.parentElement;
        }

        return false;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function isCustomerSection() {
        var role = radioValue('role');
        return (role === 'customer' || role === 'both') && radioValue('has_used_service') === 'ya';
    }

    function isCourierStep() {
        var role = radioValue('role');
        return role === 'courier' || role === 'both';
    }

    function activeSteps() {
        var role = radioValue('role');

        if (!role) return STEP_ORDER.slice();

        var list = ['identity'];
        if (isCustomerSection()) list.push('customer');
        if (isCourierStep()) list.push('courier');
        list.push('consent', 'review');

        return list;
    }

    /* ── Conditional fields ──────────────────────────────────────────── */

    function syncOtherFields() {
        Array.prototype.forEach.call(form.querySelectorAll('[data-reveals]'), function (checkbox) {
            var target = document.getElementById(checkbox.getAttribute('data-reveals'));
            if (target) target.hidden = !checkbox.checked;
        });
    }

    function syncConditionals() {
        var courierDetails = form.querySelector('[data-courier-details]');
        var courierSkipNote = form.querySelector('[data-courier-skip-note]');
        var contactQuestion = form.querySelector('[data-contact-question]');
        var status = radioValue('courier_status');
        var courierWorked = status === 'active' || status === 'former';

        if (courierDetails) courierDetails.hidden = !courierWorked;
        if (courierSkipNote) courierSkipNote.hidden = status !== 'never';
        if (contactQuestion) contactQuestion.hidden = radioValue('interview_willing') !== 'ya';

        syncOtherFields();
    }

    function syncOptionStates() {
        Array.prototype.forEach.call(form.querySelectorAll('.option'), function (option) {
            var input = option.querySelector('input');
            option.classList.toggle('is-checked', !!(input && input.checked));
        });
    }

    /* ── Rendering ───────────────────────────────────────────────────── */

    function render(options) {
        options = options || {};

        var list = activeSteps();
        if (list.indexOf(currentStep) === -1) currentStep = list[0];

        syncConditionals();

        stepEls.forEach(function (section) {
            section.hidden = section.getAttribute('data-step') !== currentStep;
        });

        var index = list.indexOf(currentStep);

        railEls.forEach(function (item) {
            var id = item.getAttribute('data-rail');
            var position = list.indexOf(id);
            item.hidden = position === -1;
            item.classList.toggle('is-current', id === currentStep);
            item.classList.toggle('is-done', position !== -1 && position < index);

            var dot = item.querySelector('.rail-dot');
            if (dot) dot.textContent = position === -1 ? '' : String(position + 1);
        });

        if (fillEl) fillEl.style.width = Math.round(((index + 1) / list.length) * 100) + '%';
        if (currentEl) currentEl.textContent = String(index + 1);
        if (totalEl) totalEl.textContent = String(list.length);

        if (barEl) {
            barEl.setAttribute('aria-valuenow', String(index + 1));
            barEl.setAttribute('aria-valuemax', String(list.length));
        }

        if (navMeta) navMeta.textContent = 'Langkah ' + (index + 1) + ' dari ' + list.length;

        if (backBtn) backBtn.hidden = index === 0;

        if (nextBtn) nextBtn.hidden = currentStep === 'review';
        if (submitBtn) submitBtn.hidden = currentStep !== 'review';

        if (currentStep === 'review') buildReview();

        if (announcer) {
            var step = getStep(currentStep);
            var title = step ? step.querySelector('.step-title') : null;
            announcer.textContent = 'Langkah ' + (index + 1) + ' dari ' + list.length +
                (title ? ': ' + title.textContent : '');
        }

        if (options.focus) focusStepHead();
    }

    function scrollToStep() {
        var step = getStep(currentStep);
        if (!step) return;
        step.scrollIntoView({
            behavior: prefersReduced ? 'auto' : 'smooth',
            block: 'start'
        });
    }

    function focusStepHead() {
        var step = getStep(currentStep);
        if (!step) return;
        var head = step.querySelector('.step-head');
        if (!head) return;
        try {
            head.focus({ preventScroll: true });
        } catch (error) {
            head.focus();
        }
    }

    function goTo(stepId) {
        if (STEP_ORDER.indexOf(stepId) === -1) return;
        currentStep = stepId;
        render({ focus: true });
        scrollToStep();
    }

    /* ── Validation ──────────────────────────────────────────────────── */

    function questionError(question) {
        var required = question.getAttribute('data-required') === 'true';
        var type = question.getAttribute('data-type');
        var name = question.getAttribute('data-name') || '';

        if (required) {
            if (type === 'radio' || type === 'scale') {
                if (!form.querySelector('input[name="' + name + '"]:checked')) {
                    return type === 'scale'
                        ? 'Pilih salah satu nilai dari 1 sampai 5.'
                        : 'Pilih salah satu jawaban untuk melanjutkan.';
                }
            } else if (type === 'checkbox') {
                if (checkedValues(name).length === 0) {
                    return 'Pilih minimal satu pilihan.';
                }
            } else if (type === 'text') {
                var textField = question.querySelector('input[type="text"]');
                if (textField && !textField.value.trim()) return 'Bagian ini wajib diisi.';
            } else if (type === 'textarea') {
                var area = question.querySelector('textarea');
                if (area && !area.value.trim()) return 'Bagian ini wajib diisi.';
            }
        }

        var otherField = question.querySelector('[data-other-field]');
        if (otherField && !otherField.hidden) {
            var otherInput = otherField.querySelector('input[type="text"]');
            if (otherInput && !otherInput.value.trim()) {
                return 'Lengkapi isian "Lainnya" atau hapus pilihannya.';
            }
        }

        return '';
    }

    function setQuestionError(question, message) {
        var errorEl = question.querySelector('.q-error');
        var hasError = message !== '';

        question.classList.toggle('has-error', hasError);

        if (errorEl) {
            errorEl.hidden = !hasError;
            errorEl.textContent = hasError ? message : '';
        }

        Array.prototype.forEach.call(question.querySelectorAll('input,textarea'), function (field) {
            if (hasError) {
                field.setAttribute('aria-invalid', 'true');
            } else {
                field.removeAttribute('aria-invalid');
            }
        });
    }

    function validateStep(stepId) {
        var step = getStep(stepId);
        if (!step) return true;

        var firstInvalid = null;

        Array.prototype.forEach.call(step.querySelectorAll('.q'), function (question) {
            if (insideHiddenPanel(question, step)) {
                setQuestionError(question, '');
                return;
            }

            var message = questionError(question);
            setQuestionError(question, message);

            if (message && !firstInvalid) firstInvalid = question;
        });

        if (firstInvalid) {
            firstInvalid.scrollIntoView({
                behavior: prefersReduced ? 'auto' : 'smooth',
                block: 'center'
            });

            var focusTarget = firstInvalid.querySelector('input:not([type="hidden"]), textarea');
            if (focusTarget) {
                window.setTimeout(function () {
                    try {
                        focusTarget.focus({ preventScroll: true });
                    } catch (error) {
                        focusTarget.focus();
                    }
                }, prefersReduced ? 0 : 240);
            }

            return false;
        }

        return true;
    }

    /* ── Review ──────────────────────────────────────────────────────── */

    function labelForQuestion(question) {
        var title = question.querySelector('.q-title');
        if (!title) return '';
        return title.textContent.replace(/\s*\*\s*$/, '').trim();
    }

    function readAnswer(question) {
        var type = question.getAttribute('data-type');
        var name = question.getAttribute('data-name') || '';

        if (type === 'radio' || type === 'scale') {
            var checked = form.querySelector('input[name="' + name + '"]:checked');
            if (!checked) return '';
            if (type === 'scale') return checked.value + ' / 5';

            var option = checked.closest('.option');
            var optionText = option ? option.querySelector('.option-text') : null;
            return optionText ? optionText.textContent.trim() : checked.value;
        }

        if (type === 'checkbox') {
            var parts = [];

            Array.prototype.forEach.call(question.querySelectorAll('.option'), function (option) {
                var input = option.querySelector('input');
                if (!input || !input.checked) return;

                var text = option.querySelector('.option-text');
                var answer = text ? text.textContent.trim() : input.value;

                if (input.getAttribute('data-reveals')) {
                    var extra = document.getElementById(input.getAttribute('data-reveals'));
                    var extraInput = extra ? extra.querySelector('input[type="text"]') : null;
                    if (extraInput && extraInput.value.trim()) {
                        answer += ': ' + extraInput.value.trim();
                    }
                }

                parts.push(answer);
            });

            return parts.join(', ');
        }

        if (type === 'text') {
            var textInput = question.querySelector('input[type="text"]');
            return textInput ? textInput.value.trim() : '';
        }

        if (type === 'textarea') {
            var textarea = question.querySelector('textarea');
            return textarea ? textarea.value.trim() : '';
        }

        return '';
    }

    function buildReview() {
        if (!reviewBody) return;

        reviewBody.innerHTML = '';

        var list = activeSteps().filter(function (id) { return id !== 'review'; });

        list.forEach(function (id, index) {
            var step = getStep(id);
            if (!step) return;

            var group = el('section', 'review-group');
            var head = el('div', 'review-group-head');

            head.appendChild(el('span', 'review-index', String(index + 1)));
            head.appendChild(el('h3', 'review-title', step.getAttribute('data-title') || 'Bagian ' + (index + 1)));

            var edit = el('button', 'review-edit', 'Ubah');
            edit.type = 'button';
            edit.setAttribute('data-edit-step', id);
            head.appendChild(edit);

            group.appendChild(head);

            var listEl = el('dl', 'review-list');

            Array.prototype.forEach.call(step.querySelectorAll('.q'), function (question) {
                if (insideHiddenPanel(question, step)) return;

                var row = el('div', 'review-row');
                row.appendChild(el('dt', 'review-label', labelForQuestion(question)));

                var value = el('dd', 'review-value');
                var answer = readAnswer(question);

                if (answer) {
                    value.textContent = answer;
                } else {
                    value.textContent = 'Tidak dijawab';
                    value.classList.add('is-empty');
                }

                row.appendChild(value);
                listEl.appendChild(row);
            });

            group.appendChild(listEl);
            reviewBody.appendChild(group);
        });

        var notes = buildNotes();
        if (notes.length) {
            var notesWrap = el('section', 'review-group');
            var notesHead = el('div', 'review-group-head');
            notesHead.appendChild(el('h3', 'review-title', 'Catatan bagian yang dilewati'));
            notesWrap.appendChild(notesHead);

            var notesList = el('ul', 'review-notes');
            notes.forEach(function (note) {
                notesList.appendChild(el('li', '', note));
            });

            notesWrap.appendChild(notesList);
            reviewBody.appendChild(notesWrap);
        }
    }

    function buildNotes() {
        var role = radioValue('role');
        var notes = [];

        if (!role) return notes;

        if (role === 'customer' && radioValue('has_used_service') !== 'ya') {
            notes.push('Bagian B (pertanyaan Customer) dilewati karena Anda belum pernah menggunakan layanan pengiriman barang.');
        }

        if (role === 'customer') {
            notes.push('Bagian C (pertanyaan Courier) tidak ditampilkan karena peran Anda hanya sebagai Customer.');
        }

        if (role === 'courier') {
            notes.push('Bagian B (pertanyaan Customer) tidak ditampilkan karena peran Anda hanya sebagai Courier.');
        }

        if ((role === 'courier' || role === 'both') && radioValue('courier_status') === 'never') {
            notes.push('Pertanyaan detail kurir (16 sampai 25) tidak ditampilkan karena Anda belum pernah bekerja sebagai kurir.');
        }

        if (radioValue('interview_willing') === 'tidak') {
            notes.push('Kontak wawancara tidak diisi karena Anda tidak bersedia dihubungi kembali.');
        }

        return notes;
    }

    /* ── Alerts & submitting ─────────────────────────────────────────── */

    function showAlert(messages) {
        if (!alertBox) return;

        alertBox.innerHTML = '';

        if (messages.length === 1) {
            alertBox.appendChild(el('p', '', messages[0]));
        } else {
            var wrap = el('div');
            wrap.appendChild(el('p', '', 'Periksa kembali jawaban berikut:'));
            var list = el('ul');
            messages.forEach(function (message) {
                list.appendChild(el('li', '', message));
            });
            wrap.appendChild(list);
            alertBox.appendChild(wrap);
        }

        alertBox.hidden = false;

        try {
            alertBox.focus({ preventScroll: true });
        } catch (error) {
            alertBox.focus();
        }

        alertBox.scrollIntoView({
            behavior: prefersReduced ? 'auto' : 'smooth',
            block: 'center'
        });
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.hidden = true;
        alertBox.innerHTML = '';
    }

    function setSubmitting(state) {
        if (submitBtn) {
            submitBtn.disabled = state;
            submitBtn.classList.toggle('is-loading', state);
            submitBtn.setAttribute('aria-busy', state ? 'true' : 'false');
        }

        if (submitLabel) {
            submitLabel.textContent = state ? 'Mengirim…' : 'Kirim formulir';
        }

        if (backBtn) backBtn.disabled = state;
        if (nextBtn) nextBtn.disabled = state;

        form.setAttribute('aria-busy', state ? 'true' : 'false');

        if (state) {
            form.dataset.submitting = 'true';
        } else {
            delete form.dataset.submitting;
        }
    }

    function submitForm() {
        hideAlert();
        setSubmitting(true);

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var json = null;
                    try { json = JSON.parse(text); } catch (error) { json = null; }

                    return { ok: response.ok, status: response.status, json: json };
                });
            })
            .then(function (result) {
                if (result.ok && result.json && result.json.redirect) {
                    window.location.assign(result.json.redirect);
                    return;
                }

                if (result.status === 419) {
                    showAlert(['Sesi halaman telah berakhir. Muat ulang halaman lalu isi kembali formulir.']);
                } else if (result.status === 422 && result.json && result.json.errors) {
                    var messages = [];
                    Object.keys(result.json.errors).forEach(function (key) {
                        messages = messages.concat(result.json.errors[key]);
                    });
                    showAlert(messages.length ? messages : ['Periksa kembali jawaban Anda.']);
                } else {
                    showAlert(['Formulir gagal dikirim karena kendala pada server. Silakan coba lagi beberapa saat lagi.']);
                }

                setSubmitting(false);
            })
            .catch(function () {
                showAlert(['Koneksi terputus. Periksa jaringan Anda, lalu kirim ulang formulir.']);
                setSubmitting(false);
            });
    }

    /* ── Navigation ──────────────────────────────────────────────────── */

    function handleNext() {
        if (!validateStep(currentStep)) return;

        var list = activeSteps();
        var index = list.indexOf(currentStep);
        var target = list[index + 1];

        if (target) goTo(target);
    }

    function handleBack() {
        var list = activeSteps();
        var index = list.indexOf(currentStep);
        var target = list[index - 1];

        if (target) goTo(target);
    }

    if (nextBtn) nextBtn.addEventListener('click', handleNext);
    if (backBtn) backBtn.addEventListener('click', handleBack);

    if (reviewBody) {
        reviewBody.addEventListener('click', function (event) {
            var button = event.target.closest('[data-edit-step]');
            if (!button) return;
            goTo(button.getAttribute('data-edit-step'));
        });
    }

    /* ── Events ──────────────────────────────────────────────────────── */

    form.addEventListener('change', function (event) {
        var question = event.target.closest('.q');
        if (question && question.classList.contains('has-error')) {
            setQuestionError(question, '');
        }

        syncOptionStates();

        if (event.target.matches('[data-reveals]')) {
            syncOtherFields();
        }

        var conditionalNames = ['role', 'has_used_service', 'courier_status', 'interview_willing'];
        if (conditionalNames.indexOf(event.target.name) !== -1) {
            render();
        } else {
            syncConditionals();
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.matches('[data-counter]')) {
            var counter = document.querySelector('[data-counter-for="' + event.target.id + '"]');
            if (counter) {
                counter.textContent = event.target.value.length + ' / ' + event.target.maxLength;
            }
        }

        var question = event.target.closest('.q');
        if (question && question.classList.contains('has-error')) {
            setQuestionError(question, '');
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (form.dataset.submitting === 'true') return;

        // Menekan Enter pada isian teks tidak boleh melewati halaman tinjauan.
        if (currentStep !== 'review') {
            handleNext();
            return;
        }

        var stepsToValidate = activeSteps().filter(function (id) { return id !== 'review'; });

        for (var i = 0; i < stepsToValidate.length; i++) {
            if (!validateStep(stepsToValidate[i])) {
                goTo(stepsToValidate[i]);
                return;
            }
        }

        submitForm();
    });

    // Jangan biarkan Enter pada isian satu baris memicu pengiriman dini.
    form.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        if (event.target.tagName !== 'INPUT' || event.target.type !== 'text') return;
        event.preventDefault();
        if (currentStep !== 'review') handleNext();
    });

    /* ── Intro panel ─────────────────────────────────────────────────── */

    var introToggle = document.getElementById('intro-toggle');
    var introBody = document.getElementById('intro-body');

    if (introToggle && introBody) {
        introToggle.addEventListener('click', function () {
            var expanded = introToggle.getAttribute('aria-expanded') === 'true';
            introToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            introBody.hidden = expanded;
            introToggle.textContent = expanded ? 'Baca selengkapnya' : 'Sembunyikan';
        });
    }

    /* ── Init ────────────────────────────────────────────────────────── */

    Array.prototype.forEach.call(form.querySelectorAll('[data-counter]'), function (textarea) {
        var counter = document.querySelector('[data-counter-for="' + textarea.id + '"]');
        if (counter) counter.textContent = textarea.value.length + ' / ' + textarea.maxLength;
    });

    syncOptionStates();
    syncConditionals();
    render();
})();
