/**
 * Personality Quiz - Combined Scripts
 * Admin + Frontend Quiz + Results
 */

(function() {
    'use strict';

    // =========================================================================
    // ADMIN FUNCTIONALITY
    // =========================================================================

    function initAdmin() {
        if (typeof jQuery === 'undefined') return;

        var $ = jQuery;
        var resultIdx = $('#pq-results-container .pq-result-row').length;
        var questionIdx = $('#pq-questions-container .pq-question-row').length;
        var $secondaryToggle = $('#pq_allow_secondary_results');

        // Toggle collapse
        $(document).on('click', '.pq-toggle, .pq-row-header', function(e) {
            if ($(e.target).closest('.pq-remove-result, .pq-remove-question').length) return;
            $(this).closest('.pq-result-row, .pq-question-row').toggleClass('collapsed');
        });

        // Add result
        $(document).on('click', '.pq-add-result', function() {
            var tpl = $('#pq-result-template').html().replace(/\{\{INDEX\}\}/g, resultIdx++);
            $('#pq-results-container').append(tpl);
            updateResultSelects();
        });

        // Remove result
        $(document).on('click', '.pq-remove-result', function(e) {
            e.stopPropagation();
            if (typeof pqAdmin !== 'undefined' && !confirm(pqAdmin.confirmResult)) return;
            $(this).closest('.pq-result-row').remove();
            updateResultSelects();
        });

        // Add question
        $(document).on('click', '.pq-add-question', function() {
            var tpl = $('#pq-question-template').html().replace(/\{\{Q_INDEX\}\}/g, questionIdx++);
            var $newRow = $(tpl);
            $newRow.find('.pq-row-number').text(questionIdx + '.');
            $('#pq-questions-container').append($newRow);
            updateResultSelects();
        });

        // Remove question
        $(document).on('click', '.pq-remove-question', function(e) {
            e.stopPropagation();
            if (typeof pqAdmin !== 'undefined' && !confirm(pqAdmin.confirmQuestion)) return;
            $(this).closest('.pq-question-row').remove();
            renumberQuestions();
        });

        // Add answer
        $(document).on('click', '.pq-add-answer', function() {
            var $q = $(this).closest('.pq-question-row');
            var qi = $q.data('index');
            var ai = $q.find('.pq-answer-row').length;
            var tpl = $('#pq-answer-template').html()
                .replace(/\{\{Q_INDEX\}\}/g, qi)
                .replace(/\{\{A_INDEX\}\}/g, ai);
            $q.find('.pq-answers-list').append(tpl);
            updateResultSelects();
        });

        // Remove answer
        $(document).on('click', '.pq-remove-answer', function(e) {
            e.preventDefault();
            var $list = $(this).closest('.pq-answers-list');
            if ($list.find('.pq-answer-row').length <= 2) {
                alert('Minimum 2 answers required.');
                return;
            }
            $(this).closest('.pq-answer-row').remove();
        });

        // Update result name in header
        $(document).on('input', '.pq-result-name', function() {
            var name = $(this).val() || 'New Result';
            $(this).closest('.pq-result-row').find('.pq-row-title').text(name);
            updateResultSelects();
        });

        // Update question text in header
        $(document).on('input', '.pq-question-text', function() {
            var text = $(this).val() || 'New Question';
            if (text.length > 60) text = text.substring(0, 60) + '...';
            $(this).closest('.pq-question-row').find('.pq-question-title').text(text);
        });

        // Update result dropdowns
        function updateResultSelects() {
            var results = [];
            $('.pq-result-name').each(function() {
                var name = $(this).val();
                if (name) {
                    results.push({
                        name: name,
                        slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
                    });
                }
            });
            $('.pq-result-select').each(function() {
                var $sel = $(this), val = $sel.val();
                $sel.find('option:not(:first)').remove();
                results.forEach(function(r) {
                $sel.append('<option value="' + r.slug + '">' + r.name + '</option>');
                });
                $sel.val(val);
            });
            toggleSecondaryResultSelects();
        }

        function toggleSecondaryResultSelects() {
            var enabled = $secondaryToggle.is(':checked');
            $('.pq-result-select-secondary').each(function() {
                $(this).toggleClass('is-hidden', !enabled);
            });
        }

        // Renumber questions
        function renumberQuestions() {
            $('#pq-questions-container .pq-question-row').each(function(i) {
                $(this).find('.pq-row-number').text((i + 1) + '.');
            });
        }

        // Image selection
        $(document).on('click', '.pq-select-image', function(e) {
            e.preventDefault();
            var $field = $(this).closest('.pq-image-field');

            var frame = wp.media({
                title: (typeof pqAdmin !== 'undefined') ? pqAdmin.mediaTitle : 'Select Image',
                button: { text: (typeof pqAdmin !== 'undefined') ? pqAdmin.mediaButton : 'Use Image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;
                $field.find('.pq-image-id').val(attachment.id);
                $field.find('.pq-image-preview').html('<img src="' + url + '" alt="">');
                $field.find('.pq-remove-image').show();
            });

            frame.open();
        });

        // Image removal
        $(document).on('click', '.pq-remove-image', function(e) {
            e.preventDefault();
            var $field = $(this).closest('.pq-image-field');
            $field.find('.pq-image-id').val('');
            $field.find('.pq-image-preview').empty();
            $(this).hide();
        });

        // Copy shortcode (admin)
        $(document).on('click', '.pq-copy-shortcode, .pq-copy-btn', function() {
            var sc = $(this).data('shortcode');
            var $msg = $(this).siblings('.pq-copy-feedback, .pq-copied-msg');
            copyToClipboard(sc, function() {
                $msg.addClass('visible');
                setTimeout(function() { $msg.removeClass('visible'); }, 2000);
            });
        });

        if ($secondaryToggle.length) {
            $secondaryToggle.on('change', toggleSecondaryResultSelects);
            toggleSecondaryResultSelects();
        }
    }

    // =========================================================================
    // FRONTEND QUIZ FUNCTIONALITY
    // =========================================================================

    function Quiz(container) {
        this.container = container;
        this.quizId = container.dataset.quizId;
        this.perPage = parseInt(container.dataset.perPage, 10) || 5;
        this.total = parseInt(container.dataset.total, 10);
        this.allowSecondaryResults = container.dataset.allowSecondary === '1';

        try {
            this.resultsData = JSON.parse(container.dataset.results || '{}');
        } catch (e) {
            console.error('Personality Quiz: Failed to parse results data', e);
            this.resultsData = {};
        }

        this.questions = container.querySelectorAll('.pq-question');
        this.prevBtn = container.querySelector('.pq-prev-btn');
        this.nextBtn = container.querySelector('.pq-next-btn');
        this.progressText = container.querySelector('.pq-progress-text');
        this.validation = container.querySelector('.pq-validation');
        this.navigation = container.querySelector('.pq-navigation');
        this.progress = container.querySelector('.pq-progress');
        this.resultPanel = container.querySelector('.pq-result');
        this.resultMain = container.querySelector('.pq-result-main');

        this.currentPage = 0;
        this.totalPages = Math.ceil(this.total / this.perPage);
        this.answers = {};
        this.storageKey = 'pq_' + this.quizId;

        this.init();
    }

    Quiz.prototype.init = function() {
        var urlParams = new URLSearchParams(window.location.search);
        var sharedResult = urlParams.get('result');

        if (sharedResult && this.resultsData[sharedResult]) {
            this.showSharedResult(sharedResult);
            return;
        }

        this.loadState();
        this.bindEvents();
        this.showPage(this.currentPage);
        this.updateProgress();
        this.syncPageRowHeights();
        this.syncResultRowHeights();

        var self = this;
        window.addEventListener('resize', function() {
            self.syncPageRowHeights();
            self.syncResultRowHeights();
        });
    };

    Quiz.prototype.showSharedResult = function(slug) {
        var result = this.resultsData[slug];
        if (!result) return;

        this.winnerSlug = slug;
        this.shareUrl = window.location.href;

        this.container.querySelector('.pq-questions').style.display = 'none';
        this.navigation.style.display = 'none';
        this.progress.style.display = 'none';

        var imgContainer = this.resultPanel.querySelector('.pq-result-image');
        var secondaryImgContainer = this.resultPanel.querySelector('.pq-result-secondary-image');
        var titleEl = this.resultPanel.querySelector('.pq-result-title');
        var descEl = this.resultPanel.querySelector('.pq-result-description');

        imgContainer.innerHTML = result.image ? ('<img src="' + result.image + '" alt="' + result.name + '">') : '';
        secondaryImgContainer.innerHTML = result.secondaryImage ? ('<img src="' + result.secondaryImage + '" alt="' + result.name + '">') : '';
        secondaryImgContainer.classList.toggle('has-image', !!result.secondaryImage);
        titleEl.textContent = result.name;
        descEl.innerHTML = result.description || '';

        this.resultPanel.style.display = 'block';
        this.syncResultRowHeights();
        this.bindEvents();
    };

    Quiz.prototype.loadState = function() {
        try {
            var saved = sessionStorage.getItem(this.storageKey);
            if (!saved) return;

            var state = JSON.parse(saved);
            this.answers = state.answers || {};
            this.currentPage = state.page || 0;

            for (var qIndex in this.answers) {
                var q = this.container.querySelector('[data-question="' + qIndex + '"]');
                if (!q) continue;
                var btn = q.querySelector('[data-answer="' + this.answers[qIndex].answerIndex + '"]');
                if (btn) btn.classList.add('selected');
            }
        } catch (e) {}
    };

    Quiz.prototype.saveState = function() {
        try {
            sessionStorage.setItem(this.storageKey, JSON.stringify({
                answers: this.answers,
                page: this.currentPage
            }));
        } catch (e) {}
    };

    Quiz.prototype.bindEvents = function() {
        var self = this;

        this.container.addEventListener('click', function(e) {
            var answer = e.target.closest('.pq-answer');
            if (answer) self.selectAnswer(answer);
        });

        this.prevBtn.addEventListener('click', function() { self.prevPage(); });
        this.nextBtn.addEventListener('click', function() { self.nextPage(); });

        var retakeBtn = this.container.querySelector('.pq-retake-btn');
        if (retakeBtn) {
            retakeBtn.addEventListener('click', function() { self.restart(); });
        }

        var copyBtn = this.container.querySelector('.pq-copy-link');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var success = self.container.querySelector('.pq-copy-success');
                var urlToCopy = self.shareUrl || window.location.href;
                copyToClipboard(urlToCopy, function() {
                    if (success) {
                        success.classList.add('visible');
                        setTimeout(function() { success.classList.remove('visible'); }, 2000);
                    }
                });
            });
        }
    };

    Quiz.prototype.selectAnswer = function(btn) {
        var question = btn.closest('.pq-question');
        var qIndex = question.dataset.question;
        var aIndex = btn.dataset.answer;
        var resultSlug = btn.dataset.result;
        var secondarySlug = btn.dataset.resultSecondary;

        question.querySelectorAll('.pq-answer').forEach(function(a) {
            a.classList.remove('selected');
        });
        btn.classList.add('selected');

        this.answers[qIndex] = {
            answerIndex: aIndex,
            resultSlug: resultSlug || '',
            resultSecondarySlug: secondarySlug || ''
        };

        this.saveState();
        this.hideValidation();
        this.syncPageRowHeights();
    };

    Quiz.prototype.showPage = function(page) {
        var start = page * this.perPage;
        var end = start + this.perPage;

        this.questions.forEach(function(q, i) {
            q.classList.toggle('active', i >= start && i < end);
        });

        this.prevBtn.style.display = page === 0 ? 'none' : '';
        this.nextBtn.textContent = page >= this.totalPages - 1 ? 'Results' : 'Next';

        this.syncPageRowHeights();
    };

    // =========================================================================
    // UPDATED HEIGHT SYNC LOGIC
    // =========================================================================

    Quiz.prototype.syncPageRowHeights = function() {
        var activeQuestions = this.container.querySelectorAll('.pq-question.active');

        activeQuestions.forEach(function(q) {
            var textCol = q.querySelector('.pq-question-content');
            var imgCol = q.querySelector('.pq-question-image');

            // If both columns exist
            if(textCol && imgCol) {
                // 1. Get the height of the Text Column (includes the 250px min-height from CSS)
                var textHeight = textCol.offsetHeight;

                // 2. Ensure minimum height is respected (though CSS handles it, this is a JS backup)
                var finalHeight = Math.max(textHeight, 250);

                // 3. Force Image Column to be the same height as Text Column
                // This prevents the image (e.g. 1024px) from expanding the row
                imgCol.style.height = finalHeight + 'px';
            }
        });
    };

    // =========================================================================
    // =========================================================================

    Quiz.prototype.syncResultRowHeights = function() {
        if (!this.resultPanel || this.resultPanel.style.display === 'none') {
            return;
        }

        var textCol = this.resultPanel.querySelector('.pq-result-content');
        var imgCol = this.resultPanel.querySelector('.pq-result-image');

        if (textCol && imgCol) {
            var textHeight = textCol.offsetHeight;
            var finalHeight = Math.max(textHeight, 250);
            imgCol.style.height = finalHeight + 'px';
        }
    };

    Quiz.prototype.updateProgress = function() {
        var start = this.currentPage * this.perPage + 1;
        var end = Math.min((this.currentPage + 1) * this.perPage, this.total);
        this.progressText.textContent = 'Question ' + start + '-' + end + ' of ' + this.total;
    };

    Quiz.prototype.prevPage = function() {
        if (this.currentPage > 0) {
            this.currentPage--;
            this.showPage(this.currentPage);
            this.updateProgress();
            this.saveState();
            this.scrollToTop();
        }
    };

    Quiz.prototype.nextPage = function() {
        if (!this.validateCurrentPage()) return;

        if (this.currentPage >= this.totalPages - 1) {
            var missingIndex = this.findFirstMissingAnswer(0, this.total);
            if (missingIndex !== null) {
                this.goToQuestion(missingIndex);
                return;
            }
            this.showResults();
            return;
        }

        this.currentPage++;
        this.showPage(this.currentPage);
        this.updateProgress();
        this.saveState();
        this.scrollToTop();
    };

    Quiz.prototype.validateCurrentPage = function() {
        var start = this.currentPage * this.perPage;
        var end = Math.min(start + this.perPage, this.total);
        var missingIndex = this.findFirstMissingAnswer(start, end);

        if (missingIndex !== null) {
            var missedQuestion = this.container.querySelector('[data-question="' + missingIndex + '"]');
            if (missedQuestion) {
                missedQuestion.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return false;
        }
        return true;
    };

    Quiz.prototype.findFirstMissingAnswer = function(start, end) {
        for (var i = start; i < end; i++) {
            if (!this.answers[i]) {
                return i;
            }
        }
        return null;
    };

    Quiz.prototype.goToQuestion = function(index) {
        this.currentPage = Math.floor(index / this.perPage);
        this.showPage(this.currentPage);
        this.updateProgress();
        this.saveState();
        var missedQuestion = this.container.querySelector('[data-question="' + index + '"]');
        if (missedQuestion) {
            missedQuestion.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    Quiz.prototype.showValidation = function(msg) {
        this.validation.textContent = msg;
        this.validation.classList.add('visible');
    };

    Quiz.prototype.hideValidation = function() {
        this.validation.classList.remove('visible');
    };

    Quiz.prototype.showResults = function() {
        var counts = {};
        var priorities = {};

        for (var qIndex in this.answers) {
            var primarySlug = this.answers[qIndex].resultSlug;
            var secondarySlug = this.answers[qIndex].resultSecondarySlug;
            var slugs = [];

            if (primarySlug) slugs.push(primarySlug);
            if (this.allowSecondaryResults && secondarySlug) slugs.push(secondarySlug);

            slugs.filter(function(value, index, self) {
                return self.indexOf(value) === index;
            }).forEach(function(slug) {
                if (slug && this.resultsData[slug]) {
                    counts[slug] = (counts[slug] || 0) + 1;
                    priorities[slug] = this.resultsData[slug].priority || 99;
                }
            }, this);
        }

        var maxCount = 0;
        var winner = null;

        for (var s in counts) {
            if (counts[s] > maxCount) {
                maxCount = counts[s];
                winner = s;
            } else if (counts[s] === maxCount && winner) {
                if ((priorities[s] || 99) < (priorities[winner] || 99)) winner = s;
            }
        }

        if (!winner || !this.resultsData[winner]) {
            this.showValidation('Could not calculate result. Check answer-to-result mappings.');
            return;
        }

        var result = this.resultsData[winner];
        this.winnerSlug = winner;

        var shareUrl = new URL(window.location.href.split('?')[0]);
        shareUrl.searchParams.set('result', winner);
        this.shareUrl = shareUrl.toString();

        try { history.replaceState(null, '', this.shareUrl); } catch(e) {}
        try { sessionStorage.removeItem(this.storageKey); } catch (e) {}

        this.container.querySelector('.pq-questions').style.display = 'none';
        this.navigation.style.display = 'none';
        this.progress.style.display = 'none';
        this.hideValidation();

        var imgContainer = this.resultPanel.querySelector('.pq-result-image');
        var secondaryImgContainer = this.resultPanel.querySelector('.pq-result-secondary-image');
        var titleEl = this.resultPanel.querySelector('.pq-result-title');
        var descEl = this.resultPanel.querySelector('.pq-result-description');

        imgContainer.innerHTML = result.image ? ('<img src="' + result.image + '" alt="' + result.name + '">') : '';
        secondaryImgContainer.innerHTML = result.secondaryImage ? ('<img src="' + result.secondaryImage + '" alt="' + result.name + '">') : '';
        secondaryImgContainer.classList.toggle('has-image', !!result.secondaryImage);
        titleEl.textContent = result.name;
        descEl.innerHTML = result.description || '';

        this.resultPanel.style.display = 'block';
        this.syncResultRowHeights();
        this.scrollToTop();
    };

    Quiz.prototype.restart = function() {
        this.answers = {};
        this.currentPage = 0;
        this.winnerSlug = null;
        this.shareUrl = null;

        try { sessionStorage.removeItem(this.storageKey); } catch (e) {}

        try {
            var cleanUrl = window.location.href.split('?')[0];
            history.replaceState(null, '', cleanUrl);
        } catch(e) {}

        this.container.querySelectorAll('.pq-answer.selected').forEach(function(a) {
            a.classList.remove('selected');
        });

        this.container.querySelector('.pq-questions').style.display = '';
        this.navigation.style.display = '';
        this.progress.style.display = '';
        this.resultPanel.style.display = 'none';
        this.resultPanel.querySelector('.pq-result-secondary-image').classList.remove('has-image');

        this.showPage(0);
        this.updateProgress();
        this.scrollToTop();
    };

    Quiz.prototype.scrollToTop = function() {
        this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // =========================================================================
    // UTILITIES
    // =========================================================================

    function copyToClipboard(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(callback).catch(function() {
                fallbackCopy(text, callback);
            });
        } else {
            fallbackCopy(text, callback);
        }
    }

    function fallbackCopy(text, callback) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (callback) callback();
        } catch (e) {
            console.error('Copy failed:', e);
        }
        document.body.removeChild(textarea);
    }

    // =========================================================================
    // INITIALIZATION
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function() {
        if (document.body.classList.contains('wp-admin')) {
            initAdmin();
        }

        var quizzes = document.querySelectorAll('.pq-quiz');
        quizzes.forEach(function(el) {
            new Quiz(el);
        });
    });

})();
