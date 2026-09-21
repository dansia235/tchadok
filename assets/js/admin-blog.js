(function () {
    'use strict';

    const titleInput = document.getElementById('blog-title');
    const slugInput = document.getElementById('blog-slug');
    const postFormAction = document.querySelector('form input[name="action"][value="save_post"]');
    const postForm = postFormAction ? postFormAction.closest('form') : null;

    let manualSlug = !!(slugInput && slugInput.value.trim() !== '');

    function toSlug(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function initSlugSync() {
        if (!titleInput || !slugInput) return;

        titleInput.addEventListener('input', () => {
            if (manualSlug) return;
            slugInput.value = toSlug(titleInput.value);
        });

        slugInput.addEventListener('input', () => {
            const generated = toSlug(titleInput.value);
            manualSlug = slugInput.value.trim() !== '' && slugInput.value.trim() !== generated;
        });
    }

    function updateWordCount(editor) {
        const content = editor.getContent({ format: 'text' });
        const wordCount = content.split(/\s+/).filter((word) => word.length > 0).length;
        const charCount = content.length;
        const readingTime = Math.ceil(wordCount / 200) || 1;

        const countDisplay = document.getElementById('editor-word-count');
        if (countDisplay) {
            countDisplay.innerHTML = `<i class="fas fa-font me-1"></i>${wordCount} mots | ${charCount} caracteres | ~${readingTime} min de lecture`;
        }
    }

    function initTinyMce() {
        if (typeof tinymce === 'undefined') return;

        const uploadUrl = (window.TCHADOK?.SITE_URL || '') + '/api/blog/upload-image.php';
        const csrfToken = window.TCHADOK?.CSRF_TOKEN || '';

        tinymce.init({
            selector: '#content-editor',
            height: 500,
            language: 'fr_FR',
            license_key: 'gpl',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
                'quickbars', 'autoresize'
            ],
            toolbar: 'undo redo | blocks fontfamily fontsize | ' +
                     'bold italic underline strikethrough | forecolor backcolor | ' +
                     'alignleft aligncenter alignright alignjustify | ' +
                     'bullist numlist outdent indent | ' +
                     'link image media table | ' +
                     'blockquote hr | pullquote infobox | removeformat code fullscreen help',
            toolbar_mode: 'sliding',
            quickbars_selection_toolbar: 'bold italic underline | quicklink h2 h3 blockquote',
            quickbars_insert_toolbar: 'quickimage quicktable hr',
            font_family_formats: 'Roboto=Roboto, sans-serif; Open Sans=Open Sans, sans-serif; Georgia=Georgia, serif; Arial=Arial, sans-serif; Times New Roman=Times New Roman, serif; Verdana=Verdana, sans-serif; Poppins=Poppins, sans-serif',
            font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt 36pt 48pt 72pt',
            block_formats: 'Paragraphe=p; Titre 2=h2; Titre 3=h3; Titre 4=h4; Citation=blockquote; Code=pre',
            color_cols: 8,
            custom_colors: true,
            color_map: [
                '003da5', 'Bleu primaire',
                'ce1126', 'Rouge primaire',
                'ffd700', 'Jaune accent',
                '333333', 'Noir',
                '666666', 'Gris fonce',
                '999999', 'Gris',
                'ffffff', 'Blanc',
                '28a745', 'Vert succes',
                'dc3545', 'Rouge danger',
                'ffc107', 'Jaune warning',
                '17a2b8', 'Bleu info',
                '6c757d', 'Gris secondary'
            ],
            content_style: `
                @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap');
                body { font-family: 'Roboto', sans-serif; font-size: 16px; line-height: 1.7; color: #333; padding: 20px; max-width: 800px; margin: 0 auto; }
                p { margin-bottom: 1rem; }
                h2 { font-size: 1.75rem; font-weight: 700; margin: 2rem 0 1rem; color: #222; }
                h3 { font-size: 1.4rem; font-weight: 600; margin: 1.5rem 0 0.75rem; color: #333; }
                h4 { font-size: 1.2rem; font-weight: 600; margin: 1.25rem 0 0.5rem; }
                blockquote { border-left: 4px solid #003da5; padding: 15px 20px; margin: 1.5rem 0; background: #f8f9fa; font-style: italic; color: #555; }
                img { max-width: 100%; height: auto; border-radius: 8px; }
                a { color: #003da5; }
                table { border-collapse: collapse; width: 100%; margin: 1rem 0; }
                table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                table th { background: #f4f4f4; font-weight: 600; }
                pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; font-family: 'Monaco', 'Menlo', monospace; font-size: 14px; }
                hr { border: none; height: 1px; background: #ddd; margin: 2rem 0; }
                ul, ol { margin: 1rem 0; padding-left: 2rem; }
                li { margin-bottom: 0.5rem; }
            `,
            images_upload_url: uploadUrl,
            images_upload_credentials: true,
            automatic_uploads: true,
            file_picker_types: 'image',
            images_upload_handler: function (blobInfo, progress) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.withCredentials = true;
                    xhr.open('POST', uploadUrl);

                    xhr.upload.onprogress = function (e) {
                        progress((e.loaded / e.total) * 100);
                    };

                    xhr.onload = function () {
                        if (xhr.status === 403) {
                            reject({ message: 'Erreur HTTP: ' + xhr.status, remove: true });
                            return;
                        }
                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject('Erreur HTTP: ' + xhr.status);
                            return;
                        }

                        let json = null;
                        try {
                            json = JSON.parse(xhr.responseText);
                        } catch (error) {
                            reject('Reponse invalide: ' + xhr.responseText);
                            return;
                        }

                        if (!json || typeof json.location !== 'string') {
                            reject('Reponse invalide: ' + xhr.responseText);
                            return;
                        }

                        resolve(json.location);
                    };

                    xhr.onerror = function () {
                        reject("Erreur de telechargement de l'image. Verifiez la connexion.");
                    };

                    const formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    formData.append('csrf_token', csrfToken);
                    xhr.send(formData);
                });
            },
            paste_data_images: true,
            paste_as_text: false,
            min_height: 400,
            max_height: 800,
            autoresize_bottom_margin: 50,
            branding: false,
            promotion: false,
            setup: function (editor) {
                editor.ui.registry.addButton('pullquote', {
                    text: 'Citation vedette',
                    icon: 'quote',
                    onAction: function () {
                        editor.insertContent('<blockquote class="pull-quote"><p>Votre citation ici...</p><cite>- Auteur</cite></blockquote>');
                    }
                });

                editor.ui.registry.addButton('infobox', {
                    text: 'Info',
                    icon: 'info',
                    onAction: function () {
                        editor.insertContent('<div class="info-box" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 1rem 0; border-radius: 0 8px 8px 0;"><strong>Information:</strong> Votre texte ici...</div>');
                    }
                });

                editor.on('init', function () {
                    updateWordCount(editor);
                });
                editor.on('keyup', function () {
                    updateWordCount(editor);
                });
                editor.on('change', function () {
                    updateWordCount(editor);
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSlugSync();
        initTinyMce();

        if (postForm) {
            postForm.addEventListener('submit', () => {
                if (typeof tinymce !== 'undefined' && tinymce.get('content-editor')) {
                    tinymce.get('content-editor').save();
                }
            });
        }
    });
})();
